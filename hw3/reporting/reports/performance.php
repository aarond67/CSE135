<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

[$user, $report, $savedReport] =
    requireSavedReportAccess('performance-overview');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    saveReportFromPost($user, $report, '/reports/performance.php');
}

$analystComments = $savedReport['analyst_comments'] ?? '';
$canEdit = canEditReport($user, $report['category']);

$messages = getFlashMessages();

$roleName = displayUserRole($user['role']);

$timezone = new DateTimeZone('UTC');
$today = new DateTimeImmutable('today', $timezone);

$defaultEnd =
    $today->format('Y-m-d');

$defaultStart =
    $today->modify('-29 days')->format('Y-m-d');

$exportQuery = http_build_query([
    'key' => $report['report_key'],
    'start' => $defaultStart,
    'end' => $defaultEnd
]);
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
        Performance Budget | Bad Decisions Analytics
    </title>

    <link rel="stylesheet" href="/assets/css/app.css?v=report-focus-1">

    <script
        src="/assets/js/performance-report.js?v=performance-budget-1"
        defer
    ></script>
</head>
<body>
    <header class="topbar">
        <div>
            <p class="eyebrow">Bad Decisions Analytics</p>
            <strong>Performance Budget</strong>
        </div>

        <div class="user-controls">
            <div>
                <strong><?= escape($user['username']) ?></strong>

                <span class="role-badge">
                    <?= escape($roleName) ?>
                </span>
            </div>

            <a
                href="/reports/index.php"
                class="button button-secondary"
            >
                All reports
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
                <p class="eyebrow">Performance budget</p>
                <h1>Page-load budget</h1>

                <p class="muted">
                    Compare each page's 75th-percentile load time with
                    a 3-second budget, then inspect slow measurements.
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
                <span class="metric-label">Pages within budget</span>
                <strong id="performance-within-budget">—</strong>
                <small>Page p75 at or below 3 seconds</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Pages over budget</span>
                <strong id="performance-over-budget">—</strong>
                <small>Page p75 above 3 seconds</small>
            </article>
        </section>

        <section class="panel report-panel">
            <p class="eyebrow">Budget check</p>
            <h2 id="performance-budget-title">75th-percentile load time by page</h2>
            <p class="muted" id="performance-budget-description">
                The p75 value is the load time that 75% of measurements meet or beat.
                It is compared with the same 3,000 ms budget for every page.
            </p>
            <div
                id="performance-budget-chart"
                class="performance-budget-chart"
                aria-labelledby="performance-budget-title"
                aria-describedby="performance-budget-description"
                aria-busy="true"
            ></div>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Exact budget results</p>
            <h2 id="performance-budget-table-title">Pages compared with the budget</h2>
            <div
                id="performance-budget-table"
                aria-labelledby="performance-budget-table-title"
                aria-busy="true"
            >
                <p class="empty-state">Loading budget results...</p>
            </div>
        </section>

        <section class="panel report-panel">
            <div class="performance-chart-heading">
                <div>
                    <p class="eyebrow">Investigate budget misses</p>
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
            <p class="eyebrow">Compare pages</p>
            <h2 id="performance-stage-values-title">Loading-stage summary</h2>

            <div
                id="performance-stage-values"
                aria-labelledby="performance-stage-values-title"
                aria-busy="true"
            >
                <p class="empty-state">Loading stage summary...</p>
            </div>
        </section>

        <section class="panel analyst-panel">
            <p class="eyebrow">Analyst comments</p>
            <h2>What should be improved first?</h2>

            <p>
                <strong>Guiding question:</strong>
                <?= escape($report['guiding_question']) ?>
            </p>

            <?php if ($canEdit): ?>
                <form method="post" action="/reports/performance.php" class="comments-form">
                    <?= csrfInput() ?>

                    <label for="analyst-comments">Performance analysis</label>
                    <textarea
                        id="analyst-comments"
                        name="analyst_comments"
                        rows="8"
                        maxlength="5000"
                        placeholder="Explain which pages miss the budget, whether the sample is large enough, and which loading stage you would investigate next."
                    ><?= escape($analystComments) ?></textarea>

                    <label class="checkbox-label publish-control">
                        <input
                            type="checkbox"
                            name="is_published"
                            value="1"
                            <?= $savedReport['is_published'] ? 'checked' : '' ?>
                        >
                        Publish this report for viewer accounts
                    </label>

                    <div class="comments-actions">
                        <button type="submit" class="button button-primary">Save report</button>
                        <a id="performance-export" class="button button-secondary" href="/exports/report.php?<?= escape($exportQuery) ?>">
                            Download PDF
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="published-comments">
                    <?= $analystComments !== ''
                        ? nl2br(escape($analystComments))
                        : '<p>No analyst comments were saved with this report.</p>' ?>
                </div>
                <a id="performance-export" class="button button-primary" href="/exports/report.php?<?= escape($exportQuery) ?>">
                    Download PDF
                </a>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
