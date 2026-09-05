<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

[$user, $report, $savedReport] = requireSavedReportAccess('technology-overview');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    saveReportFromPost($user, $report, '/reports/errors.php');
}

$range = getReportDateRange();
$data = getTechnicalErrorReportData($range);
$summary = $data['summary'];
$canEdit = canEditReport($user, $report['category']);
$messages = getFlashMessages();
$sessionCounts = array_map(
    static fn (array $row): int => (int) $row['affected_sessions'],
    $data['pages']
);
$chartMaximum = max($sessionCounts === [] ? [1] : $sessionCounts);
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
    <title>Technical Errors | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=report-focus-1">
</head>
<body>
    <header class="topbar">
        <div><p class="eyebrow">Bad Decisions Analytics</p><strong>Technical Errors</strong></div>
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
                <p class="eyebrow">Technical errors</p>
                <h1>JavaScript errors</h1>
                <p class="muted"><?= escape($report['guiding_question']) ?></p>
            </div>
            <form method="get" class="date-filter">
                <div class="form-group"><label for="errors-start">Start date</label><input id="errors-start" type="date" name="start" value="<?= escape($range['start']) ?>" required></div>
                <div class="form-group"><label for="errors-end">End date</label><input id="errors-end" type="date" name="end" value="<?= escape($range['end']) ?>" required></div>
                <button type="submit" class="button button-primary">Apply filter</button>
            </form>
        </section>

        <p class="status-message status-success">Showing <?= escape($range['start']) ?> through <?= escape($range['end']) ?> (UTC).</p>

        <section class="summary-grid" aria-label="Technical error summary">
            <article class="metric-card"><span class="metric-label">Error occurrences</span><strong><?= number_format($summary['error_occurrences']) ?></strong><small>Every recorded JavaScript error</small></article>
            <article class="metric-card"><span class="metric-label">Affected sessions</span><strong><?= number_format($summary['affected_sessions']) ?></strong><small>Distinct sessions with an error</small></article>
            <article class="metric-card"><span class="metric-label">Affected pages</span><strong><?= number_format($summary['affected_pages']) ?></strong><small>Distinct URLs recording errors</small></article>
        </section>

        <section class="panel report-panel">
            <p class="eyebrow">Impact by page</p>
            <h2>Sessions affected by JavaScript errors</h2>
            <p class="muted">The bars count distinct sessions, while the value on the right also shows total occurrences. Repeated errors in one session do not make the affected-session bar larger.</p>
            <div class="horizontal-chart" aria-label="Affected sessions and JavaScript error occurrences by page">
                <?php if ($data['pages'] === []): ?>
                    <p class="empty-state">No JavaScript errors were recorded in this period.</p>
                <?php else: ?>
                    <?php foreach ($data['pages'] as $row): ?>
                        <?php $width = (int) $row['affected_sessions'] / $chartMaximum * 100; ?>
                        <div class="chart-row technical-error-row">
                            <span class="chart-label" title="<?= escape($row['page_url']) ?>"><?= escape(reportPagePath($row['page_url'])) ?></span>
                            <div class="chart-track" aria-hidden="true"><div class="chart-bar chart-bar-errors" style="width: <?= number_format($width, 2, '.', '') ?>%"></div></div>
                            <strong class="chart-value"><?= (int) $row['affected_sessions'] ?> sessions<br><small><?= (int) $row['error_occurrences'] ?> errors</small></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Error details</p>
            <h2>Problems to reproduce and fix</h2>
            <p class="muted">Rows are grouped by page, message, file, and line. Start with errors that affect more sessions, then use occurrences and the latest time to reproduce them.</p>
            <div class="table-wrapper" tabindex="0" role="region" aria-label="JavaScript error details; scroll horizontally if needed">
                <table class="data-table">
                    <thead><tr><th>Page</th><th>Error message</th><th>Location</th><th>Occurrences</th><th>Affected sessions</th><th>Latest</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['details'] as $row): ?>
                            <?php
                            $location = reportPagePath((string) ($row['filename'] ?? ''));
                            if (($row['line_number'] ?? '') !== '') {
                                $location .= ':' . $row['line_number'];
                            }
                            ?>
                            <tr>
                                <th scope="row" class="url-cell" title="<?= escape($row['page_url']) ?>"><?= escape(reportPagePath($row['page_url'])) ?></th>
                                <td class="error-message-cell"><?= escape($row['error_message']) ?></td>
                                <td class="url-cell"><?= escape($location !== '' ? $location : 'Not recorded') ?></td>
                                <td><?= (int) $row['error_occurrences'] ?></td>
                                <td><?= (int) $row['affected_sessions'] ?></td>
                                <td><?= escape($row['latest_error']) ?> UTC</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($data['details'] === []): ?><tr><td colspan="6">No JavaScript errors were recorded in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel analyst-panel">
            <p class="eyebrow">Analyst comments</p><h2>What should be fixed first?</h2>
            <?php if ($canEdit): ?>
                <form method="post" class="comments-form">
                    <?= csrfInput() ?>
                    <label for="analyst-comments">Technical error analysis</label>
                    <textarea id="analyst-comments" name="analyst_comments" rows="8" maxlength="5000" placeholder="Explain which error should be reproduced first, how many sessions it affected, and what you would check next."><?= escape($savedReport['analyst_comments'] ?? '') ?></textarea>
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
