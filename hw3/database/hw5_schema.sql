USE cse135_analytics;

INSERT INTO saved_reports (
    report_key,
    title,
    category,
    guiding_question,
    analyst_comments,
    created_by,
    is_published
) VALUES
(
    'technology-overview',
    'Technical Errors',
    'technology',
    'Which JavaScript errors affect the most sessions and should be fixed first?',
    NULL,
    NULL,
    FALSE
),
(
    'performance-overview',
    'Performance Budget',
    'performance',
    'Which pages exceed the 3-second load-time budget, and where should performance work be focused?',
    NULL,
    NULL,
    FALSE
),
(
    'behavior-overview',
    'Page Engagement',
    'behavior',
    'Which pages have the strongest meaningful interaction rate, and which pages need a closer look?',
    NULL,
    NULL,
    FALSE
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    category = VALUES(category),
    guiding_question = VALUES(guiding_question);

GRANT SELECT
ON cse135_analytics.static_data
TO 'cse135_reporting'@'localhost';

GRANT SELECT
ON cse135_analytics.performance_data
TO 'cse135_reporting'@'localhost';

GRANT SELECT
ON cse135_analytics.activity_events
TO 'cse135_reporting'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON cse135_analytics.saved_reports
TO 'cse135_reporting'@'localhost';
