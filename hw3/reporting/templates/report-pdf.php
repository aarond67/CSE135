<?php

declare(strict_types=1);

if (!isset($report, $range, $chartRows, $tableRows, $reportPdfCss)) {
    throw new RuntimeException('The report template is missing required data.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= escape($report['title']) ?></title>
    <style><?= $reportPdfCss ?></style>
</head>
<body>
    <header class="report-header">
        <p class="brand">Bad Decisions Analytics</p>
        <h1><?= escape($report['title']) ?></h1>
        <p class="date-range">
            <?= escape($range['start']) ?> through
            <?= escape($range['end']) ?> UTC
        </p>
    </header>

    <main>
        <section class="report-section question-section">
            <p class="section-label">Guiding question</p>
            <h2><?= escape($report['guiding_question']) ?></h2>
        </section>

        <section class="report-section chart-section">
            <p class="section-label"><?= escape($report['category_label']) ?></p>
            <h2><?= escape($chartTitle) ?></h2>
            <p class="section-note"><?= escape($chartNote) ?></p>

            <?php if ($chartRows === []): ?>
                <p class="empty-state">No chart data was recorded for these dates.</p>
            <?php else: ?>
                <table class="bar-chart" role="presentation">
                    <tbody>
                        <?php foreach ($chartRows as $row): ?>
                            <?php
                            $percentage = min(
                                max(((float) $row['value'] / $chartMaximum) * 100, 0),
                                100
                            );
                            ?>
                            <tr>
                                <th><?= escape((string) $row['label']) ?></th>
                                <td class="bar-cell">
                                    <div class="bar-track">
                                        <div
                                            class="bar-fill"
                                            style="width: <?= number_format($percentage, 2, '.', '') ?>%"
                                        >&nbsp;</div>
                                    </div>
                                </td>
                                <td class="bar-value">
                                    <?= escape((string) $row['display']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="report-section table-section">
            <h2><?= escape($tableTitle) ?></h2>

            <?php if ($tableRows === []): ?>
                <p class="empty-state">No table data was recorded for these dates.</p>
            <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <?php foreach ($tableHeaders as $heading): ?>
                                <th><?= escape((string) $heading) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableRows as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= escape((string) $cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="report-section comments-section">
            <p class="section-label">Analysis</p>
            <h2>Analyst comments</h2>
            <?php if ($comments === ''): ?>
                <p class="empty-state">No analyst comments were saved with this report.</p>
            <?php else: ?>
                <p class="analyst-comments">
                    <?= nl2br(escape($comments)) ?>
                </p>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        Generated from the published reporting data for the selected UTC dates.
    </footer>
</body>
</html>
