<?php

declare(strict_types=1);

const DATABASE_CONFIG = '/etc/cse135/collector-db.php';

function respondWithError(int $status, string $message): never
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function mysqlDateTime(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        throw new InvalidArgumentException('A valid timestamp is required');
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable $error) {
        throw new InvalidArgumentException('A valid timestamp is required');
    }

    return $date
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s.v');
}

function nullableMysqlDateTime(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return mysqlDateTime($value);
}

function databaseJson(mixed $value): string
{
    return json_encode(
        $value,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    );
}

function booleanValue(mixed $value): ?int
{
    if (!is_bool($value)) {
        return null;
    }

    return $value ? 1 : 0;
}

$allowedOrigin = 'https://test.baddecisions.site';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestOrigin === $allowedOrigin) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header('Access-Control-Allow-Credentials: true');
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
    respondWithError(405, 'Method not allowed');
}

$rawBody = file_get_contents('php://input');

try {
    $payload = json_decode(
        $rawBody ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $error) {
    respondWithError(400, 'Valid JSON is required');
}

if (!is_array($payload)) {
    respondWithError(400, 'The JSON payload must be an object');
}

$type = $payload['type'] ?? null;
$sessionId = $payload['sessionId'] ?? null;
$pageUrl = $payload['url'] ?? null;
$timestamp = $payload['timestamp'] ?? null;
$data = $payload['data'] ?? [];

$allowedTypes = [
    'pageview',
    'static',
    'performance',
    'activity',
    'error'
];

if (!is_string($type) || !in_array($type, $allowedTypes, true)) {
    respondWithError(400, 'A supported data type is required');
}

if (
    !is_string($sessionId) ||
    !preg_match('/^[A-Za-z0-9-]{1,36}$/', $sessionId)
) {
    respondWithError(400, 'A valid sessionId is required');
}

if (!is_string($pageUrl) || $pageUrl === '') {
    respondWithError(400, 'A valid url is required');
}

if (!is_array($data)) {
    respondWithError(400, 'The data property must be an object');
}

try {
    $collectedAt = mysqlDateTime($timestamp);
} catch (InvalidArgumentException $error) {
    respondWithError(400, $error->getMessage());
}

try {
    $databaseConfig = require DATABASE_CONFIG;

    $pdo = new PDO(
        $databaseConfig['dsn'],
        $databaseConfig['username'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $pdo->beginTransaction();

    $sessionStatement = $pdo->prepare(
        'INSERT INTO sessions (session_id, first_seen, last_seen)
         VALUES (:session_id, :first_seen, :last_seen)
         ON DUPLICATE KEY UPDATE
            first_seen = LEAST(first_seen, VALUES(first_seen)),
            last_seen = GREATEST(last_seen, VALUES(last_seen))'
    );

    $sessionStatement->execute([
        'session_id' => $sessionId,
        'first_seen' => $collectedAt,
        'last_seen' => $collectedAt
    ]);

    if ($type === 'static') {
        $screen = is_array($data['screenDimensions'] ?? null)
            ? $data['screenDimensions']
            : [];
        $window = is_array($data['windowDimensions'] ?? null)
            ? $data['windowDimensions']
            : [];
        $network = is_array($data['network'] ?? null)
            ? $data['network']
            : [];

        $statement = $pdo->prepare(
            'INSERT INTO static_data (
                session_id, page_url, collected_at, user_agent, language,
                cookies_enabled, javascript_enabled, images_enabled,
                css_enabled, screen_width, screen_height, window_width,
                window_height, network_type, effective_type, downlink,
                rtt, save_data, raw_data
             ) VALUES (
                :session_id, :page_url, :collected_at, :user_agent,
                :language, :cookies_enabled, :javascript_enabled,
                :images_enabled, :css_enabled, :screen_width,
                :screen_height, :window_width, :window_height,
                :network_type, :effective_type, :downlink, :rtt,
                :save_data, :raw_data
             )'
        );

        $statement->execute([
            'session_id' => $sessionId,
            'page_url' => $pageUrl,
            'collected_at' => $collectedAt,
            'user_agent' => $data['userAgent'] ?? null,
            'language' => $data['language'] ?? null,
            'cookies_enabled' => booleanValue($data['cookiesEnabled'] ?? null),
            'javascript_enabled' => booleanValue($data['javascriptEnabled'] ?? null),
            'images_enabled' => booleanValue($data['imagesEnabled'] ?? null),
            'css_enabled' => booleanValue($data['cssEnabled'] ?? null),
            'screen_width' => $screen['width'] ?? null,
            'screen_height' => $screen['height'] ?? null,
            'window_width' => $window['width'] ?? null,
            'window_height' => $window['height'] ?? null,
            'network_type' => $network['type'] ?? null,
            'effective_type' => $network['effectiveType'] ?? null,
            'downlink' => $network['downlink'] ?? null,
            'rtt' => $network['rtt'] ?? null,
            'save_data' => booleanValue($network['saveData'] ?? null),
            'raw_data' => databaseJson($data)
        ]);
    } elseif ($type === 'performance') {
        $navigationTiming = $data['navigationTiming'] ?? [];

        $statement = $pdo->prepare(
            'INSERT INTO performance_data (
                session_id, page_url, collected_at, page_load_start,
                page_load_end, total_load_time_ms, navigation_timing
             ) VALUES (
                :session_id, :page_url, :collected_at, :page_load_start,
                :page_load_end, :total_load_time_ms, :navigation_timing
             )'
        );

        $statement->execute([
            'session_id' => $sessionId,
            'page_url' => $pageUrl,
            'collected_at' => $collectedAt,
            'page_load_start' => nullableMysqlDateTime($data['pageLoadStart'] ?? null),
            'page_load_end' => nullableMysqlDateTime($data['pageLoadEnd'] ?? null),
            'total_load_time_ms' => is_numeric($data['totalLoadTimeMilliseconds'] ?? null)
                ? $data['totalLoadTimeMilliseconds']
                : null,
            'navigation_timing' => databaseJson($navigationTiming)
        ]);
    } else {
        $activityStatement = $pdo->prepare(
            'INSERT INTO activity_events (
                session_id, page_url, event_type, event_time, event_data
             ) VALUES (
                :session_id, :page_url, :event_type, :event_time, :event_data
             )'
        );

        if ($type === 'activity') {
            $events = $data['events'] ?? null;

            if (!is_array($events)) {
                throw new InvalidArgumentException(
                    'Activity data must contain an events array'
                );
            }

            foreach ($events as $event) {
                if (!is_array($event)) {
                    throw new InvalidArgumentException(
                        'Every activity event must be an object'
                    );
                }

                $eventType = $event['eventType'] ?? null;
                $eventData = $event['data'] ?? [];

                if (!is_string($eventType) || $eventType === '') {
                    throw new InvalidArgumentException(
                        'Every activity event requires an eventType'
                    );
                }

                if (!is_array($eventData)) {
                    $eventData = ['value' => $eventData];
                }

                $activityStatement->execute([
                    'session_id' => $sessionId,
                    'page_url' => $pageUrl,
                    'event_type' => $eventType,
                    'event_time' => mysqlDateTime($event['timestamp'] ?? null),
                    'event_data' => databaseJson($eventData)
                ]);
            }
        } else {
            $eventData = $type === 'pageview'
                ? [
                    'title' => $payload['title'] ?? null,
                    'referrer' => $payload['referrer'] ?? null
                ]
                : $data;

            $activityStatement->execute([
                'session_id' => $sessionId,
                'page_url' => $pageUrl,
                'event_type' => $type,
                'event_time' => $collectedAt,
                'event_data' => databaseJson($eventData)
            ]);
        }
    }

    $pdo->commit();
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respondWithError(400, $error->getMessage());
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[CSE135 Collector Database] ' . $error->getMessage());
    respondWithError(500, 'Unable to store analytics data');
}

http_response_code(204);
