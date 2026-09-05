<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$query = is_string($_SERVER['QUERY_STRING'] ?? null)
    ? $_SERVER['QUERY_STRING']
    : '';

redirect('/reports/engagement.php' . ($query !== '' ? '?' . $query : ''));
