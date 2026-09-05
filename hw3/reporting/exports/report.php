<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/simple-pdf.php';

$reportKey = is_string($_GET['key'] ?? null) ? $_GET['key'] : '';
[$user, $report, $savedReport] = requireSavedReportAccess($reportKey);
$range = getReportDateRange();

$pdf = new SimplePdf();
$pdf->title($report['title']);
$pdf->paragraph('Bad Decisions Analytics | ' . $range['start'] . ' through ' . $range['end'] . ' UTC');
$pdf->heading('Guiding question');
$pdf->paragraph($report['guiding_question']);

if ($report['category'] === 'technology') {
    $data = getTechnologyReportData($range);
    $maximum = max(array_map(
        static fn (array $row): int => (int) $row['page_loads'],
        $data['browsers'] ?: [['page_loads' => 0]]
    ));

    $pdf->heading('Page loads by browser');
    foreach ($data['browsers'] as $row) {
        $pdf->bar($row['browser'], (float) $row['page_loads'], $maximum, (string) $row['page_loads']);
    }

    $pdf->heading('Screen-size summary');
    $pdf->row(['Screen group', 'Sessions', 'Loads', 'Avg width']);
    foreach ($data['devices'] as $row) {
        $pdf->row([
            $row['screen_group'],
            $row['sessions'],
            $row['page_loads'],
            ($row['average_screen_width'] ?? 'n/a') . ' px'
        ]);
    }
} elseif ($report['category'] === 'behavior') {
    $data = getBehaviorReportData($range);
    $progress = $data['progress'];
    $maximum = max($progress['visited'], 1);

    $pdf->heading('Sessions reaching each shopping step');
    foreach ([
        'Visited site' => $progress['visited'],
        'Viewed product' => $progress['product'],
        'Reached checkout' => $progress['checkout'],
        'Demo success shown' => $progress['success']
    ] as $label => $count) {
        $pdf->bar($label, $count, $maximum, (string) $count);
    }

    $pdf->heading('Pages recording JavaScript errors');
    $pdf->row(['Page', 'Errors', 'Latest', 'Message']);
    foreach ($data['errors'] as $row) {
        $pdf->row([
            parse_url($row['page_url'], PHP_URL_PATH) ?: $row['page_url'],
            $row['error_count'],
            $row['latest_error'],
            $row['example_message'] ?? 'No message'
        ]);
    }
} else {
    $rows = getPerformanceExportData($range);
    $maximum = max(array_map(
        static fn (array $row): float => (float) $row['average_ms'],
        $rows ?: [['average_ms' => 0]]
    ));

    $pdf->heading('Average load time by page');
    foreach ($rows as $row) {
        $label = parse_url($row['page_url'], PHP_URL_PATH) ?: $row['page_url'];
        $pdf->bar($label, (float) $row['average_ms'], $maximum, number_format((float) $row['average_ms'], 1) . ' ms');
    }

    $pdf->heading('Page performance');
    $pdf->row(['Page', 'Samples', 'Average', 'Slowest']);
    foreach ($rows as $row) {
        $pdf->row([
            parse_url($row['page_url'], PHP_URL_PATH) ?: $row['page_url'],
            $row['measurements'],
            $row['average_ms'] . ' ms',
            $row['slowest_ms'] . ' ms'
        ]);
    }
}

$pdf->heading('Analyst comments');
$pdf->paragraph(
    ($savedReport['analyst_comments'] ?? '') !== ''
        ? substr((string) $savedReport['analyst_comments'], 0, 1200)
        : 'No analyst comments were saved with this report.'
);

$filename = preg_replace('/[^a-z0-9-]+/', '-', strtolower($reportKey)) . '-' . $range['end'] . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
echo $pdf->output();
