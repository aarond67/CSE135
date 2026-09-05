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
$tableTitle = '';
$tableHeaders = [];
$tableRows = [];

if ($report['category'] === 'technology') {
    $data = getTechnologyReportData($range);

    $chartTitle = 'Page loads by browser';
    $chartNote = 'Recorded page loads, including repeat visits.';
    $chartRows = array_map(
        static fn (array $row): array => [
            'label' => (string) $row['browser'],
            'value' => (float) $row['page_loads'],
            'display' => number_format((int) $row['page_loads'])
        ],
        $data['browsers']
    );

    $tableTitle = 'Screen-size summary';
    $tableHeaders = ['Screen group', 'Sessions', 'Page loads', 'Average width'];
    $tableRows = array_map(
        static fn (array $row): array => [
            (string) $row['screen_group'],
            number_format((int) $row['sessions']),
            number_format((int) $row['page_loads']),
            $row['average_screen_width'] !== null
                ? number_format((float) $row['average_screen_width'], 0) . ' px'
                : 'Not available'
        ],
        $data['devices']
    );
} elseif ($report['category'] === 'behavior') {
    $data = getBehaviorReportData($range);
    $progress = $data['progress'];

    $chartTitle = 'Sessions reaching each shopping step';
    $chartNote = 'Each session counts once per step, and the steps must occur in order.';
    $chartRows = [
        [
            'label' => 'Visited site',
            'value' => $progress['visited'],
            'display' => number_format($progress['visited'])
        ],
        [
            'label' => 'Viewed product',
            'value' => $progress['product'],
            'display' => number_format($progress['product'])
        ],
        [
            'label' => 'Reached checkout',
            'value' => $progress['checkout'],
            'display' => number_format($progress['checkout'])
        ],
        [
            'label' => 'Demo success shown',
            'value' => $progress['success'],
            'display' => number_format($progress['success'])
        ]
    ];

    $tableTitle = 'Pages recording JavaScript errors';
    $tableHeaders = ['Page', 'Errors', 'Latest occurrence', 'Example message'];
    $tableRows = array_map(
        static fn (array $row): array => [
            reportPageLabel((string) $row['page_url']),
            number_format((int) $row['error_count']),
            (string) $row['latest_error'] . ' UTC',
            (string) ($row['example_message'] ?? 'No message recorded')
        ],
        $data['errors']
    );
} else {
    $rows = getPerformanceExportData($range);

    $chartTitle = 'Average load time by page';
    $chartNote = 'Longer bars identify pages that deserve a closer performance review.';
    $chartRows = array_map(
        static fn (array $row): array => [
            'label' => reportPageLabel((string) $row['page_url']),
            'value' => (float) $row['average_ms'],
            'display' => number_format((float) $row['average_ms'], 1) . ' ms'
        ],
        $rows
    );

    $tableTitle = 'Page performance';
    $tableHeaders = ['Page', 'Measurements', 'Average', 'Slowest'];
    $tableRows = array_map(
        static fn (array $row): array => [
            reportPageLabel((string) $row['page_url']),
            number_format((int) $row['measurements']),
            number_format((float) $row['average_ms'], 1) . ' ms',
            number_format((float) $row['slowest_ms'], 1) . ' ms'
        ],
        $rows
    );
}

$chartMaximum = max(max(
    array_map(
        static fn (array $row): float => (float) $row['value'],
        $chartRows ?: [['value' => 0]]
    )),
    1
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
