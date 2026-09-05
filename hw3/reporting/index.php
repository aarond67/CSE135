<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = currentUser();

if ($user !== null) {
    redirect(homePathForUser($user));
}

redirect('/login.php');
