<?php

declare(strict_types=1);

$allowedOrigin = 'https://test.baddecisions.site';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestOrigin === $allowedOrigin) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
}

header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($requestMethod !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (
    !is_array($payload) ||
    empty($payload['type']) ||
    empty($payload['url'])
) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'error' => 'A valid JSON payload with type and url is required'
    ]);
    exit;
}

/*
 * Temporary checkpoint logging.
 * Part 4 will replace this with a prepared MySQL insertion.
 */
error_log(
    '[CSE135 Collector] ' .
    json_encode($payload, JSON_UNESCAPED_SLASHES)
);

http_response_code(204);