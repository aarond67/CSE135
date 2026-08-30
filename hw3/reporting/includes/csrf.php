<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return sprintf(
        '<input type="hidden" name="_csrf" value="%s">',
        escape(csrfToken())
    );
}

function requireValidCsrfToken(): void
{
    $submittedToken = $_POST['_csrf'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals(
            csrfToken(),
            $submittedToken
        )
    ) {
        abortRequest(
            400,
            'Invalid Request',
            'The security token was missing or invalid. Please try again.'
        );
    }
}