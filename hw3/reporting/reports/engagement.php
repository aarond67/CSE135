<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

[$user, $report, $savedReport] = requireSavedReportAccess('behavior-overview');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    saveReportFromPost($user, $report, '/reports/engagement.php');
}

$range = getReportDateRange();
$data = getPageEngagementReportData($range);
$summary = $data['summary'];
$canEdit = canEditReport($user, $report['category']);
$messages = getFlashMessages();
$exportQuery = http_build_query([
    'key' => $report['report_key'],
    'start' => $range['start'],
    'end' => $range['end']
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Engagement | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=report-focus-1">
</head>
<body>
    <header class="topbar">
        <div><p class="eyebrow">Bad Decisions Analytics</p><strong>Page Engagement</strong></div>
        <div class="user-controls">
            <div><strong><?= escape($user['username']) ?></strong><span class="role-badge"><?= escape(displayUserRole($user['role'])) ?></span></div>
            <a href="/reports/index.php" class="button button-secondary">All reports</a>
            <form method="post" action="/logout.php"><?= csrfInput() ?><button type="submit" class="button button-secondary">Sign out</button></form>
        </div>
    </header>

    <main class="dashboard-content">
        <?php foreach ($messages as $message): ?>
            <?php $type = ($message['type'] ?? '') === 'error' ? 'error' : 'success'; ?>
            <div class="alert alert-<?= escape($type) ?>"><?= escape($message['message'] ?? '') ?></div>
        <?php endforeach; ?>

        <section class="dashboard-heading">
            <div>
                <p class="eyebrow">Page engagement</p>
                <h1>Meaningful activity by page</h1>
                <p class="muted"><?= escape($report['guiding_question']) ?></p>
            </div>
            <form method="get" class="date-filter">
                <div class="form-group"><label for="engagement-start">Start date</label><input id="engagement-start" type="date" name="start" value="<?= escape($range['start']) ?>" required></div>
                <div class="form-group"><label for="engagement-end">End date</label><input id="engagement-end" type="date" name="end" value="<?= escape($range['end']) ?>" required></div>
                <button type="submit" class="button button-primary">Apply filter</button>
            </form>
        </section>

        <p class="status-message status-success">Showing <?= escape($range['start']) ?> through <?= escape($range['end']) ?> (UTC).</p>

        <section class="summary-grid" aria-label="Page engagement summary">
            <article class="metric-card"><span class="metric-label">Page sessions</span><strong><?= number_format($summary['page_sessions']) ?></strong><small>Distinct session-and-page pairs</small></article>
            <article class="metric-card"><span class="metric-label">Engaged page sessions</span><strong><?= number_format($summary['engaged_page_sessions']) ?></strong><small>Pairs with a click, scroll, or key press</small></article>
            <article class="metric-card"><span class="metric-label">Engagement rate</span><strong><?= number_format($summary['engagement_rate'], 1) ?>%</strong><small>Engaged page sessions divided by page sessions</small></article>
        </section>

        <section class="panel report-panel">
            <p class="eyebrow">Compare pages</p>
            <h2>Engaged-session rate by page</h2>
            <p class="muted">A page session counts as engaged when it includes at least one click, scroll, or key press. Mouse movement is excluded. A short page does not need a scroll to count as engaged.</p>
            <div class="horizontal-chart engagement-chart" aria-label="Engaged page-session rate by page">
                <?php if ($data['pages'] === []): ?>
                    <p class="empty-state">No page sessions were recorded in this period.</p>
                <?php else: ?>
                    <?php foreach (array_slice($data['pages'], 0, 10) as $row): ?>
                        <?php $rate = (float) $row['engagement_rate']; ?>
                        <div class="chart-row">
                            <span class="chart-label" title="<?= escape($row['page_url']) ?>"><?= escape(reportPagePath($row['page_url'])) ?></span>
                            <div class="chart-track" aria-hidden="true"><div class="chart-bar chart-bar-engagement" style="width: <?= number_format($rate, 1, '.', '') ?>%"></div></div>
                            <strong class="chart-value"><?= number_format($rate, 1) ?>%</strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Exact values</p>
            <h2>Page engagement details</h2>
            <p class="muted">Use the session rate with the sample size. Event totals explain what happened, but they are not counts of different people.</p>
            <div class="table-wrapper" tabindex="0" role="region" aria-label="Page engagement details; scroll horizontally if needed">
                <table class="data-table">
                    <thead><tr><th>Page</th><th>Page sessions</th><th>Engaged sessions</th><th>Rate</th><th>Clicks</th><th>Scrolls</th><th>Key presses</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['pages'] as $row): ?>
                            <tr>
                                <th scope="row" class="url-cell" title="<?= escape($row['page_url']) ?>"><?= escape(reportPagePath($row['page_url'])) ?></th>
                                <td><?= (int) $row['page_sessions'] ?></td>
                                <td><?= (int) $row['engaged_sessions'] ?></td>
                                <td><?= number_format((float) $row['engagement_rate'], 1) ?>%</td>
                                <td><?= (int) $row['clicks'] ?></td>
                                <td><?= (int) $row['scrolls'] ?></td>
                                <td><?= (int) $row['keydowns'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($data['pages'] === []): ?><tr><td colspan="7">No page sessions were recorded in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel analyst-panel">
            <p class="eyebrow">Analyst comments</p><h2>Which pages need a closer look?</h2>
            <?php if ($canEdit): ?>
                <form method="post" class="comments-form">
                    <?= csrfInput() ?>
                    <label for="analyst-comments">Page engagement analysis</label>
                    <textarea id="analyst-comments" name="analyst_comments" rows="8" maxlength="5000" placeholder="Compare engagement rates with their sample sizes and explain which page you would investigate next."><?= escape($savedReport['analyst_comments'] ?? '') ?></textarea>
                    <label class="checkbox-label publish-control"><input type="checkbox" name="is_published" value="1" <?= $savedReport['is_published'] ? 'checked' : '' ?>>Publish this report for viewer accounts</label>
                    <div class="comments-actions"><button type="submit" class="button button-primary">Save report</button><a class="button button-secondary" href="/exports/report.php?<?= escape($exportQuery) ?>">Download PDF</a></div>
                </form>
            <?php else: ?>
                <div class="published-comments"><?= ($savedReport['analyst_comments'] ?? '') !== '' ? nl2br(escape($savedReport['analyst_comments'])) : '<p>No analyst comments were saved with this report.</p>' ?></div>
                <a class="button button-primary" href="/exports/report.php?<?= escape($exportQuery) ?>">Download PDF</a>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
