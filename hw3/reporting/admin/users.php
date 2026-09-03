<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$currentUser = requireRole(['super_admin']);
$messages = getFlashMessages();
$errors = [];

$createValues = [
    'username' => '',
    'email' => '',
    'role' => 'viewer',
    'is_active' => true,
    'sections' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            [$data, $validationErrors] =
                validateUserInput($_POST, true);

            $createValues = $data;
            $errors = $validationErrors;

            if ($errors === []) {
                createUser($data);

                setFlashMessage(
                    'success',
                    'The user account was created.'
                );

                redirect('/admin/users.php');
            }
        } elseif ($action === 'update') {
            $userId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$userId) {
                throw new DomainException(
                    'A valid user ID is required.'
                );
            }

            [$data, $validationErrors] =
                validateUserInput($_POST, false);

            $errors = $validationErrors;

            if ($errors === []) {
                updateUser(
                    (int) $userId,
                    $currentUser['id'],
                    $data
                );

                setFlashMessage(
                    'success',
                    'The user account was updated.'
                );

                redirect('/admin/users.php');
            }
        } elseif ($action === 'delete') {
            $userId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$userId) {
                throw new DomainException(
                    'A valid user ID is required.'
                );
            }

            deleteUser(
                (int) $userId,
                $currentUser['id']
            );

            setFlashMessage(
                'success',
                'The user account was deleted.'
            );

            redirect('/admin/users.php');
        } else {
            abortRequest(
                400,
                'Invalid Request',
                'The requested user-management action was invalid.'
            );
        }
    } catch (DomainException $error) {
        $errors[] = $error->getMessage();
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            $errors[] =
                'That username or email address is already being used.';
        } else {
            error_log(
                '[Reporting User Management] ' .
                $error->getMessage()
            );

            $errors[] =
                'The account could not be saved because of a database error.';
        }
    } catch (Throwable $error) {
        error_log(
            '[Reporting User Management] ' .
            $error->getMessage()
        );

        $errors[] =
            'The account could not be changed. Please try again.';
    }
}

$users = listUsers();
$roles = allowedUserRoles();
$sections = allowedAnalyticsSections();

