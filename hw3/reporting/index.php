<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (currentUser() !== null) {
    redirect('/dashboard.php');
}

redirect('/login.php');