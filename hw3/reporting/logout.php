<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abortRequest(
        405,
        'Method Not Allowed',
        'Logout requests must use the POST method.'
    );
}

requireLogin();
requireValidCsrfToken();
logoutUser();

redirect('/login.php?logged_out=1');