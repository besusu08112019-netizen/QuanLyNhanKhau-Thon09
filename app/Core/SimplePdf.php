<?php

namespace App\Core;

final class SimplePdf
{
    private const FONT_CANDIDATES = [
        __DIR__ . '/../../assets/fonts/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/tahoma.ttf',
    ];

    private array $pages = [];
    private float $y;
    private string $current = '';
    private string $orientation;
    private float $width;
    private float $height;
    private float $marginX;
    private array $usedChars = [];
    private ?array $font = null;

    public function __construct(string $orientation = 'portrait')
    {
        $this->orientation = strtolower($orientation) === 'landscape' ? 'landscape' : 'portrait';
        [$this->width, $this->height] = $this->orientation === 'landscape' ? [842.0, 595.0] : [595.0, 842.0];
        $this->marginX = $this->orientation === 'landscape' ? 30.0 : 42.0;
        $this->y = $this->height - 42.0;
        $this->font = $this->loadFont();
    }

    public function addTitle(string $text): void
    {
        $this->addPrintHeader('', $text);
    }

    public function addPrintHeader(string $unit, string $title): void
    {
        $unit = trim($unit) !== '' ? $unit : TenantConfig::unitName();
        $rightX = $this->orientation === 'landscape' ? 510 : 298;
        $this->text($unit, $this->marginX, $this->height - 28, 10, true);
        $this->text('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', $rightX, $this->height - 28, 10, true);
        $this->text('Độc lập - Tự do - Hạnh phúc', $rightX + 26, $this->height - 44, 9, true);
        $this->line($rightX + 55, $this->height - 50, $rightX + 185, $this->height - 50);
        $this->wrappedText(mb_strtoupper($title, 'UTF-8'), $this->marginX, $this->height - 78, $this->width - ($this->marginX * 2), 15, true, 'center');
        $this->y = $this->height - 118;
    }

    public function addMeta(string $text): void
    {
        foreach ($this->wrap($text, $this->width - ($this->marginX * 2), 9) as $line) {
            $this->text($line, $this->marginX, $this->y, 9);
            $this->y -= 14;
        }
    }

    public function addTable(array $headers, array $rows): void
    {
        $headers = array_values(array_map('strval', $headers));
        if ($headers === []) return;
        $usable = $this->width - ($this->marginX * 2);
        $count = count($headers);
        $colWidths = $this->tableColumnWidths($headers, $rows, $usable);
        $this->writeTableHeader($headers, $colWidths);
        foreach ($rows as $row) {
            $cells = array_values(array_map('strval', (array) $row));
            while (count($cells) < $count) $cells[] = '';
            $lines = [];
            $rowHeight = 18;
            for ($i = 0; $i < $count; $i++) {
                $colWidth = $colWidths[$i] ?? ($usable / max(1, $count));
                $lines[$i] = array_slice($this->wrap($cells[$i] ?? '', max(24, $colWidth - 6), 7), 0, 3);
                $rowHeight = max($rowHeight, 10 + (count($lines[$i]) * 9));
            }
            if ($this->y - $rowHeight < 52) {
                $this->newPage();
                $this->writeTableHeader($headers, $colWidths);
            }
            $this->writeTableRow($lines, $colWidths, $rowHeight, false);
        }
    }

    public function addSignatureBlock(string $title = 'Trưởng thôn'): void
    {
        if ($this->y < 130) $this->newPage();
        $x = $this->orientation === 'landscape' ? 575 : 390;
        $this->y -= 24;
        $this->text('............, ngày ..... tháng ..... năm ......', $x - 35, $this->y, 9);
        $this->y -= 20;
        $this->text($this->signatureTitle($title), $x, $this->y, 10, true);
        $this->y -= 64;
        $this->text('................................', $x, $this->y, 9);
    }

