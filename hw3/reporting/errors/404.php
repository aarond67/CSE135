<?php

declare(strict_types=1);

http_response_code(404);
$title = isset($title) && is_string($title) ? $title : 'Page Not Found';
$message = isset($message) && is_string($message)
    ? $message
    : 'The page or report you requested could not be found.';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Bad Decisions Analytics</title><link rel="stylesheet" href="/assets/css/app.css?v=hw5-1"></head>
<body class="error-page"><main class="panel error-card"><p class="eyebrow">Error 404</p><h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><a class="button button-primary" href="/">Return to the reporting site</a></main></body></html>
