<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

requireGuest();

$identifier = '';
$error = null;
$messages = consumeFlashMessages();

if (($_GET['logged_out'] ?? '') === '1') {
    $messages[] = [
        'type' => 'success',
        'message' => 'You have been signed out.'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attemptLogin($identifier, $password)) {
        setFlashMessage(
            'success',
            'You successfully signed in.'
        );

        redirect('/dashboard.php');
    }

    $error = 'The username, email, or password was incorrect.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Sign In | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <p class="eyebrow">Bad Decisions Analytics</p>
        <h1>Sign in</h1>

        <p class="muted">
            Enter your username or email to access the reporting dashboard.
        </p>

        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success">
                <?= escape($message['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>

        <?php if ($error !== null): ?>
            <div class="alert alert-error">
                <?= escape($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <?= csrfInput() ?>

            <div class="form-group">
                <label for="identifier">
                    Username or email
                </label>

                <input
                    id="identifier"
                    name="identifier"
                    type="text"
                    value="<?= escape($identifier) ?>"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="button button-primary">
                Sign in
            </button>
        </form>
    </main>
</body>
</html>