    public function output(): string
    {
        $this->finishPage();
        if (!$this->font) return $this->fallbackAsciiOutput();

        $font = $this->font;
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        foreach ($this->pages as $index => $_) $kids[] = (8 + ($index * 2)) . ' 0 R';
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';
        $objects[] = '<< /Type /Font /Subtype /Type0 /BaseFont /TenantUnicode /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 7 0 R >>';
        $objects[] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /TenantUnicode /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 5 0 R /W ' . $this->widthArray($font) . ' /CIDToGIDMap 6 0 R >>';
        $objects[] = "<< /Type /FontDescriptor /FontName /TenantUnicode /Flags 32 /FontBBox [{$font['bbox']}] /ItalicAngle 0 /Ascent {$font['ascent']} /Descent {$font['descent']} /CapHeight {$font['capHeight']} /StemV 80 /FontFile2 10 0 R >>";
        $objects[] = $this->stream($this->cidToGidMap($font));
        $objects[] = $this->stream($this->toUnicodeCMap());
        foreach ($this->pages as $index => $content) {
            $pageObj = 8 + ($index * 2);
            $contentObj = $pageObj + 1;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->width} {$this->height}] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentObj 0 R >>";
            $objects[] = $this->stream($content);
        }
        $objects[] = $this->stream($font['data']);
        return $this->assemble($objects);
    }

    private function writeTableHeader(array $headers, array $colWidths): void
    {
        $lines = [];
        $height = 20;
        foreach ($headers as $i => $header) {
            $colWidth = $colWidths[$i] ?? 48.0;
            $lines[$i] = array_slice($this->wrap($header, max(24, $colWidth - 6), 7), 0, 2);
            $height = max($height, 10 + (count($lines[$i]) * 9));
        }
        $this->writeTableRow($lines, $colWidths, $height, true);
    }

    private function writeTableRow(array $lines, array $colWidths, float $height, bool $bold): void
    {
        $x = $this->marginX;
        $top = $this->y;
        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            $colWidth = $colWidths[$i] ?? 48.0;
            $this->rect($x, $top - $height, $colWidth, $height);
            $ty = $top - 10;
            foreach ($lines[$i] as $line) {
                $this->text($line, $x + 3, $ty, 7, $bold);
                $ty -= 9;
            }
            $x += $colWidth;
        }
        $this->y -= $height;
    }

    private function tableColumnWidths(array $headers, array $rows, float $usable): array
    {
        $weights = [];
        $count = count($headers);
        $sample = array_slice($rows, 0, 24);
        foreach ($headers as $i => $header) {
            [$min, $max] = $this->columnWidthProfile($header);
            $longest = $this->textLength($header);
            foreach ($sample as $row) {
                $cells = array_values((array) $row);
                $longest = max($longest, $this->textLength((string) ($cells[$i] ?? '')));
            }
            $weights[$i] = max($min, min($max, max($min, $longest * 3.0)));
        }
        $total = array_sum($weights) ?: 1.0;
        $widths = [];
        foreach ($weights as $weight) $widths[] = $usable * ($weight / $total);
        if (array_sum($widths) <= $usable + 0.01) return $widths;
        return array_fill(0, max(1, $count), $usable / max(1, $count));
    }

    private function columnWidthProfile(string $header): array
    {
        $text = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header);
        if (preg_match('/^(stt|tt|#|no\.?|so tt)$/u', $text) || str_contains($text, 'stt')) return [10.0, 14.0];
        if (preg_match('/(so luong|tong|nam sinh|nam|tuoi|gioi tinh|muc|ty le|dien tich|san luong|so tien|da nop|con no|id|ma )/u', $text)) return [16.0, 24.0];
        if (preg_match('/(ngay|thang|thoi gian|han|trang thai|tinh trang|phan loai|loai|nhom|doi tuong|khu vuc|chi bo|to chuc)/u', $text)) return [24.0, 34.0];
        if (preg_match('/(ho va ten|ho ten|chu ho|ten ho|ten|nguoi|thanh vien|can bo|don vi)/u', $text)) return [34.0, 52.0];
        if (preg_match('/(dia chi|noi dung|ghi chu|ly do|mo ta|ket qua|hinh thuc|nguon nuoc|cong trinh|san pham|nganh nghe)/u', $text)) return [46.0, 70.0];
        return [24.0, 34.0];
    }

    private function textLength(string $text): int
    {
        $text = trim($text);
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private function text(string $text, float $x, float $y, int $size = 10, bool $bold = false): void
    {
        if ($this->current === '') $this->current = "q\n";
        $this->rememberChars($text);
        $hex = strtoupper(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')));
        $mode = $bold ? '2 Tr 0.25 w' : '0 Tr';
        $this->current .= "BT /F1 $size Tf $mode 1 0 0 1 $x $y Tm <$hex> Tj ET\n";
    }

    private function wrappedText(string $text, float $x, float $y, float $width, int $size, bool $bold, string $align): void
    {
        foreach ($this->wrap($text, $width, $size) as $line) {
            $lineX = $align === 'center' ? $x + max(0, ($width - $this->textWidth($line, $size)) / 2) : $x;
            $this->text($line, $lineX, $y, $size, $bold);
            $y -= $size + 4;
        }
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void
    {
        if ($this->current === '') $this->current = "q\n";
        $this->current .= "$x1 $y1 m $x2 $y2 l S\n";
    }

    private function rect(float $x, float $y, float $w, float $h): void
    {
        if ($this->current === '') $this->current = "q\n";
        $this->current .= "$x $y $w $h re S\n";
    }

    private function newPage(): void
    {
        $this->finishPage();
        $this->y = $this->height - 42.0;
    }

    private function finishPage(): void
    {
        if ($this->current === '') return;
        $this->pages[] = $this->current . "Q";
        $this->current = '';
    }

    private function wrap(string $text, float $maxWidth, int $size): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [''];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($candidate, $size) <= $maxWidth || $line === '') {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: [''];
    }

    private function textWidth(string $text, int $size): float
    {
        $font = $this->font;
        if (!$font) return mb_strwidth($text, 'UTF-8') * $size * 0.45;
        $units = 0;
        foreach ($this->codepoints($text) as $cp) $units += $font['widths'][$font['cmap'][$cp] ?? 0] ?? $font['avgWidth'];
        return $units * $size / max(1, $font['unitsPerEm']);
    }

    private function rememberChars(string $text): void
    {
        foreach ($this->codepoints($text) as $cp) {
            if ($cp >= 0 && $cp <= 0xFFFF) $this->usedChars[$cp] = true;
        }
    }

    private function codepoints(string $text): array
    {
        $points = [];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $u = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));
            $points[] = (int) ($u[1] ?? 0);
        }
        return $points;
    }

    private function loadFont(): ?array
    {
        foreach (self::FONT_CANDIDATES as $candidate) {
            if (!is_file($candidate)) continue;
            $data = file_get_contents($candidate);
            if ($data !== false) return $this->parseTrueType($data);
        }
        return null;
    }

    private function parseTrueType(string $data): array
    {
        $tables = $this->ttfTables($data);
        $head = $tables['head'];
        $hhea = $tables['hhea'];
        $maxp = $tables['maxp'];
        $units = $this->u16($data, $head + 18);
        $bbox = implode(' ', [$this->i16($data, $head + 36), $this->i16($data, $head + 38), $this->i16($data, $head + 40), $this->i16($data, $head + 42)]);
        $ascent = $this->i16($data, $hhea + 4);
        $descent = $this->i16($data, $hhea + 6);
        $numMetrics = $this->u16($data, $hhea + 34);
        $numGlyphs = $this->u16($data, $maxp + 4);
        $widths = $this->glyphWidths($data, $tables['hmtx'], $numMetrics, $numGlyphs);
        $avg = (int) round(array_sum(array_slice($widths, 0, min(256, count($widths)))) / max(1, min(256, count($widths))));
        return ['data' => $data, 'unitsPerEm' => $units, 'bbox' => $bbox, 'ascent' => $ascent, 'descent' => $descent, 'capHeight' => $ascent, 'widths' => $widths, 'avgWidth' => $avg, 'cmap' => $this->cmap($data, $tables['cmap'])];
    }

    private function ttfTables(string $data): array
    {
        $num = $this->u16($data, 4);
        $tables = [];
        for ($i = 0; $i < $num; $i++) {
            $off = 12 + ($i * 16);
            $tables[substr($data, $off, 4)] = $this->u32($data, $off + 8);
        }
        return $tables;
    }

    private function glyphWidths(string $data, int $offset, int $numMetrics, int $numGlyphs): array
    {
        $widths = [];
        $last = 500;
        for ($i = 0; $i < $numGlyphs; $i++) {
            if ($i < $numMetrics) $last = $this->u16($data, $offset + ($i * 4));
            $widths[$i] = $last;
        }
        return $widths;
    }

    private function cmap(string $data, int $offset): array
    {
        $count = $this->u16($data, $offset + 2);
        $chosen = 0;
        for ($i = 0; $i < $count; $i++) {
            $record = $offset + 4 + ($i * 8);
            $platform = $this->u16($data, $record);
            $encoding = $this->u16($data, $record + 2);
            $sub = $offset + $this->u32($data, $record + 4);
            if ($this->u16($data, $sub) === 4 && ($platform === 3 || $platform === 0) && ($encoding === 1 || $encoding === 10 || $platform === 0)) {
                $chosen = $sub;
                break;
            }
        }
        if (!$chosen) return [];
        $segCount = intdiv($this->u16($data, $chosen + 6), 2);
        $endOff = $chosen + 14;
        $startOff = $endOff + (2 * $segCount) + 2;
        $deltaOff = $startOff + (2 * $segCount);
        $rangeOff = $deltaOff + (2 * $segCount);
        $map = [];
        for ($i = 0; $i < $segCount; $i++) {
            $end = $this->u16($data, $endOff + ($i * 2));
            $start = $this->u16($data, $startOff + ($i * 2));
            $delta = $this->i16($data, $deltaOff + ($i * 2));
            $range = $this->u16($data, $rangeOff + ($i * 2));
            if ($start === 0xFFFF && $end === 0xFFFF) continue;
            for ($cp = $start; $cp <= $end && $cp <= 0xFFFF; $cp++) {
                if ($range === 0) {
                    $gid = ($cp + $delta) & 0xFFFF;
                } else {
                    $glyph = $this->u16($data, $rangeOff + ($i * 2) + $range + (($cp - $start) * 2));
                    $gid = $glyph ? (($glyph + $delta) & 0xFFFF) : 0;
                }
                if ($gid) $map[$cp] = $gid;
            }
        }
        return $map;
    }

    private function cidToGidMap(array $font): string
    {
        $stream = '';
        for ($cp = 0; $cp <= 0xFFFF; $cp++) $stream .= pack('n', $font['cmap'][$cp] ?? 0);
        return $stream;
    }

    private function widthArray(array $font): string
    {
        $parts = [];
        foreach (array_keys($this->usedChars + [32 => true]) as $cp) {
            $parts[] = $cp . ' [' . (int) ($font['widths'][$font['cmap'][$cp] ?? 0] ?? $font['avgWidth']) . ']';
        }
        sort($parts, SORT_NATURAL);
        return '[' . implode(' ', $parts) . ']';
    }

    private function toUnicodeCMap(): string
    {
        $chars = array_keys($this->usedChars + [32 => true]);
        sort($chars, SORT_NUMERIC);
        $out = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /TenantUnicode-UTF16 def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
        foreach (array_chunk($chars, 80) as $chunk) {
            $out .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $cp) {
                $hex = strtoupper(str_pad(dechex($cp), 4, '0', STR_PAD_LEFT));
                $out .= "<$hex> <$hex>\n";
            }
            $out .= "endbfchar\n";
        }
        return $out . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    }

    private function stream(string $data): string
    {
        return '<< /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
    }

    private function assemble(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }

    private function fallbackAsciiOutput(): string
    {
        return $this->assemble([
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->width} {$this->height}] /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>",
            $this->stream('BT /F1 12 Tf 42 780 Td (PDF Unicode font unavailable on server.) Tj ET'),
        ]);
    }

    private function signatureTitle(string $text): string
    {
        $plain = preg_replace('/[:：].*$/u', '', $text);
        $plain = preg_replace('/\.{2,}.*/u', '', (string) $plain);
        $plain = trim((string) $plain);
        return $plain !== '' ? $plain : 'Trưởng thôn';
    }

    private function u16(string $data, int $offset): int
    {
        $v = unpack('n', substr($data, $offset, 2));
        return (int) ($v[1] ?? 0);
    }

    private function i16(string $data, int $offset): int
    {
        $v = $this->u16($data, $offset);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private function u32(string $data, int $offset): int
    {
        $v = unpack('N', substr($data, $offset, 4));
        return (int) ($v[1] ?? 0);
    }
}
