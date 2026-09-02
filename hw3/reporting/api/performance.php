<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/reporting-api.php';

requireApiGetRequest();
requireApiSection('performance');

$dateRange = getApiDateRange();

$queryParameters = [
    'start' => $dateRange['sql_start'],
    'end' => $dateRange['sql_end_exclusive']
];

try {
    $pdo = database();

    /*
     * Overall performance summary.
     */
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

    $summaryStatement->execute($queryParameters);

    $summaryRow =
        $summaryStatement->fetch() ?: [];

    /*
     * Performance grouped by page.
     */
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

    $pageStatement->execute($queryParameters);

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

    /*
     * Individual performance measurements for the data table.
     */
    $recordStatement = $pdo->prepare(
        'SELECT
            id,
            session_id,
            page_url,
            collected_at,
            page_load_start,
            page_load_end,
            total_load_time_ms
         FROM performance_data
         WHERE collected_at >= :start
           AND collected_at < :end
         ORDER BY collected_at DESC
         LIMIT 100'
    );

    $recordStatement->execute($queryParameters);

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
                    (float) $row['total_load_time_ms']
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
