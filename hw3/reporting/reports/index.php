<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = requireLogin();
$reports = reportsForUser($user);
$messages = getFlashMessages();
$roleName = displayUserRole($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=report-focus-1">
</head>
<body>
    <header class="topbar">
        <div>
            <p class="eyebrow">Bad Decisions Analytics</p>
            <strong>Report Library</strong>
        </div>

        <div class="user-controls">
            <div>
                <strong><?= escape($user['username']) ?></strong>
                <span class="role-badge"><?= escape($roleName) ?></span>
            </div>

            <?php if ($user['role'] !== 'viewer'): ?>
                <a href="/dashboard.php" class="button button-secondary">
                    Dashboard
                </a>
            <?php endif; ?>

            <?php if ($user['role'] === 'super_admin'): ?>
                <a href="/admin/users.php" class="button button-secondary">
                    Manage users
                </a>
            <?php endif; ?>

            <form method="post" action="/logout.php">
                <?= csrfInput() ?>
                <button type="submit" class="button button-secondary">Sign out</button>
            </form>
        </div>
    </header>

    <main class="dashboard-content">
        <?php foreach ($messages as $message): ?>
            <?php $type = ($message['type'] ?? '') === 'error' ? 'error' : 'success'; ?>
            <div class="alert alert-<?= escape($type) ?>">
                <?= escape($message['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>

        <section class="dashboard-heading report-library-heading">
            <div>
                <p class="eyebrow">Saved reports</p>
                <h1><?= $user['role'] === 'viewer' ? 'Published reports' : 'Analytics reports' ?></h1>
                <p class="muted">
                    <?= $user['role'] === 'viewer'
                        ? 'Open reports that an analyst has published for you.'
                        : 'Investigate a category, add your interpretation, and publish it for viewers.' ?>
                </p>
            </div>
        </section>

        <?php if ($reports === []): ?>
            <section class="panel empty-state">
                <h2>No reports are available yet</h2>
                <p>An analyst must publish a report before it appears here.</p>
            </section>
        <?php else: ?>
            <section class="report-card-grid" aria-label="Available reports">
                <?php foreach ($reports as $report): ?>
                    <article class="panel report-card report-card-<?= escape($report['category']) ?>">
                        <p class="eyebrow"><?= escape($report['category_label']) ?></p>
                        <h2><?= escape($report['title']) ?></h2>
                        <p><?= escape($report['guiding_question']) ?></p>

                        <div class="report-card-footer">
                            <?php if ($user['role'] !== 'viewer'): ?>
                                <span class="report-state <?= $report['is_published'] ? 'is-published' : '' ?>">
                                    <?= $report['is_published'] ? 'Published' : 'Draft' ?>
                                </span>
                            <?php endif; ?>

                            <a class="button button-primary" href="<?= escape($report['path']) ?>">
                                Open report
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