function displayRole(string $role): string
{
    return ucwords(
        str_replace('_', ' ', $role)
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>User Management | Bad Decisions Analytics</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="topbar">
        <div>
            <p class="eyebrow">Bad Decisions Analytics</p>
            <strong>User Management</strong>
        </div>

        <div class="user-controls">
            <a class="button button-secondary" href="/dashboard.php">
                Dashboard
            </a>

            <form method="post" action="/logout.php">
                <?= csrfInput() ?>

                <button type="submit" class="button button-secondary">
                    Sign out
                </button>
            </form>
        </div>
    </header>

    <main class="dashboard-content">
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success">
                <?= escape($message['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-error">
                <strong>Please fix these before saving:</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="panel">
            <p class="eyebrow">Super-admin controls</p>
            <h1>Create an account</h1>

            <form method="post" class="management-form">
                <?= csrfInput() ?>

                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="create-username">Username</label>

                        <input
                            id="create-username"
                            name="username"
                            type="text"
                            value="<?= escape($createValues['username']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="create-email">Email</label>

                        <input
                            id="create-email"
                            name="email"
                            type="email"
                            value="<?= escape($createValues['email']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="create-password">Password</label>

                        <input
                            id="create-password"
                            name="password"
                            type="password"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="create-confirm-password">
                            Confirm password
                        </label>

                        <input
                            id="create-confirm-password"
                            name="password_confirmation"
                            type="password"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="create-role">Role</label>

                        <select id="create-role" name="role">
                            <?php foreach ($roles as $role): ?>
                                <option
                                    value="<?= escape($role) ?>"
                                    <?= $createValues['role'] === $role
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= escape(displayRole($role)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <fieldset class="permission-fieldset">
                    <legend>Analyst sections</legend>

                    <?php foreach ($sections as $section): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="sections[]"
                                value="<?= escape($section) ?>"
                                <?= in_array(
                                    $section,
                                    $createValues['sections'],
                                    true
                                ) ? 'checked' : '' ?>
                            >
                            <?= escape(ucfirst($section)) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <label class="checkbox-label">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= $createValues['is_active']
                            ? 'checked'
                            : '' ?>
                    >
                    Account is active
                </label>

                <button type="submit" class="button button-primary">
                    Create account
                </button>
            </form>
        </section>

        <section class="panel user-list-panel">
            <p class="eyebrow">Existing accounts</p>
            <h2>Users</h2>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Sections</th>
                            <th>Last login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($users as $managedUser): ?>
                            <?php
                            $isCurrentUser =
                                $managedUser['id'] === $currentUser['id'];
                            ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= escape($managedUser['username']) ?>
                                    </strong>

                                    <small>
                                        <?= escape($managedUser['email']) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= escape(
                                        displayRole($managedUser['role'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= $managedUser['is_active']
                                        ? 'Active'
                                        : 'Disabled' ?>
                                </td>

                                <td>
                                    <?php if (
                                        $managedUser['permitted_sections'] === []
                                    ): ?>
                                        —
                                    <?php else: ?>
                                        <?= escape(
                                            implode(
                                                ', ',
                                                $managedUser[
                                                    'permitted_sections'
                                                ]
                                            )
                                        ) ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $managedUser['last_login_at']
                                            ?? 'Never'
                                    ) ?>
                                </td>

                                <td>
                                    <details>
                                        <summary>Edit</summary>

                                        <form
                                            method="post"
                                            class="edit-user-form"
                                        >
                                            <?= csrfInput() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="update"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $managedUser['id'] ?>"
                                            >

                                            <div class="form-group">
                                                <label>
                                                    Username
                                                    <input
                                                        name="username"
                                                        type="text"
                                                        value="<?= escape(
                                                            $managedUser[
                                                                'username'
                                                            ]
                                                        ) ?>"
                                                        required
                                                    >
                                                </label>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    Email
                                                    <input
                                                        name="email"
                                                        type="email"
                                                        value="<?= escape(
                                                            $managedUser[
                                                                'email'
                                                            ]
                                                        ) ?>"
                                                        required
                                                    >
                                                </label>
                                            </div>

                                            <?php if ($isCurrentUser): ?>
                                                <input
                                                    type="hidden"
                                                    name="role"
                                                    value="super_admin"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value="1"
                                                >

                                                <p class="muted">
                                                    You cannot remove your own
                                                    super-admin access.
                                                </p>
                                            <?php else: ?>
                                                <div class="form-group">
                                                    <label>
                                                        Role

                                                        <select name="role">
                                                            <?php foreach (
                                                                $roles as $role
                                                            ): ?>
                                                                <option
                                                                    value="<?= escape(
                                                                        $role
                                                                    ) ?>"
                                                                    <?= $managedUser[
                                                                        'role'
                                                                    ] === $role
                                                                        ? 'selected'
                                                                        : '' ?>
                                                                >
                                                                    <?= escape(
                                                                        displayRole(
                                                                            $role
                                                                        )
                                                                    ) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                </div>

                                                <label class="checkbox-label">
                                                    <input
                                                        type="checkbox"
                                                        name="is_active"
                                                        value="1"
                                                        <?= $managedUser[
                                                            'is_active'
                                                        ] ? 'checked' : '' ?>
                                                    >
                                                    Account is active
                                                </label>
                                            <?php endif; ?>

                                            <fieldset
                                                class="permission-fieldset"
                                            >
                                                <legend>
                                                    Analyst sections
                                                </legend>

                                                <?php foreach (
                                                    $sections as $section
                                                ): ?>
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            name="sections[]"
                                                            value="<?= escape(
                                                                $section
                                                            ) ?>"
                                                            <?= in_array(
                                                                $section,
                                                                $managedUser[
                                                                    'permitted_sections'
                                                                ],
                                                                true
                                                            ) ? 'checked' : '' ?>
                                                        >
                                                        <?= escape(
                                                            ucfirst($section)
                                                        ) ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </fieldset>

                                            <div class="form-group">
                                                <label>
                                                    New password
                                                    <input
                                                        name="password"
                                                        type="password"
                                                        minlength="10"
                                                        autocomplete="new-password"
                                                    >
                                                </label>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    Confirm new password
                                                    <input
                                                        name="password_confirmation"
                                                        type="password"
                                                        minlength="10"
                                                        autocomplete="new-password"
                                                    >
                                                </label>
                                            </div>

                                            <button
                                                type="submit"
                                                class="button button-primary"
                                            >
                                                Save changes
                                            </button>
                                        </form>

                                        <?php if (!$isCurrentUser): ?>
                                            <form
                                                method="post"
                                                class="delete-user-form"
                                                onsubmit="return confirm('Permanently delete this account?');"
                                            >
                                                <?= csrfInput() ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= $managedUser[
                                                        'id'
                                                    ] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="button button-danger"
                                                >
                                                    Delete account
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
