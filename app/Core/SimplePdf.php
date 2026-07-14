<?php

namespace App\Core;

final class SimplePdf
{
    private array $pages = [];
    private float $y = 800;
    private string $current = '';

    public function addTitle(string $text): void
    {
        $this->text($text, 42, 820, 16, true);
        $this->y = 790;
    }

    public function addPrintHeader(string $unit, string $title): void
    {
        $this->text($unit, 205, 820, 11, true);
        $this->text(mb_strtoupper($title, 'UTF-8'), 135, 798, 16, true);
        $this->y = 770;
    }

    public function addMeta(string $text): void
    {
        $this->text($text, 42, $this->y, 10);
        $this->y -= 20;
    }

    public function addTable(array $headers, array $rows): void
    {
        $this->line(42, $this->y + 8, 552, $this->y + 8);
        $this->writeRow($headers, true);
        $this->line(42, $this->y + 8, 552, $this->y + 8);
        foreach ($rows as $row) {
            $this->writeRow($row, false);
            if ($this->y < 70) {
                $this->newPage();
                $this->writeRow($headers, true);
                $this->line(42, $this->y + 8, 552, $this->y + 8);
            }
        }
        $this->line(42, $this->y + 8, 552, $this->y + 8);
    }

    public function output(): string
    {
        $this->finishPage();
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        foreach ($this->pages as $index => $_) $kids[] = (4 + ($index * 2)) . ' 0 R';
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        foreach ($this->pages as $index => $content) {
            $pageObj = 4 + ($index * 2);
            $contentObj = $pageObj + 1;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentObj 0 R >>";
            $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }

    private function writeRow(array $cells, bool $bold): void
    {
        $text = [];
        foreach ($cells as $cell) $text[] = $this->plain((string) $cell);
        $line = implode(' | ', $text);
        $this->text(mb_strimwidth($line, 0, 135, '...'), 42, $this->y, $bold ? 9 : 8, $bold);
        $this->y -= $bold ? 18 : 15;
    }

    private function text(string $text, float $x, float $y, int $size = 10, bool $bold = false): void
    {
        if ($this->current === '') $this->current = "q\n";
        $safe = $this->escape($this->plain($text));
        $this->current .= "BT /F1 $size Tf $x $y Td ($safe) Tj ET\n";
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void
    {
        if ($this->current === '') $this->current = "q\n";
        $this->current .= "$x1 $y1 m $x2 $y2 l S\n";
    }

    private function newPage(): void
    {
        $this->finishPage();
        $this->y = 800;
    }

    private function finishPage(): void
    {
        if ($this->current === '') return;
        $this->pages[] = $this->current . "Q";
        $this->current = '';
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function plain(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return trim(preg_replace('/\s+/', ' ', $ascii ?: $text));
    }
}
