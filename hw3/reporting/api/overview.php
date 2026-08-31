<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/reporting-api.php';

requireApiGetRequest();

$user = requireApiUser([
    'super_admin',
    'analyst'
]);

$permissions = [
    'technology' => userCanAccessSection(
        $user,
        'technology'
    ),
    'performance' => userCanAccessSection(
        $user,
        'performance'
    ),
    'behavior' => userCanAccessSection(
        $user,
        'behavior'
    )
];

if (!in_array(true, $permissions, true)) {
    apiResponse(
        ['error' => 'You do not have access to any analytics sections'],
        403
    );
}

$dateRange = getApiDateRange();

$queryParameters = [
    'start' => $dateRange['sql_start'],
    'end' => $dateRange['sql_end_exclusive']
];

$response = [
    'success' => true,

    'dateRange' => [
        'start' => $dateRange['start_date'],
        'end' => $dateRange['end_date']
    ],

    'permissions' => $permissions,

    'summary' => [],
    'charts' => [],
    'tables' => []
];

try {
    $pdo = database();

    /*
     * Technology section
     */
    if ($permissions['technology']) {
        $summaryStatement = $pdo->prepare(
            'SELECT
                COUNT(*) AS page_loads,
                COUNT(DISTINCT session_id) AS unique_sessions
             FROM static_data
             WHERE collected_at >= :start
               AND collected_at < :end'
        );

        $summaryStatement->execute($queryParameters);

        $technologySummary =
            $summaryStatement->fetch() ?: [];

        $response['summary']['pageLoads'] =
            (int) ($technologySummary['page_loads'] ?? 0);

        $response['summary']['uniqueSessions'] =
            (int) ($technologySummary['unique_sessions'] ?? 0);

        $pageStatement = $pdo->prepare(
            'SELECT
                page_url,
                COUNT(*) AS page_loads,
                COUNT(DISTINCT session_id) AS unique_sessions
             FROM static_data
             WHERE collected_at >= :start
               AND collected_at < :end
             GROUP BY page_url
             ORDER BY page_loads DESC, page_url ASC
             LIMIT 20'
        );

        $pageStatement->execute($queryParameters);

        $pageRows = $pageStatement->fetchAll();

        $topPages = array_map(
            static function (array $row): array {
                return [
                    'pageUrl' => $row['page_url'],
                    'pageLoads' => (int) $row['page_loads'],
                    'uniqueSessions' =>
                        (int) $row['unique_sessions']
                ];
            },
            $pageRows
        );

        $response['charts']['pageLoadsByPage'] =
            $topPages;

        $response['tables']['topPages'] =
            $topPages;
    }

    /*
     * Performance section
     */
    if ($permissions['performance']) {
        $performanceStatement = $pdo->prepare(
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

        $performanceStatement->execute(
            $queryParameters
        );

        $performanceSummary =
            $performanceStatement->fetch() ?: [];

        $response['summary']['performanceMeasurements'] =
            (int) (
                $performanceSummary['measurements'] ??
                0
            );

        $response['summary']['averageLoadTimeMs'] =
            (float) (
                $performanceSummary['average_load_time_ms'] ??
                0
            );

        $response['summary']['fastestLoadTimeMs'] =
            (float) (
                $performanceSummary['fastest_load_time_ms'] ??
                0
            );

        $response['summary']['slowestLoadTimeMs'] =
            (float) (
                $performanceSummary['slowest_load_time_ms'] ??
                0
            );

        $performancePageStatement = $pdo->prepare(
            'SELECT
                page_url,
                COUNT(*) AS samples,
                ROUND(AVG(total_load_time_ms), 2)
                    AS average_load_time_ms
             FROM performance_data
             WHERE collected_at >= :start
               AND collected_at < :end
             GROUP BY page_url
             ORDER BY average_load_time_ms DESC
             LIMIT 20'
        );

        $performancePageStatement->execute(
            $queryParameters
        );

        $performanceRows =
            $performancePageStatement->fetchAll();

        $response['charts']['loadTimeByPage'] =
            array_map(
                static function (array $row): array {
                    return [
                        'pageUrl' => $row['page_url'],
                        'samples' => (int) $row['samples'],
                        'averageLoadTimeMs' =>
                            (float) $row['average_load_time_ms']
                    ];
                },
                $performanceRows
            );
    }

    /*
     * Behavior section
     */
    if ($permissions['behavior']) {
        $activitySummaryStatement = $pdo->prepare(
            'SELECT COUNT(*) AS total_events
             FROM activity_events
             WHERE event_time >= :start
               AND event_time < :end'
        );

        $activitySummaryStatement->execute(
            $queryParameters
        );

        $activitySummary =
            $activitySummaryStatement->fetch() ?: [];

        $response['summary']['activityEvents'] =
            (int) (
                $activitySummary['total_events'] ??
                0
            );

        $activityTypeStatement = $pdo->prepare(
            'SELECT
                event_type,
                COUNT(*) AS total
             FROM activity_events
             WHERE event_time >= :start
               AND event_time < :end
             GROUP BY event_type
             ORDER BY total DESC, event_type ASC'
        );

        $activityTypeStatement->execute(
            $queryParameters
        );

        $activityRows =
            $activityTypeStatement->fetchAll();

        $response['charts']['activityByType'] =
            array_map(
                static function (array $row): array {
                    return [
                        'eventType' => $row['event_type'],
                        'total' => (int) $row['total']
                    ];
                },
                $activityRows
            );
    }

    apiResponse($response);
} catch (Throwable $error) {
    error_log(
        '[CSE135 Overview API] ' .
        $error->getMessage()
    );

    apiResponse(
        ['error' => 'Unable to load the analytics overview'],
        500
    );
}