<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = requireRole([
    'super_admin',
    'analyst'
]);

$messages = getFlashMessages();
$canViewPerformance = userCanAccessSection($user, 'performance');

$roleName = ucwords(
    str_replace('_', ' ', $user['role'])
);

$timezone = new DateTimeZone('UTC');
$today = new DateTimeImmutable('today', $timezone);

$defaultEnd = $today->format('Y-m-d');
$defaultStart = $today
    ->modify('-29 days')
    ->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Dashboard | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=shopping-funnel-1">
    <script src="/assets/js/dashboard.js?v=shopping-funnel-1" defer></script>
</head>
<body class="overview-dashboard">
    <header class="topbar">
        <div>
            <p class="eyebrow">Bad Decisions Analytics</p>
            <strong>Reporting Dashboard</strong>
        </div>

        <div class="user-controls">
            <div>
                <strong><?= escape($user['username']) ?></strong>

                <span class="role-badge">
                    <?= escape($roleName) ?>
                </span>
            </div>

            <?php if ($user['role'] === 'super_admin'): ?>
                <a
                    href="/admin/users.php"
                    class="button button-secondary"
                >
                    Manage users
                </a>
            <?php endif; ?>

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
                <p class="eyebrow">Analytics overview</p>
                <h1>Website activity</h1>

                <p class="muted">
                    Traffic, shopping progress, and page speed at a glance.
                </p>

                <?php if ($canViewPerformance): ?>
                    <nav class="overview-report-nav" aria-label="Detailed reports">
                        <span>Reports</span>
                        <a href="/reports/performance.php" id="performance-report-link">
                            Performance report &rarr;
                        </a>
                    </nav>
                <?php endif; ?>
            </div>

            <form
                id="dashboard-filter"
                class="date-filter"
                data-default-start="<?= escape($defaultStart) ?>"
                data-default-end="<?= escape($defaultEnd) ?>"
            >
                <div class="form-group">
                    <label for="start-date">Start date</label>

                    <input
                        type="date"
                        id="start-date"
                        name="start"
                        value="<?= escape($defaultStart) ?>"
                        max="<?= escape($defaultEnd) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="end-date">End date</label>

                    <input
                        type="date"
                        id="end-date"
                        name="end"
                        value="<?= escape($defaultEnd) ?>"
                        max="<?= escape($defaultEnd) ?>"
                        required
                    >
                </div>

                <button
                    type="submit"
                    id="apply-filter"
                    class="button button-primary"
                >
                    Apply filter
                </button>

                <button
                    type="button"
                    id="reset-filter"
                    class="button button-secondary"
                >
                    Last 30 days
                </button>
            </form>
        </section>

        <p
            id="dashboard-status"
            class="status-message"
            role="status"
            aria-live="polite"
        >
            Loading analytics data...
        </p>

        <noscript>
            <div class="alert alert-error">
                JavaScript must be enabled to display the analytics
                dashboard.
            </div>
        </noscript>

        <section
            class="summary-grid"
            aria-label="Analytics summary"
        >
            <article class="metric-card" hidden>
                <span class="metric-label">Page loads</span>
                <strong id="metric-page-loads">—</strong>
                <small>Recorded static-data entries</small>
            </article>

            <article class="metric-card" hidden>
                <span class="metric-label">Unique sessions</span>
                <strong id="metric-unique-sessions">—</strong>
                <small>Session IDs in those page loads</small>
            </article>

            <article class="metric-card" hidden>
                <span class="metric-label">Average load time</span>
                <strong id="metric-average-load">—</strong>
                <small>Across valid performance measurements</small>
            </article>
        </section>

        <div class="report-grid">
            <section
                id="technology-report"
                class="panel report-panel"
                hidden
            >
                <p class="eyebrow">Traffic</p>
                <h2 id="page-load-title">Most visited pages</h2>

                <p class="muted" id="page-load-note">
                    Up to 8 URLs ranked by recorded page loads, including repeat visits.
                </p>

                <div
                    id="page-load-chart"
                    class="horizontal-chart"
                    aria-labelledby="page-load-title"
                    aria-describedby="page-load-note"
                    aria-busy="true"
                ></div>
            </section>

            <section
                id="behavior-report"
                class="panel report-panel"
                hidden
            >
                <p class="eyebrow">Behavior</p>
                <h2 id="shopping-title">Shopping progress</h2>

                <p class="muted" id="shopping-note">
                    Are sessions reaching checkout and seeing the demo success message?
                    Each session counts once per step in the selected dates.
                </p>

                <div
                    id="shopping-chart"
                    class="overview-shopping-chart"
                    role="region"
                    aria-labelledby="shopping-title"
                    aria-describedby="shopping-note shopping-limits"
                    aria-busy="true"
                ></div>

                <p class="muted overview-shopping-limits" id="shopping-limits">
                    Product view, checkout, and demo success must occur in that order.
                    Success is a randomized demo message, not a verified purchase.
                    Older visits have no success tracking; missing records or steps
                    outside the date range also leave stages uncounted.
                </p>
            </section>
        </div>

        <section
            id="performance-report"
            class="panel data-panel"
            hidden
        >
            <p class="eyebrow">Performance</p>
            <h2>Pages to investigate</h2>

            <p class="muted">
                Up to 10 URLs with the highest average load time. A high average
                with few measurements is a reason to investigate, not proof of a persistent problem.
            </p>

            <div class="table-wrapper" tabindex="0" role="region" aria-label="Page performance; scroll horizontally if needed">
                <table class="data-table">
                    <caption class="overview-table-caption">
                        Each row summarizes valid performance measurements in the selected UTC dates.
                        Summary cards cover the full date range. Different URL paths and query strings remain separate.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Page</th>
                            <th scope="col">Measurements</th>
                            <th scope="col">Average load</th>
                            <th scope="col">Slowest load</th>
                        </tr>
                    </thead>

                    <tbody id="page-performance-table">
                        <tr>
                            <td colspan="4">
                                Loading performance summary...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
