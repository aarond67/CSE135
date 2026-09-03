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
                COUNT(*) AS page_loads
             FROM static_data
             WHERE collected_at >= :start
               AND collected_at < :end
             GROUP BY page_url
             ORDER BY page_loads DESC, page_url ASC
             LIMIT 8'
        );

        $pageStatement->execute($queryParameters);

        $pageRows = $pageStatement->fetchAll();

        $topPages = array_map(
            static function (array $row): array {
                return [
                    'pageUrl' => $row['page_url'],
                    'pageLoads' => (int) $row['page_loads']
                ];
            },
            $pageRows
        );

        $response['charts']['pageLoadsByPage'] =
            $topPages;
    }

    /*
     * Performance section
     */
    if ($permissions['performance']) {
        $performanceStatement = $pdo->prepare(
            'SELECT
                ROUND(AVG(total_load_time_ms), 2)
                    AS average_load_time_ms
             FROM performance_data
             WHERE collected_at >= :start
               AND collected_at < :end
               AND total_load_time_ms IS NOT NULL
               AND total_load_time_ms >= 0'
        );

        $performanceStatement->execute(
            $queryParameters
        );

        $performanceSummary =
            $performanceStatement->fetch() ?: [];

        $response['summary']['averageLoadTimeMs'] =
            isset($performanceSummary['average_load_time_ms'])
                ? (float) $performanceSummary['average_load_time_ms']
                : null;

        $performancePageStatement = $pdo->prepare(
            'SELECT
                page_url,
                COUNT(*) AS samples,
                ROUND(AVG(total_load_time_ms), 2)
                    AS average_load_time_ms,
                ROUND(MAX(total_load_time_ms), 2)
                    AS slowest_load_time_ms
             FROM performance_data
             WHERE collected_at >= :start
               AND collected_at < :end
               AND total_load_time_ms IS NOT NULL
               AND total_load_time_ms >= 0
             GROUP BY page_url
             ORDER BY average_load_time_ms DESC, page_url ASC
             LIMIT 10'
        );

        $performancePageStatement->execute(
            $queryParameters
        );

        $performanceRows =
            $performancePageStatement->fetchAll();

        $response['tables']['pagePerformance'] =
            array_map(
                static function (array $row): array {
                    return [
                        'pageUrl' => $row['page_url'],
                        'measurements' => (int) $row['samples'],
                        'averageLoadTimeMs' =>
                            (float) $row['average_load_time_ms'],
                        'slowestLoadTimeMs' =>
                            (float) $row['slowest_load_time_ms']
                    ];
                },
                $performanceRows
            );
    }

    /*
     * Behavior section
     */
    if ($permissions['behavior']) {
        /*
         * One row per observed shop session, not per click or page load.
         * An earliest product view followed by any later checkout qualifies.
         * Both steps must be inside the selected dates; equal times do not
         * establish an order. Query strings and fragments keep the same step.
         */
        $shoppingStatement = $pdo->prepare(
            "WITH session_progress AS (
                SELECT
                    session_id,
                    MIN(CASE WHEN
                        page_url = 'https://test.baddecisions.site/product-detail.html'
                        OR page_url LIKE 'https://test.baddecisions.site/product-detail.html?%'
                        OR page_url LIKE 'https://test.baddecisions.site/product-detail.html#%'
                        THEN collected_at END) AS first_product_at,
                    MAX(CASE WHEN
                        page_url = 'https://test.baddecisions.site/checkout.html'
                        OR page_url LIKE 'https://test.baddecisions.site/checkout.html?%'
                        OR page_url LIKE 'https://test.baddecisions.site/checkout.html#%'
                        THEN collected_at END) AS last_checkout_at
                FROM static_data
                WHERE collected_at >= :start
                  AND collected_at < :end
                  AND page_url LIKE 'https://test.baddecisions.site/%'
                GROUP BY session_id
             )
             SELECT
                COUNT(*) AS visited_sessions,
                COUNT(first_product_at) AS product_sessions,
                COALESCE(SUM(CASE
                    WHEN last_checkout_at > first_product_at THEN 1
                    ELSE 0 END), 0) AS checkout_sessions
             FROM session_progress"
        );

        $shoppingStatement->execute($queryParameters);

        $shopping = $shoppingStatement->fetch() ?: [];

        $response['charts']['shoppingProgress'] = [
            'visitedSessions' => (int) ($shopping['visited_sessions'] ?? 0),
            'productSessions' => (int) ($shopping['product_sessions'] ?? 0),
            'checkoutSessions' => (int) ($shopping['checkout_sessions'] ?? 0)
        ];
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
