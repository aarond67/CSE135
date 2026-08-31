<?php

declare(strict_types=1);

function allowedUserRoles(): array
{
    return [
        'super_admin',
        'analyst',
        'viewer'
    ];
}

function allowedAnalyticsSections(): array
{
    return [
        'technology',
        'performance',
        'behavior'
    ];
}

function listDashboardUsers(): array
{
    $statement = database()->query(
        'SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            u.is_active,
            u.last_login_at,
            u.created_at,
            GROUP_CONCAT(
                p.section_name
                ORDER BY p.section_name
                SEPARATOR ","
            ) AS permitted_sections
         FROM users AS u
         LEFT JOIN user_section_permissions AS p
            ON p.user_id = u.id
         GROUP BY
            u.id,
            u.username,
            u.email,
            u.role,
            u.is_active,
            u.last_login_at,
            u.created_at
         ORDER BY u.created_at ASC'
    );

    $users = $statement->fetchAll();

    foreach ($users as &$user) {
        $sections = $user['permitted_sections'];

        $user['id'] = (int) $user['id'];
        $user['is_active'] = (bool) $user['is_active'];
        $user['permitted_sections'] =
            is_string($sections) && $sections !== ''
                ? explode(',', $sections)
                : [];
    }

    unset($user);

    return $users;
}

function findDashboardUser(int $userId): ?array
{
    $statement = database()->prepare(
        'SELECT
            id,
            username,
            email,
            role,
            is_active,
            last_login_at,
            created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => $userId
    ]);

    $user = $statement->fetch();

    if (!$user) {
        return null;
    }

    $permissionStatement = database()->prepare(
        'SELECT section_name
         FROM user_section_permissions
         WHERE user_id = :user_id
         ORDER BY section_name'
    );

    $permissionStatement->execute([
        'user_id' => $userId
    ]);

    $user['id'] = (int) $user['id'];
    $user['is_active'] = (bool) $user['is_active'];
    $user['permitted_sections'] =
        $permissionStatement->fetchAll(PDO::FETCH_COLUMN);

    return $user;
}

function validateDashboardUserInput(
    array $input,
    bool $passwordRequired
): array {
    $username = trim((string) ($input['username'] ?? ''));
    $email = strtolower(
        trim((string) ($input['email'] ?? ''))
    );
    $role = (string) ($input['role'] ?? 'viewer');
    $password = (string) ($input['password'] ?? '');
    $passwordConfirmation =
        (string) ($input['password_confirmation'] ?? '');

    $requestedSections = $input['sections'] ?? [];

    if (!is_array($requestedSections)) {
        $requestedSections = [];
    }

    $sections = array_values(
        array_intersect(
            allowedAnalyticsSections(),
            $requestedSections
        )
    );

    $errors = [];

    if (
        !preg_match(
            '/^[A-Za-z0-9_.-]{3,50}$/',
            $username
        )
    ) {
        $errors[] =
            'The username must be 3–50 characters and may only contain ' .
            'letters, numbers, periods, underscores, and hyphens.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!in_array($role, allowedUserRoles(), true)) {
        $errors[] = 'Select a valid user role.';
    }

    if ($passwordRequired && $password === '') {
        $errors[] = 'A password is required.';
    }

    if ($password !== '' && strlen($password) < 10) {
        $errors[] =
            'The password must contain at least 10 characters.';
    }

    if (
        $password !== '' &&
        !hash_equals($password, $passwordConfirmation)
    ) {
        $errors[] = 'The password confirmation did not match.';
    }

    if ($role !== 'analyst') {
        $sections = [];
    }

    return [
        [
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'password' => $password,
            'is_active' =>
                (string) ($input['is_active'] ?? '') === '1',
            'sections' => $sections
        ],
        $errors
    ];
}

function saveUserSectionPermissions(
    PDO $connection,
    int $userId,
    string $role,
    array $sections
): void {
    $delete = $connection->prepare(
        'DELETE FROM user_section_permissions
         WHERE user_id = :user_id'
    );

    $delete->execute([
        'user_id' => $userId
    ]);

    if ($role !== 'analyst') {
        return;
    }

    $insert = $connection->prepare(
        'INSERT INTO user_section_permissions (
            user_id,
            section_name
         ) VALUES (
            :user_id,
            :section_name
         )'
    );

    foreach ($sections as $section) {
        $insert->execute([
            'user_id' => $userId,
            'section_name' => $section
        ]);
    }
}

function createDashboardUser(array $data): int
{
    $connection = database();
    $connection->beginTransaction();

    try {
        $passwordHash = password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        );

        if (!is_string($passwordHash)) {
            throw new RuntimeException(
                'The password could not be secured.'
            );
        }

        $statement = $connection->prepare(
            'INSERT INTO users (
                username,
                email,
                password_hash,
                role,
                is_active
             ) VALUES (
                :username,
                :email,
                :password_hash,
                :role,
                :is_active
             )'
        );

        $statement->execute([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'role' => $data['role'],
            'is_active' => $data['is_active'] ? 1 : 0
        ]);

        $userId = (int) $connection->lastInsertId();

        saveUserSectionPermissions(
            $connection,
            $userId,
            $data['role'],
            $data['sections']
        );

        $connection->commit();

        return $userId;
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $error;
    }
}

function updateDashboardUser(
    int $userId,
    int $currentUserId,
    array $data
): void {
    $existingUser = findDashboardUser($userId);

    if ($existingUser === null) {
        throw new DomainException(
            'The selected user does not exist.'
        );
    }

    if (
        $userId === $currentUserId &&
        (
            $data['role'] !== 'super_admin' ||
            !$data['is_active']
        )
    ) {
        throw new DomainException(
            'You cannot remove your own super-admin access.'
        );
    }

    $connection = database();
    $connection->beginTransaction();

    try {
        if ($data['password'] !== '') {
            $passwordHash = password_hash(
                $data['password'],
                PASSWORD_DEFAULT
            );

            $statement = $connection->prepare(
                'UPDATE users
                 SET username = :username,
                     email = :email,
                     password_hash = :password_hash,
                     role = :role,
                     is_active = :is_active
                 WHERE id = :id'
            );

            $statement->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $passwordHash,
                'role' => $data['role'],
                'is_active' => $data['is_active'] ? 1 : 0,
                'id' => $userId
            ]);
        } else {
            $statement = $connection->prepare(
                'UPDATE users
                 SET username = :username,
                     email = :email,
                     role = :role,
                     is_active = :is_active
                 WHERE id = :id'
            );

            $statement->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'role' => $data['role'],
                'is_active' => $data['is_active'] ? 1 : 0,
                'id' => $userId
            ]);
        }

        saveUserSectionPermissions(
            $connection,
            $userId,
            $data['role'],
            $data['sections']
        );

        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $error;
    }
}

function deleteDashboardUser(
    int $userId,
    int $currentUserId
): void {
    if ($userId === $currentUserId) {
        throw new DomainException(
            'You cannot delete your own account.'
        );
    }

    if (findDashboardUser($userId) === null) {
        throw new DomainException(
            'The selected user does not exist.'
        );
    }

    $statement = database()->prepare(
        'DELETE FROM users
         WHERE id = :id'
    );

    $statement->execute([
        'id' => $userId
    ]);
}