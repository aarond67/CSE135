<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

[$user, $report, $savedReport] = requireSavedReportAccess('behavior-overview');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    saveReportFromPost($user, $report, '/reports/behavior.php');
}

$range = getReportDateRange();
$data = getBehaviorReportData($range);
$progress = $data['progress'];
$canEdit = canEditReport($user, $report['category']);
$messages = getFlashMessages();
$maximum = max($progress['visited'], 1);
$steps = [
    ['Visited site', $progress['visited']],
    ['Viewed a product', $progress['product']],
    ['Reached checkout afterward', $progress['checkout']],
    ['Demo success shown afterward', $progress['success']]
];
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
    <title>Behavior Report | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=hw5-1">
</head>
<body>
    <header class="topbar">
        <div><p class="eyebrow">Bad Decisions Analytics</p><strong>Behavior Report</strong></div>
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
            <div><p class="eyebrow">Behavior</p><h1>Shopping behavior</h1><p class="muted"><?= escape($report['guiding_question']) ?></p></div>
            <form method="get" class="date-filter">
                <div class="form-group"><label for="behavior-start">Start date</label><input id="behavior-start" type="date" name="start" value="<?= escape($range['start']) ?>" required></div>
                <div class="form-group"><label for="behavior-end">End date</label><input id="behavior-end" type="date" name="end" value="<?= escape($range['end']) ?>" required></div>
                <button type="submit" class="button button-primary">Apply filter</button>
            </form>
        </section>

        <p class="status-message status-success">Showing <?= escape($range['start']) ?> through <?= escape($range['end']) ?> (UTC).</p>

        <section class="panel report-panel">
            <p class="eyebrow">Shopping flow</p><h2>Sessions reaching each step</h2>
            <p class="muted">Each later step must happen after the previous one in the same session. Width and labels use the same session scale.</p>
            <ol class="shopping-funnel report-funnel" aria-label="Shopping sessions reaching each step">
                <?php foreach ($steps as $index => [$label, $count]): ?>
                    <?php $width = $count / $maximum * 100; ?>
                    <li class="funnel-step">
                        <span class="funnel-label"><?= escape($label) ?></span>
                        <div class="funnel-track" aria-hidden="true"><div class="funnel-band" style="width: <?= $width ?>%"></div></div>
                        <strong class="funnel-count"><?= $count ?></strong>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="overview-shopping-result">
                <?= $progress['product'] > 0
                    ? $progress['checkout'] . ' of ' . $progress['product'] . ' product-viewing sessions reached checkout afterward (' . number_format($progress['checkout'] / $progress['product'] * 100, 1) . '%).'
                    : 'No product-viewing sessions were recorded, so checkout reach cannot be calculated.' ?>
            </p>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Exact counts</p><h2>Shopping-stage table</h2>
            <div class="table-wrapper" tabindex="0">
                <table class="data-table">
                    <thead><tr><th>Stage</th><th>Sessions</th><th>Share of site visits</th><th>Share of prior stage</th></tr></thead>
                    <tbody>
                        <?php $prior = null; ?>
                        <?php foreach ($steps as [$label, $count]): ?>
                            <tr>
                                <th scope="row"><?= escape($label) ?></th>
                                <td><?= $count ?></td>
                                <td><?= $progress['visited'] > 0 ? number_format($count / $progress['visited'] * 100, 1) . '%' : '—' ?></td>
                                <td><?= $prior !== null && $prior > 0 ? number_format($count / $prior * 100, 1) . '%' : '—' ?></td>
                            </tr>
                            <?php $prior = $count; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted">The final step is a randomized demo message, not a verified payment or purchase.</p>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Technical friction</p><h2>Pages recording JavaScript errors</h2>
            <p class="muted">Errors can help explain friction, but an error and a missing shopping step are not automatically related.</p>
            <div class="table-wrapper" tabindex="0">
                <table class="data-table">
                    <thead><tr><th>Page</th><th>Errors</th><th>Latest error</th><th>Example message</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['errors'] as $row): ?>
                            <tr><th scope="row" class="url-cell"><?= escape($row['page_url']) ?></th><td><?= (int) $row['error_count'] ?></td><td><?= escape($row['latest_error']) ?> UTC</td><td><?= escape($row['example_message'] ?? 'No message recorded') ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ($data['errors'] === []): ?><tr><td colspan="4">No JavaScript errors were recorded in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel analyst-panel">
            <p class="eyebrow">Analyst comments</p><h2>What should we investigate?</h2>
            <?php if ($canEdit): ?>
                <form method="post" class="comments-form">
                    <?= csrfInput() ?>
                    <label for="analyst-comments">Behavior analysis</label>
                    <textarea id="analyst-comments" name="analyst_comments" rows="8" maxlength="5000" placeholder="Explain where fewer sessions reach the next step and what should be checked next."><?= escape($savedReport['analyst_comments'] ?? '') ?></textarea>
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
