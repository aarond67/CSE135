<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/reporting-api.php';

requireApiGetRequest();
requireApiReport('performance-overview');

$dateRange = getApiDateRange();

$dateParams = [
    'start' => $dateRange['sql_start'],
    'end' => $dateRange['sql_end_exclusive']
];

// Split one page load into four stages. Missing or inconsistent timing stays
// unavailable; treating it as zero would make the page look faster.
function getLoadingStages(array $row): ?array
{
    $timing = json_decode($row['navigation_timing'] ?? '', true);

    if (!is_array($timing)) {
        return null;
    }

    $keys = [
        'startTime', 'requestStart', 'responseStart',
        'responseEnd', 'loadEventEnd'
    ];
    $values = [];

    foreach ($keys as $key) {
        $value = $timing[$key] ?? null;

        if (
            (!is_int($value) && !is_float($value)) ||
            !is_finite((float) $value) ||
            $value < 0
        ) {
            return null;
        }

        $values[] = (float) $value;
    }

    for ($index = 1; $index < count($values); $index++) {
        if ($values[$index] < $values[$index - 1]) {
            return null;
        }
    }

    $total = $row['total_load_time_ms'] ?? null;
    $navigationTotal = $values[4] - $values[0];

    if (
        !is_numeric($total) ||
        !is_finite((float) $total) ||
        (float) $total <= 0 ||
        $navigationTotal <= 0 ||
        abs($navigationTotal - (float) $total) > 1.0
    ) {
        return null;
    }

    return [
        'beforeRequestMs' => $values[1] - $values[0],
        'waitingMs' => $values[2] - $values[1],
        'downloadMs' => $values[3] - $values[2],
        'afterResponseMs' => $values[4] - $values[3]
    ];
}

try {
    $pdo = database();

    // These cards use every performance record in the selected dates.
    $summaryStatement = $pdo->prepare(
        'SELECT
            COUNT(*) AS measurements,
            ROUND(AVG(total_load_time_ms), 2)
                AS average_load_time_ms,
            ROUND(MIN(total_load_time_ms), 2)
                AS fastest_load_time_ms,
            ROUND(MAX(total_load_time_ms), 2)
                AS slowest_load_time_ms
         FROM performance_data
         WHERE collected_at >= :start
           AND collected_at < :end'
    );

    $summaryStatement->execute($dateParams);

    $summaryRow =
        $summaryStatement->fetch() ?: [];

    // Keep the per-page summary available to API callers.
    $pageStatement = $pdo->prepare(
        'SELECT
            page_url,
            COUNT(*) AS measurements,
            ROUND(AVG(total_load_time_ms), 2)
                AS average_load_time_ms,
            ROUND(MIN(total_load_time_ms), 2)
                AS fastest_load_time_ms,
            ROUND(MAX(total_load_time_ms), 2)
                AS slowest_load_time_ms
         FROM performance_data
         WHERE collected_at >= :start
           AND collected_at < :end
         GROUP BY page_url
         ORDER BY average_load_time_ms DESC,
                  page_url ASC'
    );

    $pageStatement->execute($dateParams);

    $pageRows = array_map(
        static function (array $row): array {
            return [
                'pageUrl' => $row['page_url'],
                'measurements' =>
                    (int) $row['measurements'],
                'averageLoadTimeMs' =>
                    (float) $row['average_load_time_ms'],
                'fastestLoadTimeMs' =>
                    (float) $row['fastest_load_time_ms'],
                'slowestLoadTimeMs' =>
                    (float) $row['slowest_load_time_ms']
            ];
        },
        $pageStatement->fetchAll()
    );

    // The two chart views and stage grid share the latest 100 records.
    $recordStatement = $pdo->prepare(
        'SELECT
            id,
            session_id,
            page_url,
            collected_at,
            page_load_start,
            page_load_end,
            total_load_time_ms,
            navigation_timing
         FROM performance_data
         WHERE collected_at >= :start
           AND collected_at < :end
         ORDER BY collected_at DESC, id DESC
         LIMIT 100'
    );

    $recordStatement->execute($dateParams);

    $records = array_map(
        static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'sessionId' => $row['session_id'],
                'pageUrl' => $row['page_url'],
                'collectedAt' => $row['collected_at'],
                'pageLoadStart' => $row['page_load_start'],
                'pageLoadEnd' => $row['page_load_end'],
                'totalLoadTimeMs' =>
                    $row['total_load_time_ms'] === null
                        ? null
                        : (float) $row['total_load_time_ms'],
                'loadingStages' => getLoadingStages($row)
            ];
        },
        $recordStatement->fetchAll()
    );

    apiResponse([
        'success' => true,

        'dateRange' => [
            'start' => $dateRange['start_date'],
            'end' => $dateRange['end_date']
        ],

        'summary' => [
            'measurements' =>
                (int) ($summaryRow['measurements'] ?? 0),

            'averageLoadTimeMs' =>
                (float) (
                    $summaryRow['average_load_time_ms'] ??
                    0
                ),

            'fastestLoadTimeMs' =>
                (float) (
                    $summaryRow['fastest_load_time_ms'] ??
                    0
                ),

            'slowestLoadTimeMs' =>
                (float) (
                    $summaryRow['slowest_load_time_ms'] ??
                    0
                )
        ],

        'byPage' => $pageRows,
        'records' => $records
    ]);
} catch (Throwable $error) {
    error_log(
        '[CSE135 Performance API] ' .
        $error->getMessage()
    );

    apiResponse(
        ['error' => 'Unable to load performance data'],
        500
    );
}
