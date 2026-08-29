<?php

declare(strict_types=1);

function respondJson(array $body, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);

    echo json_encode(
        $body,
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    exit;
}

header('Cache-Control: no-store');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];

if (!in_array($requestMethod, $allowedMethods, true)) {
    header('Allow: GET, POST, PUT, DELETE');
    respondJson(['error' => 'Method not allowed'], 405);
}

try {
    $databaseConfig = require '/etc/cse135/reporting-db.php';

    $pdo = new PDO(
        $databaseConfig['dsn'],
        $databaseConfig['username'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $id = $_GET['id'] ?? null;

    if ($requestMethod === 'POST') {
        if ($id !== null) {
            respondJson(
                ['error' => 'POST requests must not include an ID'],
                400
            );
        }

        $rawBody = file_get_contents('php://input');

        try {
            $requestBody = json_decode(
                $rawBody ?: '',
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $error) {
            respondJson(['error' => 'Valid JSON is required'], 400);
        }

        if (!is_array($requestBody)) {
            respondJson(['error' => 'The request body must be an object'], 400);
        }

        if (array_key_exists('id', $requestBody)) {
            respondJson(
                ['error' => 'POST bodies must not include an ID'],
                400
            );
        }

        $sessionId = $requestBody['session_id'] ?? null;
        $pageUrl = $requestBody['page_url'] ?? null;
        $rawData = $requestBody['raw_data'] ?? null;

        if (!is_string($sessionId) || $sessionId === '') {
            respondJson(['error' => 'session_id is required'], 400);
        }

        if (!is_string($pageUrl) || $pageUrl === '') {
            respondJson(['error' => 'page_url is required'], 400);
        }

        if (!is_array($rawData)) {
            respondJson(['error' => 'raw_data must be an object'], 400);
        }

        $sessionStatement = $pdo->prepare(
            'SELECT session_id
            FROM sessions
            WHERE session_id = :session_id'
        );

        $sessionStatement->execute([
            'session_id' => $sessionId
        ]);

        if (!$sessionStatement->fetch()) {
            respondJson(['error' => 'Session not found'], 409);
        }

        $collectedAt = (new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC')
        ))->format('Y-m-d H:i:s.v');

        $statement = $pdo->prepare(
            'INSERT INTO static_data (
                session_id,
                page_url,
                collected_at,
                user_agent,
                language,
                raw_data
            ) VALUES (
                :session_id,
                :page_url,
                :collected_at,
                :user_agent,
                :language,
                :raw_data
            )'
        );

        $statement->execute([
            'session_id' => $sessionId,
            'page_url' => $pageUrl,
            'collected_at' => $collectedAt,
            'user_agent' => $requestBody['user_agent'] ?? null,
            'language' => $requestBody['language'] ?? null,
            'raw_data' => json_encode(
                $rawData,
                JSON_UNESCAPED_SLASHES
            )
        ]);

        $newId = (int) $pdo->lastInsertId();

        header("Location: /api/static/$newId");

        respondJson([
            'message' => 'Static record created',
            'id' => $newId
        ], 201);
    }

    if ($requestMethod === 'PUT') {
        if (
            $id === null ||
            !ctype_digit($id) ||
            (int) $id < 1
        ) {
            respondJson(
                ['error' => 'PUT requests require a valid numeric ID'],
                400
            );
        }

        $rawBody = file_get_contents('php://input');

        try {
            $requestBody = json_decode(
                $rawBody ?: '',
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $error) {
            respondJson(['error' => 'Valid JSON is required'], 400);
        }

        if (!is_array($requestBody)) {
            respondJson(['error' => 'The request body must be an object'], 400);
        }

        if (array_key_exists('id', $requestBody)) {
            respondJson(
                ['error' => 'The record ID belongs in the URL'],
                400
            );
        }

        $pageUrl = $requestBody['page_url'] ?? null;
        $rawData = $requestBody['raw_data'] ?? null;

        if (!is_string($pageUrl) || $pageUrl === '') {
            respondJson(['error' => 'page_url is required'], 400);
        }

        if (!is_array($rawData)) {
            respondJson(['error' => 'raw_data must be an object'], 400);
        }

        $existingStatement = $pdo->prepare(
            'SELECT id
            FROM static_data
            WHERE id = :id'
        );

        $existingStatement->execute([
            'id' => (int) $id
        ]);

        if (!$existingStatement->fetch()) {
            respondJson(['error' => 'Static record not found'], 404);
        }

        $statement = $pdo->prepare(
            'UPDATE static_data
            SET page_url = :page_url,
                user_agent = :user_agent,
                language = :language,
                raw_data = :raw_data
            WHERE id = :id'
        );

        $statement->execute([
            'page_url' => $pageUrl,
            'user_agent' => $requestBody['user_agent'] ?? null,
            'language' => $requestBody['language'] ?? null,
            'raw_data' => json_encode(
                $rawData,
                JSON_UNESCAPED_SLASHES
            ),
            'id' => (int) $id
        ]);

        respondJson([
            'message' => 'Static record updated',
            'id' => (int) $id
        ]);
    }

    if ($requestMethod === 'DELETE') {
        if (
            $id === null ||
            !ctype_digit($id) ||
            (int) $id < 1
        ) {
            respondJson(
                ['error' => 'DELETE requests require a valid numeric ID'],
                400
            );
        }

        $existingStatement = $pdo->prepare(
            'SELECT id
            FROM static_data
            WHERE id = :id'
        );

        $existingStatement->execute([
            'id' => (int) $id
        ]);

        if (!$existingStatement->fetch()) {
            respondJson(['error' => 'Static record not found'], 404);
        }

        $deleteStatement = $pdo->prepare(
            'DELETE FROM static_data
            WHERE id = :id'
        );

        $deleteStatement->execute([
            'id' => (int) $id
        ]);

        http_response_code(204);
        exit;
    }

    if ($id === null) {
        $statement = $pdo->query(
            'SELECT *
             FROM static_data
             ORDER BY id DESC'
        );

        $records = $statement->fetchAll();

        foreach ($records as &$record) {
            $record['raw_data'] = json_decode(
                $record['raw_data'],
                true
            );
        }

        unset($record);

        respondJson([
            'count' => count($records),
            'data' => $records
        ]);
    }

    if (!ctype_digit($id) || (int) $id < 1) {
        respondJson(['error' => 'A valid numeric ID is required'], 400);
    }

    $statement = $pdo->prepare(
        'SELECT *
         FROM static_data
         WHERE id = :id'
    );

    $statement->execute([
        'id' => (int) $id
    ]);

    $record = $statement->fetch();

    if (!$record) {
        respondJson(['error' => 'Static record not found'], 404);
    }

    $record['raw_data'] = json_decode(
        $record['raw_data'],
        true
    );

    respondJson([
        'data' => $record
    ]);
} catch (Throwable $error) {
    error_log(
        '[CSE135 Reporting API] ' . $error->getMessage()
    );

    respondJson(
        ['error' => 'Unable to retrieve static data'],
        500
    );
}