<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = requireSectionAccess('performance');

$reportKey = 'performance-overview';

$guidingQuestion =
    'Which pages are loading slowly, and where should performance work be focused?';

$requestMethod =
    $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    requireValidCsrfToken();

    $analystComments =
        $_POST['analyst_comments'] ?? '';

    if (!is_string($analystComments)) {
        $analystComments = '';
    }

    $analystComments = trim($analystComments);

    if (strlen($analystComments) > 5000) {
        setFlashMessage(
            'error',
            'Analyst comments cannot exceed 5,000 characters.'
        );

        redirect('/reports/performance.php');
    }

    try {
        $pdo = database();

        $existingStatement = $pdo->prepare(
            'SELECT id
             FROM saved_reports
             WHERE report_key = :report_key
             LIMIT 1'
        );

        $existingStatement->execute([
            'report_key' => $reportKey
        ]);

        $existingReportId =
            $existingStatement->fetchColumn();

        if ($existingReportId !== false) {
            $updateStatement = $pdo->prepare(
                'UPDATE saved_reports
                 SET title = :title,
                     category = :category,
                     guiding_question = :guiding_question,
                     analyst_comments = :analyst_comments,
                     created_by = :created_by
                 WHERE id = :id'
            );

            $updateStatement->execute([
                'title' => 'Website Performance Overview',
                'category' => 'performance',
                'guiding_question' => $guidingQuestion,
                'analyst_comments' => $analystComments,
                'created_by' => $user['id'],
                'id' => (int) $existingReportId
            ]);
        } else {
            $insertStatement = $pdo->prepare(
                'INSERT INTO saved_reports (
                    report_key,
                    title,
                    category,
                    guiding_question,
                    analyst_comments,
                    created_by,
                    is_published
                 ) VALUES (
                    :report_key,
                    :title,
                    :category,
                    :guiding_question,
                    :analyst_comments,
                    :created_by,
                    FALSE
                 )'
            );

            $insertStatement->execute([
                'report_key' => $reportKey,
                'title' => 'Website Performance Overview',
                'category' => 'performance',
                'guiding_question' => $guidingQuestion,
                'analyst_comments' => $analystComments,
                'created_by' => $user['id']
            ]);
        }

        setFlashMessage(
            'success',
            'Your performance report comments were saved.'
        );
    } catch (Throwable $error) {
        error_log(
            '[CSE135 Performance Report] ' .
            $error->getMessage()
        );

        setFlashMessage(
            'error',
            'The analyst comments could not be saved.'
        );
    }

    redirect('/reports/performance.php');
}

$reportStatement = database()->prepare(
    'SELECT
        analyst_comments,
        is_published,
        updated_at
     FROM saved_reports
     WHERE report_key = :report_key
     LIMIT 1'
);

$reportStatement->execute([
    'report_key' => $reportKey
]);

$savedReport =
    $reportStatement->fetch() ?: [];

$analystComments =
    $savedReport['analyst_comments'] ?? '';

$messages = consumeFlashMessages();

$roleName = ucwords(
    str_replace('_', ' ', $user['role'])
);

$timezone = new DateTimeZone('UTC');
$today = new DateTimeImmutable('today', $timezone);

$defaultEnd =
    $today->format('Y-m-d');

$defaultStart =
    $today->modify('-29 days')->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Performance Report | Bad Decisions Analytics
    </title>

    <link rel="stylesheet" href="/assets/css/app.css?v=performance-switch-1">

    <script
        src="/assets/js/performance-report.js?v=performance-switch-1"
        defer
    ></script>
