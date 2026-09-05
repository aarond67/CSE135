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

function getTechnologyReportData(array $range): array
{
    $params = ['start' => $range['sql_start'], 'end' => $range['sql_end']];
    $pdo = database();

    $browserStatement = $pdo->prepare(
        "SELECT
            CASE
                WHEN user_agent LIKE '%Edg/%' THEN 'Edge'
                WHEN user_agent LIKE '%Chrome/%' THEN 'Chrome'
                WHEN user_agent LIKE '%Firefox/%' THEN 'Firefox'
                WHEN user_agent LIKE '%Safari/%' THEN 'Safari'
                ELSE 'Other or unknown'
            END AS browser,
            COUNT(*) AS page_loads,
            COUNT(DISTINCT session_id) AS sessions
         FROM static_data
         WHERE collected_at >= :start AND collected_at < :end
         GROUP BY browser
         ORDER BY page_loads DESC, browser ASC"
    );
    $browserStatement->execute($params);

    $deviceStatement = $pdo->prepare(
        "SELECT
            CASE
                WHEN screen_width IS NULL THEN 'Unknown'
                WHEN screen_width <= 767 THEN 'Phone-sized'
                WHEN screen_width <= 1023 THEN 'Tablet-sized'
                ELSE 'Desktop-sized'
            END AS screen_group,
            COUNT(*) AS page_loads,
            COUNT(DISTINCT session_id) AS sessions,
            ROUND(AVG(screen_width), 0) AS average_screen_width,
            ROUND(AVG(window_width), 0) AS average_window_width
         FROM static_data
         WHERE collected_at >= :start AND collected_at < :end
         GROUP BY screen_group
         ORDER BY page_loads DESC, screen_group ASC"
    );
    $deviceStatement->execute($params);

    $networkStatement = $pdo->prepare(
        "SELECT
            COALESCE(NULLIF(effective_type, ''), 'Unknown') AS connection_type,
            COUNT(*) AS page_loads
         FROM static_data
         WHERE collected_at >= :start AND collected_at < :end
         GROUP BY connection_type
         ORDER BY page_loads DESC, connection_type ASC"
    );
    $networkStatement->execute($params);

    return [
        'browsers' => $browserStatement->fetchAll(),
        'devices' => $deviceStatement->fetchAll(),
        'networks' => $networkStatement->fetchAll()
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

function getBehaviorReportData(array $range): array
{
    $errorStatement = database()->prepare(
        "SELECT
            page_url,
            COUNT(*) AS error_count,
            MAX(event_time) AS latest_error,
            MAX(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.message')), '')) AS example_message
         FROM activity_events
         WHERE event_time >= :start
           AND event_time < :end
           AND event_type = 'error'
         GROUP BY page_url
         ORDER BY error_count DESC, page_url ASC
         LIMIT 10"
    );
    $errorStatement->execute(['start' => $range['sql_start'], 'end' => $range['sql_end']]);

    return [
        'progress' => getShoppingProgress($range),
        'errors' => $errorStatement->fetchAll()
    ];
}

function getPerformanceExportData(array $range): array
{
    $statement = database()->prepare(
        'SELECT
            page_url,
            COUNT(*) AS measurements,
            ROUND(AVG(total_load_time_ms), 2) AS average_ms,
            ROUND(MAX(total_load_time_ms), 2) AS slowest_ms
         FROM performance_data
         WHERE collected_at >= :start
           AND collected_at < :end
           AND total_load_time_ms IS NOT NULL
           AND total_load_time_ms >= 0
         GROUP BY page_url
         ORDER BY average_ms DESC, page_url ASC
         LIMIT 10'
    );
    $statement->execute(['start' => $range['sql_start'], 'end' => $range['sql_end']]);

    return $statement->fetchAll();
}
