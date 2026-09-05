<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

[$user, $report, $savedReport] = requireSavedReportAccess('checkout-dropoff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    saveReportFromPost($user, $report, '/reports/checkout.php');
}

$range = getReportDateRange();
$data = getCheckoutDropoffReportData($range);
$counts = $data['counts'];
$stages = $data['stages'];
$largestDrop = $data['largest_drop'];
$canEdit = canEditReport($user, $report['category']);
$messages = getFlashMessages();
$chartMaximum = max($counts['product'], 1);
$checkoutRate = $counts['product'] > 0
    ? $counts['checkout'] / $counts['product'] * 100
    : 0.0;
$reviewRate = $counts['checkout'] > 0
    ? $counts['review'] / $counts['checkout'] * 100
    : 0.0;
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
    <title>Checkout Drop-off | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=checkout-dropoff-1">
</head>
<body>
    <header class="topbar">
        <div><p class="eyebrow">Bad Decisions Analytics</p><strong>Checkout Drop-off</strong></div>
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
                <p class="eyebrow">Checkout behavior</p>
                <h1>Checkout drop-off</h1>
                <p class="muted"><?= escape($report['guiding_question']) ?></p>
            </div>
            <form method="get" class="date-filter">
                <div class="form-group"><label for="checkout-start">Start date</label><input id="checkout-start" type="date" name="start" value="<?= escape($range['start']) ?>" required></div>
                <div class="form-group"><label for="checkout-end">End date</label><input id="checkout-end" type="date" name="end" value="<?= escape($range['end']) ?>" required></div>
                <button type="submit" class="button button-primary">Apply filter</button>
            </form>
        </section>

        <p class="status-message status-success">Showing <?= escape($range['start']) ?> through <?= escape($range['end']) ?> (UTC).</p>

        <section class="summary-grid" aria-label="Checkout progress summary">
            <article class="metric-card"><span class="metric-label">Product-view sessions</span><strong><?= number_format($counts['product']) ?></strong><small>Starting point for this funnel</small></article>
            <article class="metric-card"><span class="metric-label">Reached checkout</span><strong><?= number_format($checkoutRate, 1) ?>%</strong><small><?= number_format($counts['checkout']) ?> of <?= number_format($counts['product']) ?> product-view sessions</small></article>
            <article class="metric-card"><span class="metric-label">Reached review</span><strong><?= number_format($reviewRate, 1) ?>%</strong><small><?= number_format($counts['review']) ?> of <?= number_format($counts['checkout']) ?> checkout sessions</small></article>
        </section>

        <section class="panel report-panel">
            <p class="eyebrow">Ordered session funnel</p>
            <h2>Sessions reaching each checkout stage</h2>
            <p class="muted">Each session counts once at a stage, and a later stage only counts when its earlier stages were recorded first.</p>
            <ol class="shopping-funnel report-funnel" aria-label="Sessions reaching each checkout stage">
                <?php foreach ($stages as $stage): ?>
                    <?php $width = $stage['count'] / $chartMaximum * 100; ?>
                    <li class="funnel-step">
                        <span class="funnel-label"><?= escape($stage['label']) ?></span>
                        <span class="funnel-track" aria-hidden="true"><span class="funnel-band" style="width: <?= number_format($width, 2, '.', '') ?>%"></span></span>
                        <strong class="funnel-count"><?= number_format($stage['count']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($counts['product'] === 0): ?>
                <p class="empty-state">No product-view sessions were recorded in this period, so there is no checkout path to compare.</p>
            <?php elseif ($largestDrop !== null && $largestDrop['drop'] > 0): ?>
                <p class="report-finding"><strong>Largest recorded drop:</strong> <?= number_format($largestDrop['drop']) ?> sessions between <?= escape($largestDrop['previous_label']) ?> and <?= escape($largestDrop['label']) ?>.</p>
            <?php else: ?>
                <p class="report-finding"><strong>No stage-to-stage drop was recorded in this sample.</strong></p>
            <?php endif; ?>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Exact values</p>
            <h2>Stage-by-stage drop-off</h2>
            <p class="muted">Use the session counts with the continuation rate to identify the transition that deserves investigation.</p>
            <div class="table-wrapper" tabindex="0" role="region" aria-label="Checkout drop-off details; scroll horizontally if needed">
                <table class="data-table">
                    <thead><tr><th>Stage reached</th><th>Sessions</th><th>Drop from prior stage</th><th>Continued from prior stage</th></tr></thead>
                    <tbody>
                        <?php foreach ($stages as $stage): ?>
                            <tr>
                                <th scope="row"><?= escape($stage['label']) ?></th>
                                <td><?= number_format($stage['count']) ?></td>
                                <td><?= $stage['drop'] === null ? '&mdash;' : number_format($stage['drop']) ?></td>
                                <td><?= $stage['continued_rate'] === null ? '&mdash;' : number_format($stage['continued_rate'], 1) . '%' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="report-caveat">Checkout-step tracking records only step numbers, never names, addresses, or payment fields. Payment and review tracking starts with this update, so older sessions may appear to stop at checkout even if they moved farther. The final message is randomized and is not proof of a purchase.</p>
        </section>

        <section class="panel analyst-panel">
            <p class="eyebrow">Analyst comments</p><h2>Where are sessions dropping off?</h2>
            <?php if ($canEdit): ?>
                <form method="post" class="comments-form">
                    <?= csrfInput() ?>
                    <label for="analyst-comments">Checkout drop-off analysis</label>
                    <textarea id="analyst-comments" name="analyst_comments" rows="8" maxlength="5000" placeholder="Name the stage with the largest recorded drop, compare the counts, and explain what you would investigate next."><?= escape($savedReport['analyst_comments'] ?? '') ?></textarea>
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
