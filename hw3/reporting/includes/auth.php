<?php

declare(strict_types=1);

/**
 * Return user information
 */
function currentUser(): ?array
{
    $userId = $_SESSION['user']['id'] ?? null;

    if (!is_numeric($userId)) {
        return null;
    }

    $statement = database()->prepare(
        'SELECT id, username, email, role, is_active
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => (int) $userId
    ]);

    $user = $statement->fetch();

    if (!$user || !(bool) $user['is_active']) {
        logoutUser();
        return null;
    }

    $safeUser = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    $_SESSION['user'] = $safeUser;

    return $safeUser;
}

/**
 * Check a username or email and password.
 */
function attemptLogin(string $identifier, string $password): bool
{
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return false;
    }

    $statement = database()->prepare(
        'SELECT id, username, email, password_hash, role, is_active
         FROM users
         WHERE username = :username_identifier
            OR email = :email_identifier
         LIMIT 1'
    );

    $statement->execute([
        'username_identifier' => $identifier,
        'email_identifier' => $identifier
    ]);

    $user = $statement->fetch();

    if (
        !$user ||
        !(bool) $user['is_active'] ||
        !password_verify($password, $user['password_hash'])
    ) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    database()
        ->prepare(
            'UPDATE users
             SET last_login_at = CURRENT_TIMESTAMP(3)
             WHERE id = :id'
        )
        ->execute([
            'id' => (int) $user['id']
        ]);

    return true;
}

/**
 * Remove all login-session information.
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => 'Lax'
        ]);
    }

    session_destroy();
}

/**
 * Require the visitor to be logged in.
 */
function requireLogin(): array
{
    $user = currentUser();

    if ($user === null) {
        setFlashMessage('error', 'Please sign in to continue.');
        redirect('/login.php');
    }

    return $user;
}

/**
 * Prevent logged-in users from returning to the login page.
 */
function requireGuest(): void
{
    if (currentUser() !== null) {
        redirect('/dashboard.php');
    }
}

/**
 * Require one of the provided roles.
 */
function requireRole(array $allowedRoles): array
{
    $user = requireLogin();

    if (!in_array($user['role'], $allowedRoles, true)) {
        abortRequest(
            403,
            'Forbidden',
            'You do not have permission to access this page.'
        );
    }

    return $user;
}

/**
 * Check whether a user can access an analytics section.
 */
function userCanAccessSection(array $user, string $section): bool
{
    if ($user['role'] === 'super_admin') {
        return true;
    }

    if ($user['role'] !== 'analyst') {
        return false;
    }

    $statement = database()->prepare(
        'SELECT 1
         FROM user_section_permissions
         WHERE user_id = :user_id
           AND section_name = :section_name
         LIMIT 1'
    );

    $statement->execute([
        'user_id' => $user['id'],
        'section_name' => $section
    ]);

    return (bool) $statement->fetchColumn();
}

/**
 * Require permission for one analytics section.
 */
function requireSectionAccess(string $section): array
{
    $allowedSections = [
        'technology',
        'performance',
        'behavior'
    ];

    if (!in_array($section, $allowedSections, true)) {
        abortRequest(
            404,
            'Not Found',
            'The requested analytics section does not exist.'
        );
    }

    $user = requireLogin();

    if (!userCanAccessSection($user, $section)) {
        abortRequest(
            403,
            'Forbidden',
            'You do not have permission to access this analytics section.'
        );
    }

    return $user;
}