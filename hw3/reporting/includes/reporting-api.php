<?php

declare(strict_types=1);

function apiResponse(
    array $body,
    int $status = 200
): never {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    http_response_code($status);

    echo json_encode(
        $body,
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    exit;
}

// The overview and performance APIs only read data.
function requireApiGetRequest(): void
{
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($requestMethod !== 'GET') {
        header('Allow: GET');

        apiResponse(
            ['error' => 'Method not allowed'],
            405
        );
    }
}

// API callers need a JSON error, not the HTML login page.
function requireApiUser(array $allowedRoles): array
{
    $user = currentUser();

    if ($user === null) {
        apiResponse(
            ['error' => 'Authentication is required'],
            401
        );
    }

    if (
        !in_array(
            $user['role'],
            $allowedRoles,
            true
        )
    ) {
        apiResponse(
            ['error' => 'You do not have permission to access this data'],
            403
        );
    }

    return $user;
}

function requireApiSection(string $section): array
{
    $user = requireApiUser([
        'super_admin',
        'analyst'
    ]);

    if (!userCanAccessSection($user, $section)) {
        apiResponse(
            ['error' => 'You do not have access to this analytics section'],
            403
        );
    }

    return $user;
}

// Reject invalid dates instead of letting PHP roll them into another month.
function parseApiDate(mixed $value): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }

    $timezone = new DateTimeZone('UTC');

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value,
        $timezone
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (
        $date === false ||
        (
            $errors !== false &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )
        ) ||
        $date->format('Y-m-d') !== $value
    ) {
        return null;
    }

    return $date;
}

// Default to today and the previous 29 days, all in UTC.
function getApiDateRange(): array
{
    $timezone = new DateTimeZone('UTC');
    $today = new DateTimeImmutable('today', $timezone);

    $defaultStart = $today->modify('-29 days');
    $defaultEnd = $today;

    $startInput =
        $_GET['start'] ??
        $defaultStart->format('Y-m-d');

    $endInput =
        $_GET['end'] ??
        $defaultEnd->format('Y-m-d');

    $startDate = parseApiDate($startInput);
    $endDate = parseApiDate($endInput);

    if ($startDate === null || $endDate === null) {
        apiResponse(
            ['error' => 'Dates must use the YYYY-MM-DD format'],
            400
        );
    }

    if ($startDate > $endDate) {
        apiResponse(
            ['error' => 'The start date cannot be after the end date'],
            400
        );
    }

    $numberOfDays = $startDate
        ->diff($endDate)
        ->days;

    if ($numberOfDays > 366) {
        apiResponse(
            ['error' => 'The selected date range cannot exceed 366 days'],
            400
        );
    }

    // Stop at the next midnight so the whole end date is included.
    $endExclusive = $endDate->modify('+1 day');

    return [
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $endDate->format('Y-m-d'),

        'sql_start' =>
            $startDate->format('Y-m-d H:i:s.v'),

        'sql_end_exclusive' =>
            $endExclusive->format('Y-m-d H:i:s.v')
    ];
}
