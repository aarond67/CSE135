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

if ($requestMethod !== 'GET') {
    header('Allow: GET');
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