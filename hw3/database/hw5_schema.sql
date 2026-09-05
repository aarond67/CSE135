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
    'Visitor Technology',
    'technology',
    'Which browsers, screen sizes, and network conditions should we support first?',
    NULL,
    NULL,
    FALSE
),
(
    'performance-overview',
    'Website Performance',
    'performance',
    'Which pages are loading slowly, and where should performance work be focused?',
    NULL,
    NULL,
    FALSE
),
(
    'behavior-overview',
    'Shopping Behavior',
    'behavior',
    'How far are sessions moving through the shopping flow, and where should we investigate?',
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
