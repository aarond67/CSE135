<?php

declare(strict_types=1);

function reportDefinitions(): array
{
    return [
        'technology-overview' => [
            'report_key' => 'technology-overview',
            'title' => 'Visitor Technology',
            'category' => 'technology',
            'guiding_question' =>
                'Which browsers, screen sizes, and network conditions should we support first?',
            'path' => '/reports/technology.php'
        ],
        'performance-overview' => [
            'report_key' => 'performance-overview',
            'title' => 'Website Performance',
            'category' => 'performance',
            'guiding_question' =>
                'Which pages are loading slowly, and where should performance work be focused?',
            'path' => '/reports/performance.php'
        ],
        'behavior-overview' => [
            'report_key' => 'behavior-overview',
            'title' => 'Shopping Behavior',
            'category' => 'behavior',
            'guiding_question' =>
                'How far are sessions moving through the shopping flow, and where should we investigate?',
            'path' => '/reports/behavior.php'
        ]
    ];
}

function findReportDefinition(string $reportKey): ?array
{
    return reportDefinitions()[$reportKey] ?? null;
}

function findSavedReport(string $reportKey): ?array
{
    $statement = database()->prepare(
        'SELECT
            id,
            report_key,
            title,
            category,
            guiding_question,
            analyst_comments,
            created_by,
            is_published,
            created_at,
            updated_at
         FROM saved_reports
         WHERE report_key = :report_key
         LIMIT 1'
    );

    $statement->execute(['report_key' => $reportKey]);
    $report = $statement->fetch();

    if (!$report) {
        return null;
    }

    $report['id'] = (int) $report['id'];
    $report['is_published'] = (bool) $report['is_published'];

    return $report;
}

function ensureSavedReport(array $definition, ?int $creatorId = null): array
{
    $statement = database()->prepare(
        'INSERT INTO saved_reports (
            report_key,
            title,
            category,
            guiding_question,
            created_by,
            is_published
         ) VALUES (
            :report_key,
            :title,
            :category,
            :guiding_question,
            :created_by,
            FALSE
         )
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            category = VALUES(category),
            guiding_question = VALUES(guiding_question)'
    );

    $statement->execute([
        'report_key' => $definition['report_key'],
        'title' => $definition['title'],
        'category' => $definition['category'],
        'guiding_question' => $definition['guiding_question'],
        'created_by' => $creatorId
    ]);

    $saved = findSavedReport($definition['report_key']);

    if ($saved === null) {
        throw new RuntimeException('The report record could not be created.');
    }

    return $saved;
}

function canEditReport(array $user, string $category): bool
{
    return $user['role'] === 'super_admin' ||
        (
            $user['role'] === 'analyst' &&
            userCanAccessSection($user, $category)
        );
}

function requireSavedReportAccess(string $reportKey): array
{
    $definition = findReportDefinition($reportKey);

    if ($definition === null) {
        abortRequest(404, 'Not Found', 'The requested report does not exist.');
    }

    $user = requireLogin();
    $saved = findSavedReport($reportKey);

    if ($user['role'] === 'viewer') {
        if ($saved === null || !$saved['is_published']) {
            abortRequest(
                403,
                'Forbidden',
                'This report is not available to viewer accounts.'
            );
        }
    } elseif (!canEditReport($user, $definition['category'])) {
        abortRequest(
            403,
            'Forbidden',
            'You do not have permission to access this report category.'
        );
    }

    if ($saved === null) {
        $saved = ensureSavedReport($definition, (int) $user['id']);
    }

    return [$user, $definition, $saved];
}

function saveReportFromPost(
    array $user,
    array $definition,
    string $redirectPath
): never {
    if (!canEditReport($user, $definition['category'])) {
        abortRequest(403, 'Forbidden', 'Viewer accounts cannot change reports.');
    }

    requireValidCsrfToken();

    $comments = $_POST['analyst_comments'] ?? '';

    if (!is_string($comments)) {
        $comments = '';
    }

    $comments = trim($comments);

    if (strlen($comments) > 5000) {
        setFlashMessage('error', 'Analyst comments cannot exceed 5,000 characters.');
        redirect($redirectPath);
    }

    $published = ($_POST['is_published'] ?? '') === '1';

    try {
        ensureSavedReport($definition, (int) $user['id']);

        $statement = database()->prepare(
            'UPDATE saved_reports
             SET analyst_comments = :analyst_comments,
                 created_by = :created_by,
                 is_published = :is_published
             WHERE report_key = :report_key'
        );

        $statement->execute([
            'analyst_comments' => $comments,
            'created_by' => (int) $user['id'],
            'is_published' => $published ? 1 : 0,
            'report_key' => $definition['report_key']
        ]);

        setFlashMessage(
            'success',
            $published
                ? 'The report was saved and published for viewers.'
                : 'The report was saved as an analyst-only draft.'
        );
    } catch (Throwable $error) {
        error_log('[Saved Report] ' . $error->getMessage());
        setFlashMessage('error', 'The report could not be saved.');
    }

    redirect($redirectPath);
}

function reportsForUser(array $user): array
{
    $reports = [];

    foreach (reportDefinitions() as $definition) {
        $saved = findSavedReport($definition['report_key']);

        if ($user['role'] === 'viewer') {
            if ($saved === null || !$saved['is_published']) {
                continue;
            }
        } elseif (!canEditReport($user, $definition['category'])) {
            continue;
        }

        $reports[] = [
            ...$definition,
            'is_published' => (bool) ($saved['is_published'] ?? false),
            'updated_at' => $saved['updated_at'] ?? null
        ];
    }

    return $reports;
}

function homePathForUser(array $user): string
{
    return $user['role'] === 'viewer'
        ? '/reports/index.php'
        : '/dashboard.php';
}

function displayUserRole(string $role): string
{
    return ucwords(str_replace('_', ' ', $role));
}
