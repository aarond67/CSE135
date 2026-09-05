<?php

declare(strict_types=1);

function validReportDate(mixed $value): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        new DateTimeZone('UTC')
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (
        $date === false ||
        ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
        $date->format('Y-m-d') !== $value
    ) {
        return null;
    }

    return $date;
}

function getReportDateRange(): array
{
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $start = validReportDate($_GET['start'] ?? $today->modify('-29 days')->format('Y-m-d'));
    $end = validReportDate($_GET['end'] ?? $today->format('Y-m-d'));

    if ($start === null || $end === null || $start > $end) {
        abortRequest(400, 'Invalid Date Range', 'Choose a valid start and end date.');
    }

    if ($start->diff($end)->days > 366) {
        abortRequest(400, 'Invalid Date Range', 'The date range cannot exceed 366 days.');
    }

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'sql_start' => $start->format('Y-m-d H:i:s.v'),
        'sql_end' => $end->modify('+1 day')->format('Y-m-d H:i:s.v')
    ];
}

function reportPagePath(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);
    $label = is_string($path) && $path !== '' ? $path : $url;

    return is_string($query) && $query !== ''
        ? $label . '?' . $query
        : $label;
}

function getTechnicalErrorReportData(array $range): array
{
    $params = ['start' => $range['sql_start'], 'end' => $range['sql_end']];
    $pdo = database();

    $summaryStatement = $pdo->prepare(
        "SELECT
            COUNT(*) AS error_occurrences,
            COUNT(DISTINCT session_id) AS affected_sessions,
            COUNT(DISTINCT page_url) AS affected_pages
         FROM activity_events
         WHERE event_time >= :start
           AND event_time < :end
           AND event_type = 'error'
           AND COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.eventType')),
                'javascript-error'
           ) = 'javascript-error'"
    );
    $summaryStatement->execute($params);

    $pageStatement = $pdo->prepare(
        "SELECT
            page_url,
            COUNT(*) AS error_occurrences,
            COUNT(DISTINCT session_id) AS affected_sessions,
            MIN(event_time) AS first_error,
            MAX(event_time) AS latest_error
         FROM activity_events
         WHERE event_time >= :start
           AND event_time < :end
           AND event_type = 'error'
           AND COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.eventType')),
                'javascript-error'
           ) = 'javascript-error'
         GROUP BY page_url
         ORDER BY affected_sessions DESC, error_occurrences DESC, page_url ASC
         LIMIT 10"
    );
    $pageStatement->execute($params);

    $detailStatement = $pdo->prepare(
        "SELECT
            page_url,
            COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.message')), ''),
                'No message recorded'
            ) AS error_message,
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.filename')), '') AS filename,
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.lineNumber')), '') AS line_number,
            COUNT(*) AS error_occurrences,
            COUNT(DISTINCT session_id) AS affected_sessions,
            MIN(event_time) AS first_error,
            MAX(event_time) AS latest_error
         FROM activity_events
         WHERE event_time >= :start
           AND event_time < :end
           AND event_type = 'error'
           AND COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.eventType')),
                'javascript-error'
           ) = 'javascript-error'
         GROUP BY page_url, error_message, filename, line_number
         ORDER BY affected_sessions DESC, error_occurrences DESC, latest_error DESC
         LIMIT 25"
    );
    $detailStatement->execute($params);

    $summary = $summaryStatement->fetch() ?: [];

    return [
        'summary' => [
            'error_occurrences' => (int) ($summary['error_occurrences'] ?? 0),
            'affected_sessions' => (int) ($summary['affected_sessions'] ?? 0),
            'affected_pages' => (int) ($summary['affected_pages'] ?? 0)
        ],
        'pages' => $pageStatement->fetchAll(),
        'details' => $detailStatement->fetchAll()
    ];
}

