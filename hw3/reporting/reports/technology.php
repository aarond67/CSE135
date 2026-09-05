<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

[$user, $report, $savedReport] = requireSavedReportAccess('technology-overview');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    saveReportFromPost($user, $report, '/reports/technology.php');
}

$range = getReportDateRange();
$data = getTechnologyReportData($range);
$canEdit = canEditReport($user, $report['category']);
$messages = getFlashMessages();
$browserMaximum = max(array_map(
    static fn (array $row): int => (int) $row['page_loads'],
    $data['browsers'] ?: [['page_loads' => 0]]
));
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
    <title>Technology Report | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=hw5-1">
</head>
<body>
    <header class="topbar">
        <div><p class="eyebrow">Bad Decisions Analytics</p><strong>Technology Report</strong></div>
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
                <p class="eyebrow">Technology</p>
                <h1>Visitor technology</h1>
                <p class="muted"><?= escape($report['guiding_question']) ?></p>
            </div>
            <form method="get" class="date-filter">
                <div class="form-group"><label for="technology-start">Start date</label><input id="technology-start" type="date" name="start" value="<?= escape($range['start']) ?>" required></div>
                <div class="form-group"><label for="technology-end">End date</label><input id="technology-end" type="date" name="end" value="<?= escape($range['end']) ?>" required></div>
                <button type="submit" class="button button-primary">Apply filter</button>
            </form>
        </section>

        <p class="status-message status-success">Showing <?= escape($range['start']) ?> through <?= escape($range['end']) ?> (UTC).</p>

        <section class="report-grid report-grid-main">
            <article class="panel report-panel">
                <p class="eyebrow">Browser support</p>
                <h2>Page loads by browser</h2>
                <p class="muted">Longer bars identify the browsers represented most often in the collected page loads.</p>
                <div class="horizontal-chart" aria-label="Page loads grouped by browser">
                    <?php if ($data['browsers'] === []): ?>
                        <p class="empty-state">No browser data was recorded in this period.</p>
                    <?php else: ?>
                        <?php foreach ($data['browsers'] as $row): ?>
                            <?php $width = $browserMaximum > 0 ? (int) $row['page_loads'] / $browserMaximum * 100 : 0; ?>
                            <div class="chart-row">
                                <span class="chart-label"><?= escape($row['browser']) ?></span>
                                <div class="chart-track" aria-hidden="true"><div class="chart-bar chart-bar-technology" style="width: <?= $width ?>%"></div></div>
                                <strong class="chart-value"><?= (int) $row['page_loads'] ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <article class="panel report-panel">
                <p class="eyebrow">Connection conditions</p>
                <h2>Reported network types</h2>
                <p class="muted">This is a supporting signal from the browser and may be unavailable or approximate.</p>
                <ul class="stat-list">
                    <?php foreach ($data['networks'] as $row): ?>
                        <li><span><?= escape($row['connection_type']) ?></span><strong><?= (int) $row['page_loads'] ?> loads</strong></li>
                    <?php endforeach; ?>
                    <?php if ($data['networks'] === []): ?><li>No network data recorded.</li><?php endif; ?>
                </ul>
            </article>
        </section>

        <section class="panel data-panel">
            <p class="eyebrow">Screen support</p>
            <h2>Screen-size summary</h2>
            <p class="muted">The table keeps the session count beside the page-load count so repeat visits do not look like separate people.</p>
            <div class="table-wrapper" tabindex="0">
                <table class="data-table">
                    <thead><tr><th>Screen group</th><th>Sessions</th><th>Page loads</th><th>Avg. screen width</th><th>Avg. window width</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['devices'] as $row): ?>
                            <tr>
                                <th scope="row"><?= escape($row['screen_group']) ?></th>
                                <td><?= (int) $row['sessions'] ?></td>
                                <td><?= (int) $row['page_loads'] ?></td>
                                <td><?= $row['average_screen_width'] !== null ? (int) $row['average_screen_width'] . ' px' : '—' ?></td>
                                <td><?= $row['average_window_width'] !== null ? (int) $row['average_window_width'] . ' px' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($data['devices'] === []): ?><tr><td colspan="5">No screen data recorded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel analyst-panel">
            <p class="eyebrow">Analyst comments</p><h2>What should we support first?</h2>
            <?php if ($canEdit): ?>
                <form method="post" class="comments-form">
                    <?= csrfInput() ?>
                    <label for="analyst-comments">Technology analysis</label>
                    <textarea id="analyst-comments" name="analyst_comments" rows="8" maxlength="5000" placeholder="Explain which browser, screen, or connection conditions deserve testing first."><?= escape($savedReport['analyst_comments'] ?? '') ?></textarea>
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