</head>
<body>
    <header class="topbar">
        <div>
            <p class="eyebrow">Bad Decisions Analytics</p>
            <strong>Performance Report</strong>
        </div>

        <div class="user-controls">
            <div>
                <strong><?= escape($user['username']) ?></strong>

                <span class="role-badge">
                    <?= escape($roleName) ?>
                </span>
            </div>

            <a
                href="/dashboard.php"
                class="button button-secondary"
            >
                Back to dashboard
            </a>

            <form method="post" action="/logout.php">
                <?= csrfInput() ?>

                <button
                    type="submit"
                    class="button button-secondary"
                >
                    Sign out
                </button>
            </form>
        </div>
    </header>

    <main class="dashboard-content">
        <?php foreach ($messages as $message): ?>
            <?php
            $messageType =
                ($message['type'] ?? '') === 'error'
                    ? 'error'
                    : 'success';
            ?>

            <div class="alert alert-<?= escape($messageType) ?>">
                <?= escape($message['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>

        <section class="dashboard-heading">
            <div>
                <p class="eyebrow">Detailed report</p>
                <h1>Website performance</h1>

                <p class="muted">
                    Compare page-loading measurements and find pages
                    that may need performance improvements.
                </p>
            </div>

            <form
                id="performance-filter"
                class="date-filter"
                data-default-start="<?= escape($defaultStart) ?>"
                data-default-end="<?= escape($defaultEnd) ?>"
            >
                <div class="form-group">
                    <label for="performance-start">
                        Start date
                    </label>

                    <input
                        type="date"
                        id="performance-start"
                        value="<?= escape($defaultStart) ?>"
                        max="<?= escape($defaultEnd) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="performance-end">
                        End date
                    </label>

                    <input
                        type="date"
                        id="performance-end"
                        value="<?= escape($defaultEnd) ?>"
                        max="<?= escape($defaultEnd) ?>"
                        required
                    >
                </div>

                <button
                    type="submit"
                    id="performance-apply"
                    class="button button-primary"
                >
                    Apply filter
                </button>

                <button
                    type="button"
                    id="performance-reset"
                    class="button button-secondary"
                >
                    Last 30 days
                </button>
            </form>
        </section>

        <p
            id="performance-status"
            class="status-message"
            role="status"
            aria-live="polite"
        >
            Loading performance data...
        </p>

        <noscript>
            <div class="alert alert-error">
                JavaScript must be enabled to display this report.
            </div>
        </noscript>

        <section
            class="summary-grid performance-summary-grid"
            aria-label="Performance summary"
        >
            <article class="metric-card">
                <span class="metric-label">Measurements</span>
                <strong id="performance-measurements">—</strong>
                <small>Performance records</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Average load</span>
                <strong id="performance-average">—</strong>
                <small>Average page-load time</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Fastest load</span>
                <strong id="performance-fastest">—</strong>
                <small>Fastest measurement</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Slowest load</span>
                <strong id="performance-slowest">—</strong>
                <small>Slowest measurement</small>
            </article>
        </section>

        <section class="panel report-panel">
            <div class="performance-chart-heading">
                <div>
                    <p class="eyebrow">Investigate page loading</p>
                    <h2 id="performance-chart-title">Individual load times</h2>
                </div>

                <div class="performance-chart-switch" role="group" aria-label="Chart view">
                    <button
                        type="button"
                        id="performance-view-dots"
                        aria-pressed="true"
                        aria-controls="performance-page-chart"
                        disabled
                    >Individual loads</button>
                    <button
                        type="button"
                        id="performance-view-stages"
                        aria-pressed="false"
                        aria-controls="performance-page-chart"
                        disabled
                    >Loading stages</button>
                </div>
            </div>

            <p class="muted" id="performance-chart-description">
                Each dot is one measured page load. Select a dot to inspect it.
            </p>

            <p class="performance-chart-note" id="performance-chart-note"></p>

            <div
                id="performance-page-chart"
                class="performance-chart"
                aria-labelledby="performance-chart-title"
                aria-describedby="performance-chart-description performance-chart-note"
                aria-busy="true"
            ></div>

            <p
                id="performance-chart-detail"
                class="performance-chart-detail"
                role="status"
                aria-live="polite"
                hidden
            ></p>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Detailed measurements</p>
            <h2>Performance records</h2>

            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="performance-table-caption">
                        Latest 100 measurements at most; summary cards cover the full date range.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Collected</th>
                            <th scope="col">Page</th>
                            <th scope="col">Load time</th>
                            <th scope="col">Session</th>
                        </tr>
                    </thead>

                    <tbody id="performance-records">
                        <tr>
                            <td colspan="4">
                                Loading performance records...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel analyst-panel">
            <p class="eyebrow">Analyst comments</p>
            <h2>What does this data mean?</h2>

            <p>
                <strong>Guiding question:</strong>
                <?= escape($guidingQuestion) ?>
            </p>

            <form
                method="post"
                action="/reports/performance.php"
                class="comments-form"
            >
                <?= csrfInput() ?>

                <label for="analyst-comments">
                    Performance analysis
                </label>

                <textarea
                    id="analyst-comments"
                    name="analyst_comments"
                    rows="8"
                    maxlength="5000"
                    placeholder="Explain what the performance data shows and which pages should be improved first."
                ><?= escape($analystComments) ?></textarea>

                <div class="comments-actions">
                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        Save comments
                    </button>

                    <span class="muted">
                        This report is currently
                        <?= !empty($savedReport['is_published'])
                            ? 'published'
                            : 'not published' ?>.
                    </span>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