function getShoppingProgress(array $range): array
{
    $statement = database()->prepare(
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
                MIN(CASE WHEN step = 'product' THEN collected_at END) AS first_product_at
            FROM shop_visits
            GROUP BY session_id
         ), checkout_progress AS (
            SELECT s.session_id, MIN(v.collected_at) AS first_checkout_at
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

    $statement->execute([
        'start' => $range['sql_start'],
        'end' => $range['sql_end'],
        'success_start' => $range['sql_start'],
        'success_end' => $range['sql_end']
    ]);

    $row = $statement->fetch() ?: [];

    return [
        'visited' => (int) ($row['visited_sessions'] ?? 0),
        'product' => (int) ($row['product_sessions'] ?? 0),
        'checkout' => (int) ($row['checkout_sessions'] ?? 0),
        'success' => (int) ($row['demo_success_sessions'] ?? 0)
    ];
}

function getCheckoutDropoffReportData(array $range): array
{
    $statement = database()->prepare(
        "WITH product_progress AS (
            SELECT session_id, MIN(collected_at) AS first_product_at
            FROM static_data
            WHERE collected_at >= :start
              AND collected_at < :end
              AND (
                page_url = 'https://test.baddecisions.site/product-detail.html'
                OR page_url LIKE 'https://test.baddecisions.site/product-detail.html?%'
                OR page_url LIKE 'https://test.baddecisions.site/product-detail.html#%'
              )
            GROUP BY session_id
         ), checkout_progress AS (
            SELECT p.session_id, MIN(s.collected_at) AS first_checkout_at
            FROM product_progress p
            JOIN static_data s
              ON s.session_id = p.session_id
             AND s.collected_at > p.first_product_at
             AND s.collected_at < :checkout_end
             AND (
                s.page_url = 'https://test.baddecisions.site/checkout.html'
                OR s.page_url LIKE 'https://test.baddecisions.site/checkout.html?%'
                OR s.page_url LIKE 'https://test.baddecisions.site/checkout.html#%'
             )
            GROUP BY p.session_id
         ), checkout_steps AS (
            SELECT
                session_id,
                event_time,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.step')) AS UNSIGNED) AS step_number
            FROM activity_events
            WHERE event_type = 'checkout-step'
              AND event_time >= :event_start
              AND event_time < :event_end
         ), payment_progress AS (
            SELECT c.session_id, MIN(e.event_time) AS first_payment_at
            FROM checkout_progress c
            JOIN checkout_steps e
              ON e.session_id = c.session_id
             AND e.step_number = 2
             AND e.event_time > c.first_checkout_at
            GROUP BY c.session_id
         ), review_progress AS (
            SELECT p.session_id, MIN(e.event_time) AS first_review_at
            FROM payment_progress p
            JOIN checkout_steps e
              ON e.session_id = p.session_id
             AND e.step_number = 3
             AND e.event_time > p.first_payment_at
            GROUP BY p.session_id
         ), demo_successes AS (
            SELECT r.session_id, MIN(a.event_time) AS first_success_at
            FROM review_progress r
            JOIN activity_events a
              ON a.session_id = r.session_id
             AND a.event_type = 'demo-order-success'
             AND a.event_time > r.first_review_at
             AND a.event_time < :success_end
            GROUP BY r.session_id
         )
         SELECT
            COUNT(*) AS product_sessions,
            COUNT(c.first_checkout_at) AS checkout_sessions,
            COUNT(p.first_payment_at) AS payment_sessions,
            COUNT(r.first_review_at) AS review_sessions,
            COUNT(d.first_success_at) AS success_sessions
         FROM product_progress products
         LEFT JOIN checkout_progress c ON c.session_id = products.session_id
         LEFT JOIN payment_progress p ON p.session_id = products.session_id
         LEFT JOIN review_progress r ON r.session_id = products.session_id
         LEFT JOIN demo_successes d ON d.session_id = products.session_id"
    );

    $statement->execute([
        'start' => $range['sql_start'],
        'end' => $range['sql_end'],
        'checkout_end' => $range['sql_end'],
        'event_start' => $range['sql_start'],
        'event_end' => $range['sql_end'],
        'success_end' => $range['sql_end']
    ]);

    $row = $statement->fetch() ?: [];
    $counts = [
        'product' => (int) ($row['product_sessions'] ?? 0),
        'checkout' => (int) ($row['checkout_sessions'] ?? 0),
        'payment' => (int) ($row['payment_sessions'] ?? 0),
        'review' => (int) ($row['review_sessions'] ?? 0),
        'success' => (int) ($row['success_sessions'] ?? 0)
    ];
    $labels = [
        'product' => 'Viewed a product',
        'checkout' => 'Opened checkout',
        'payment' => 'Reached payment',
        'review' => 'Reached review',
        'success' => 'Demo success shown'
    ];
    $stages = [];
    $previous = null;
    $previousLabel = null;
    $largestDrop = null;

    foreach ($labels as $key => $label) {
        $count = $counts[$key];
        $drop = $previous === null ? null : max($previous - $count, 0);
        $continued = $previous === null || $previous === 0
            ? null
            : round($count / $previous * 100, 1);

        $stage = [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'drop' => $drop,
            'continued_rate' => $continued,
            'previous_label' => $previousLabel
        ];
        $stages[] = $stage;

        if ($drop !== null && ($largestDrop === null || $drop > $largestDrop['drop'])) {
            $largestDrop = $stage;
        }

        $previous = $count;
        $previousLabel = $label;
    }

    return [
        'counts' => $counts,
        'stages' => $stages,
        'largest_drop' => $largestDrop
    ];
}

