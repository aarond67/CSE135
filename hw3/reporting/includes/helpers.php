<?php

declare(strict_types=1);

function escape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function redirect(string $path, int $status = 302): never
{
    if (
        !str_starts_with($path, '/') ||
        str_starts_with($path, '//')
    ) {
        throw new InvalidArgumentException(
            'Redirects must use an internal path.'
        );
    }

    header('Location: ' . $path, true, $status);
    exit;
}

function setFlashMessage(
    string $type,
    string $message
): void {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function consumeFlashMessages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];

    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}

function abortRequest(
    int $status,
    string $title,
    string $message
): never {
    http_response_code($status);

    $errorPage =
        dirname(__DIR__) .
        '/errors/' .
        $status .
        '.php';

    if (is_file($errorPage)) {
        require $errorPage;
        exit;
    }

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" ';
    echo 'content="width=device-width, initial-scale=1.0">';
    echo '<title>' . escape($title) . '</title>';
    echo '</head>';
    echo '<body>';
    echo '<main>';
    echo '<h1>' . escape($title) . '</h1>';
    echo '<p>' . escape($message) . '</p>';
    echo '</main>';
    echo '</body>';
    echo '</html>';

    exit;
}