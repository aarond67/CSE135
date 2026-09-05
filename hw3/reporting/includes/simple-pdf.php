<?php

declare(strict_types=1);

final class SimplePdf
{
    private array $commands = [];
    private float $cursorY = 754;

    public function title(string $text): void
    {
        $this->writeLine($text, 20, true);
        $this->cursorY -= 8;
    }

    public function heading(string $text): void
    {
        $this->cursorY -= 6;
        $this->writeLine($text, 13, true);
    }

    public function paragraph(string $text): void
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        foreach ($this->wrap($text, 92) as $line) {
            if ($this->cursorY < 52) {
                break;
            }
            $this->writeLine($line, 10);
        }

        $this->cursorY -= 4;
    }

    public function bar(string $label, float $value, float $maximum, string $display): void
    {
        if ($this->cursorY < 64) {
            return;
        }

        $safeMaximum = max($maximum, 1);
        $width = min(max($value / $safeMaximum, 0), 1) * 250;
        $this->writeAt($label, 46, $this->cursorY, 9);
        $this->commands[] = '0.88 0.91 0.90 rg 220 ' . ($this->cursorY - 3) . ' 250 10 re f';
        $this->commands[] = '0.12 0.42 0.31 rg 220 ' . ($this->cursorY - 3) . ' ' . $width . ' 10 re f';
        $this->writeAt($display, 482, $this->cursorY, 9);
        $this->cursorY -= 20;
    }

    public function row(array $cells): void
    {
        if ($this->cursorY < 48) {
            return;
        }

        $widths = [34, 20, 20, 20];
        $pieces = [];

        foreach (array_values($cells) as $index => $cell) {
            $pieces[] = substr((string) $cell, 0, $widths[$index] ?? 20);
        }

        $this->writeLine(implode(' | ', $pieces), 8);
    }

    public function output(): string
    {
        $stream = implode("\n", $this->commands) . "\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream"
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }

    private function writeLine(string $text, int $size, bool $bold = false): void
    {
        $this->writeAt($text, 46, $this->cursorY, $size, $bold);
        $this->cursorY -= $size + 5;
    }

    private function writeAt(
        string $text,
        float $x,
        float $y,
        int $size,
        bool $bold = false
    ): void {
        $font = $bold ? 'F2' : 'F1';
        $escaped = $this->escapeText($text);
        $this->commands[] = "0.10 0.14 0.12 rg BT /{$font} {$size} Tf {$x} {$y} Td ({$escaped}) Tj ET";
    }

    private function wrap(string $text, int $length): array
    {
        if ($text === '') {
            return [''];
        }

        return explode("\n", wordwrap($text, $length, "\n", true));
    }

    private function escapeText(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if (is_string($converted)) {
                $text = $converted;
            }
        }

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            $text
        );
    }
}
