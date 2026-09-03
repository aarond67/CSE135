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

$dateParams = [
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

    // Page loads and sessions for the traffic cards and chart.
    if ($permissions['technology']) {
        $summaryStatement = $pdo->prepare(
            'SELECT
                COUNT(*) AS page_loads,
                COUNT(DISTINCT session_id) AS unique_sessions
             FROM static_data
             WHERE collected_at >= :start
               AND collected_at < :end'
        );

        $summaryStatement->execute($dateParams);

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

        $pageStatement->execute($dateParams);

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

    // Average speed for the card, plus the slowest pages for the grid.
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
            $dateParams
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
            $dateParams
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

    // Follow each session from a product page to checkout and demo success.
    if ($permissions['behavior']) {
        // All steps must happen in the same session and selected dates.
        // Use the first product view, then the first checkout after it.
        // A later checkout reload must not erase an earlier demo success.
        // Each subquery returns one row per session to avoid double-counting.
        // Matching timestamps are not enough to prove which step came first.
        $shoppingStatement = $pdo->prepare(
            "WITH shop_visits AS (
                SELECT
                    session_id,
                    collected_at,
                    CASE WHEN
                        page_url = 'https://test.baddecisions.site/product-detail.html'
                        OR page_url LIKE 'https://test.baddecisions.site/product-detail.html?%'
                        OR page_url LIKE 'https://test.baddecisions.site/product-detail.html#%'
                        THEN 'product'
                    WHEN
                        page_url = 'https://test.baddecisions.site/checkout.html'
                        OR page_url LIKE 'https://test.baddecisions.site/checkout.html?%'
                        OR page_url LIKE 'https://test.baddecisions.site/checkout.html#%'
                        THEN 'checkout'
                    ELSE 'visit' END AS step
                FROM static_data
                WHERE collected_at >= :start
                  AND collected_at < :end
                  AND page_url LIKE 'https://test.baddecisions.site/%'
             ), session_progress AS (
                SELECT
                    session_id,
                    MIN(CASE WHEN step = 'product' THEN collected_at END)
                        AS first_product_at
                FROM shop_visits
                GROUP BY session_id
             ), checkout_progress AS (
                SELECT
                    s.session_id,
                    MIN(v.collected_at) AS first_checkout_at
                FROM session_progress s
                JOIN shop_visits v ON v.session_id = s.session_id
                    AND v.step = 'checkout'
                    AND v.collected_at > s.first_product_at
                GROUP BY s.session_id
             ), demo_successes AS (
                SELECT session_id, MAX(event_time) AS last_demo_success_at
                FROM activity_events
                WHERE event_type = 'demo-order-success'
                  AND event_time >= :success_start
                  AND event_time < :success_end
                  AND (
                    page_url = 'https://test.baddecisions.site/checkout.html'
                    OR page_url LIKE 'https://test.baddecisions.site/checkout.html?%'
                    OR page_url LIKE 'https://test.baddecisions.site/checkout.html#%'
                  )
                GROUP BY session_id
             )
             SELECT
                COUNT(*) AS visited_sessions,
                COUNT(s.first_product_at) AS product_sessions,
                COUNT(c.first_checkout_at) AS checkout_sessions,
                COALESCE(SUM(CASE
                    WHEN d.last_demo_success_at > c.first_checkout_at THEN 1
                    ELSE 0 END), 0) AS demo_success_sessions
             FROM session_progress s
             LEFT JOIN checkout_progress c ON c.session_id = s.session_id
             LEFT JOIN demo_successes d ON d.session_id = s.session_id"
        );

        $shoppingStatement->execute([
            'start' => $dateRange['sql_start'],
            'end' => $dateRange['sql_end_exclusive'],
            'success_start' => $dateRange['sql_start'],
            'success_end' => $dateRange['sql_end_exclusive']
        ]);

        $shopping = $shoppingStatement->fetch() ?: [];

        $response['charts']['shoppingProgress'] = [
            'visitedSessions' => (int) ($shopping['visited_sessions'] ?? 0),
            'productSessions' => (int) ($shopping['product_sessions'] ?? 0),
            'checkoutSessions' => (int) ($shopping['checkout_sessions'] ?? 0),
            'demoSuccessSessions' => (int) ($shopping['demo_success_sessions'] ?? 0)
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