function getPageEngagementReportData(array $range): array
{
    $statement = database()->prepare(
        "WITH page_sessions AS (
            SELECT
                page_url,
                COUNT(*) AS page_loads,
                COUNT(DISTINCT session_id) AS page_sessions
            FROM static_data
            WHERE collected_at >= :start
              AND collected_at < :end
            GROUP BY page_url
         ), interactions AS (
            SELECT
                page_url,
                COUNT(DISTINCT CASE
                    WHEN event_type IN ('click', 'scroll', 'keydown')
                    THEN session_id END
                ) AS engaged_sessions,
                SUM(event_type = 'click') AS clicks,
                SUM(event_type = 'scroll') AS scrolls,
                SUM(event_type = 'keydown') AS keydowns
            FROM activity_events
            WHERE event_time >= :event_start
              AND event_time < :event_end
              AND event_type IN ('click', 'scroll', 'keydown')
            GROUP BY page_url
         )
         SELECT
            p.page_url,
            p.page_loads,
            p.page_sessions,
            LEAST(COALESCE(i.engaged_sessions, 0), p.page_sessions) AS engaged_sessions,
            ROUND(
                LEAST(COALESCE(i.engaged_sessions, 0), p.page_sessions)
                / NULLIF(p.page_sessions, 0) * 100,
                1
            ) AS engagement_rate,
            COALESCE(i.clicks, 0) AS clicks,
            COALESCE(i.scrolls, 0) AS scrolls,
            COALESCE(i.keydowns, 0) AS keydowns
         FROM page_sessions p
         LEFT JOIN interactions i ON i.page_url = p.page_url
         ORDER BY engagement_rate DESC, p.page_sessions DESC, p.page_url ASC"
    );
    $statement->execute([
        'start' => $range['sql_start'],
        'end' => $range['sql_end'],
        'event_start' => $range['sql_start'],
        'event_end' => $range['sql_end']
    ]);

    $pages = $statement->fetchAll();
    $pageSessions = 0;
    $engagedSessions = 0;

    foreach ($pages as $page) {
        $pageSessions += (int) $page['page_sessions'];
        $engagedSessions += (int) $page['engaged_sessions'];
    }

    return [
        'summary' => [
            'page_sessions' => $pageSessions,
            'engaged_page_sessions' => $engagedSessions,
            'engagement_rate' => $pageSessions > 0
                ? round($engagedSessions / $pageSessions * 100, 1)
                : 0.0
        ],
        'pages' => $pages
    ];
}

function performanceBudgetMilliseconds(): int
{
    return 3000;
}

function getPerformanceBudgetData(array $range): array
{
    $statement = database()->prepare(
        'WITH ranked_loads AS (
            SELECT
                page_url,
                total_load_time_ms,
                ROW_NUMBER() OVER (
                    PARTITION BY page_url
                    ORDER BY total_load_time_ms
                ) AS load_rank,
                COUNT(*) OVER (PARTITION BY page_url) AS measurement_count
            FROM performance_data
            WHERE collected_at >= :start
              AND collected_at < :end
              AND total_load_time_ms IS NOT NULL
              AND total_load_time_ms >= 0
         )
         SELECT
            page_url,
            MAX(measurement_count) AS measurements,
            ROUND(AVG(total_load_time_ms), 2) AS average_ms,
            ROUND(MAX(CASE
                WHEN load_rank = CEIL(measurement_count * 0.75)
                THEN total_load_time_ms END
            ), 2) AS p75_ms,
            ROUND(MAX(total_load_time_ms), 2) AS slowest_ms
         FROM ranked_loads
         GROUP BY page_url
         ORDER BY p75_ms DESC, page_url ASC'
    );
    $statement->execute(['start' => $range['sql_start'], 'end' => $range['sql_end']]);

    $pages = $statement->fetchAll();
    $budget = performanceBudgetMilliseconds();
    $withinBudget = 0;

    foreach ($pages as &$page) {
        $page['within_budget'] = (float) $page['p75_ms'] <= $budget;

        if ($page['within_budget']) {
            $withinBudget++;
        }
    }
    unset($page);

    return [
        'budget_ms' => $budget,
        'pages_within_budget' => $withinBudget,
        'pages_over_budget' => count($pages) - $withinBudget,
        'pages' => $pages
    ];
}

function getPerformanceExportData(array $range): array
{
    return getPerformanceBudgetData($range)['pages'];
}
