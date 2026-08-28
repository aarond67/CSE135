<?php

declare(strict_types=1);

$acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$primaryLanguage = null;

if ($acceptLanguage !== '') {
    $primaryLanguage = explode(',', $acceptLanguage)[0];
}

$payload = [
    'type' => 'static',
    'sessionId' => null,
    'url' => $_SERVER['HTTP_REFERER'] ?? '',
    'timestamp' => gmdate('c'),
    'data' => [
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'language' => $primaryLanguage,
        'javascriptEnabled' => false,
        'imagesEnabled' => true
    ],
    'collectionMethod' => 'noscript-pixel'
];

error_log(
    '[CSE135 Collector] ' .
    json_encode($payload, JSON_UNESCAPED_SLASHES)
);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: image/gif');

echo base64_decode(
    'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
);