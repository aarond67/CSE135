<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = requireRole([
    'super_admin',
    'analyst'
]);

$messages = consumeFlashMessages();

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
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/dashboard.js" defer></script>
</head>
<body>
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
                    See how people are accessing the website, how quickly
                    pages are loading, and how visitors are interacting
                    with each page.
                </p>
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
            <article class="metric-card">
                <span class="metric-label">Page loads</span>
                <strong id="metric-page-loads">—</strong>
                <small>Pages collected</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Unique sessions</span>
                <strong id="metric-unique-sessions">—</strong>
                <small>Separate browsing sessions</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Average load time</span>
                <strong id="metric-average-load">—</strong>
                <small>Across performance records</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Fastest load</span>
                <strong id="metric-fastest-load">—</strong>
                <small>Fastest page measurement</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Slowest load</span>
                <strong id="metric-slowest-load">—</strong>
                <small>Slowest page measurement</small>
            </article>

            <article class="metric-card">
                <span class="metric-label">Activity events</span>
                <strong id="metric-activity-events">—</strong>
                <small>Recorded visitor interactions</small>
            </article>
        </section>

        <div class="report-grid">
            <section
                id="technology-report"
                class="panel report-panel"
            >
                <p class="eyebrow">Technology</p>
                <h2>Page loads by page</h2>

                <p class="muted">
                    This shows which pages were loaded most often during
                    the selected period.
                </p>

                <div
                    id="page-load-chart"
                    class="horizontal-chart"
                ></div>
            </section>

            <section
                id="performance-report"
                class="panel report-panel"
            >
                <p class="eyebrow">Performance</p>
                <h2>Average load time by page</h2>

                <p class="muted">
                    Longer bars represent pages that took more time to
                    finish loading.
                </p>

                <div
                    id="performance-chart"
                    class="horizontal-chart"
                ></div>
                <a
                    href="/reports/performance.php"
                    class="button button-secondary report-link"
                >
                    View detailed performance report
                </a>
            </section>

            <section
                id="behavior-report"
                class="panel report-panel"
            >
                <p class="eyebrow">Behavior</p>
                <h2>Activity by event type</h2>

                <p class="muted">
                    This compares mouse, keyboard, scrolling, idle, and
                    page activity.
                </p>

                <div
                    id="activity-chart"
                    class="horizontal-chart"
                ></div>
            </section>
        </div>

        <section
            id="top-pages-report"
            class="panel data-panel"
        >
            <p class="eyebrow">Page data</p>
            <h2>Top pages</h2>

            <p class="muted">
                Page-load and session totals for each collected URL.
            </p>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Page</th>
                            <th scope="col">Page loads</th>
                            <th scope="col">Unique sessions</th>
                        </tr>
                    </thead>

                    <tbody id="top-pages-table">
                        <tr>
                            <td colspan="3">
                                Loading page data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>