<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
$messages = consumeFlashMessages();

$roleName = ucwords(
    str_replace('_', ' ', $user['role'])
);
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

            <form method="post" action="/logout.php">
                <?= csrfInput() ?>

                <button type="submit" class="button button-secondary">
                    Sign out
                </button>
            </form>
        </div>
    </header>

    <main class="dashboard-content">
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success">
                <?= escape($message['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>

        <section class="panel">
            <p class="eyebrow">Authentication checkpoint</p>
            <h1>Welcome, <?= escape($user['username']) ?></h1>

            <p>
                You are signed in as
                <strong><?= escape($roleName) ?></strong>.
            </p>

            <p class="muted">
                The analytics cards, charts, tables, and navigation will
                be added after the authentication system is verified.
            </p>
        </section>
    </main>
</body>
</html>