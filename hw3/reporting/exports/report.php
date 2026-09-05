<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$reportKey = is_string($_GET['key'] ?? null) ? $_GET['key'] : '';
[$user, $report, $savedReport] = requireSavedReportAccess($reportKey);
$range = getReportDateRange();

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    abortRequest(
        500,
        'PDF Export Unavailable',
        'The PDF dependency has not been installed on the reporting server.'
    );
}

try {
    require_once $autoloadPath;
} catch (Throwable $error) {
    error_log('PDF dependency error: ' . $error->getMessage());
    abortRequest(
        500,
        'PDF Export Unavailable',
        'The reporting server could not load the PDF dependency.'
    );
}

if (!class_exists(Dompdf::class)) {
    abortRequest(
        500,
        'PDF Export Unavailable',
        'The reporting server could not load the PDF dependency.'
    );
}

/**
 * Shorten a collected URL for display while keeping its query string.
 */
function reportPageLabel(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);
    $label = is_string($path) && $path !== '' ? $path : $url;

    return is_string($query) && $query !== ''
        ? $label . '?' . $query
        : $label;
}

$chartTitle = '';
$chartNote = '';
$chartRows = [];
$chartMinimumMaximum = 1;
$tableTitle = '';
$tableHeaders = [];
$tableRows = [];

if ($report['category'] === 'technology') {
    $data = getTechnicalErrorReportData($range);

    $chartTitle = 'Sessions affected by JavaScript errors';
    $chartNote = 'Bars count distinct affected sessions. Repeated errors in one session do not make its bar larger.';
    $chartRows = array_map(
        static fn (array $row): array => [
            'label' => reportPageLabel((string) $row['page_url']),
            'value' => (float) $row['affected_sessions'],
            'display' => number_format((int) $row['affected_sessions']) . ' sessions'
        ],
        $data['pages']
    );

    $tableTitle = 'JavaScript errors to investigate';
    $tableHeaders = ['Page', 'Error message', 'Occurrences', 'Sessions', 'Latest'];
    $tableRows = array_map(
        static fn (array $row): array => [
            reportPageLabel((string) $row['page_url']),
            (string) $row['error_message'],
            number_format((int) $row['error_occurrences']),
            number_format((int) $row['affected_sessions']),
            (string) $row['latest_error'] . ' UTC'
        ],
        $data['details']
    );
} elseif ($report['report_key'] === 'checkout-dropoff') {
    $data = getCheckoutDropoffReportData($range);

    $chartTitle = 'Sessions reaching each checkout stage';
    $chartNote = 'Stages count ordered sessions. Checkout step events contain only the step number and no form values.';
    $chartRows = array_map(
        static fn (array $stage): array => [
            'label' => (string) $stage['label'],
            'value' => (float) $stage['count'],
            'display' => number_format((int) $stage['count']) . ' sessions'
        ],
        $data['stages']
    );

    $tableTitle = 'Stage-by-stage drop-off';
    $tableHeaders = ['Stage reached', 'Sessions', 'Drop from prior', 'Continued from prior'];
    $tableRows = array_map(
        static fn (array $stage): array => [
            (string) $stage['label'],
            number_format((int) $stage['count']),
            $stage['drop'] === null ? '—' : number_format((int) $stage['drop']),
            $stage['continued_rate'] === null
                ? '—'
                : number_format((float) $stage['continued_rate'], 1) . '%'
        ],
        $data['stages']
    );
} elseif ($report['category'] === 'behavior') {
    $data = getPageEngagementReportData($range);
    $chartMinimumMaximum = 100;

    $chartTitle = 'Engaged-session rate by page';
    $chartNote = 'A page session counts as engaged when it includes a click, scroll, or key press.';
    $chartRows = array_map(
        static fn (array $row): array => [
            'label' => reportPageLabel((string) $row['page_url']),
            'value' => (float) $row['engagement_rate'],
            'display' => number_format((float) $row['engagement_rate'], 1) . '%'
        ],
        array_slice($data['pages'], 0, 10)
    );

    $tableTitle = 'Page engagement details';
    $tableHeaders = ['Page', 'Page sessions', 'Engaged', 'Rate', 'Clicks', 'Scrolls'];
    $tableRows = array_map(
        static fn (array $row): array => [
            reportPageLabel((string) $row['page_url']),
            number_format((int) $row['page_sessions']),
            number_format((int) $row['engaged_sessions']),
            number_format((float) $row['engagement_rate'], 1) . '%',
            number_format((int) $row['clicks']),
            number_format((int) $row['scrolls'])
        ],
        $data['pages']
    );
} else {
    $budgetData = getPerformanceBudgetData($range);
    $rows = $budgetData['pages'];
    $chartMinimumMaximum = (float) $budgetData['budget_ms'];

    $chartTitle = '75th-percentile load time by page';
    $chartNote = 'Each page is compared with a 3,000 ms load-time budget.';
    $chartRows = array_map(
        static fn (array $row): array => [
            'label' => reportPageLabel((string) $row['page_url']),
            'value' => (float) $row['p75_ms'],
            'display' => number_format((float) $row['p75_ms'], 1) . ' ms'
        ],
        $rows
    );

    $tableTitle = 'Performance budget results';
    $tableHeaders = ['Page', 'Measurements', 'p75', 'Average', 'Slowest', 'Result'];
    $tableRows = array_map(
        static fn (array $row): array => [
            reportPageLabel((string) $row['page_url']),
            number_format((int) $row['measurements']),
            number_format((float) $row['p75_ms'], 1) . ' ms',
            number_format((float) $row['average_ms'], 1) . ' ms',
            number_format((float) $row['slowest_ms'], 1) . ' ms',
            $row['within_budget'] ? 'Within budget' : 'Over budget'
        ],
        $rows
    );
}

$chartMaximum = max(max(
    array_map(
        static fn (array $row): float => (float) $row['value'],
        $chartRows ?: [['value' => 0]]
    )),
    $chartMinimumMaximum
);

$comments = trim((string) ($savedReport['analyst_comments'] ?? ''));
$reportPdfCss = file_get_contents(
    dirname(__DIR__) . '/assets/css/report-pdf.css'
);

if ($reportPdfCss === false) {
    abortRequest(
        500,
        'PDF Export Unavailable',
        'The PDF stylesheet could not be loaded.'
    );
}

ob_start();
require dirname(__DIR__) . '/templates/report-pdf.php';
$reportHtml = ob_get_clean();

if (!is_string($reportHtml)) {
    abortRequest(500, 'PDF Export Unavailable', 'The report template could not be rendered.');
}

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('chroot', dirname(__DIR__));

$filename = preg_replace('/[^a-z0-9-]+/', '-', strtolower($reportKey))
    . '-' . $range['end'] . '.pdf';

try {
    $pdf = new Dompdf($options);
    $pdf->loadHtml($reportHtml, 'UTF-8');
    $pdf->setPaper('letter', 'portrait');
    $pdf->render();

    header('Cache-Control: private, no-store');
    $pdf->stream($filename, ['Attachment' => true]);
} catch (Throwable $error) {
    error_log('PDF render error: ' . $error->getMessage());
    abortRequest(
        500,
        'PDF Export Unavailable',
        'The PDF could not be generated. Please try again.'
    );
}
