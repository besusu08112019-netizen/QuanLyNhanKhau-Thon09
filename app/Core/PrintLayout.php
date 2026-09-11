<?php

namespace App\Core;

final class PrintLayout
{
    public static function documentOpen(string $orientation = 'portrait'): string
    {
        $orientation = self::orientation($orientation);
        return '<html><head><meta charset="utf-8"><style>' . self::styles($orientation) . '</style></head><body class="report-print-' . $orientation . '">';
    }

    public static function documentClose(): string
    {
        return '</body></html>';
    }

    public static function styles(string $orientation = 'portrait'): string
    {
        $orientation = self::orientation($orientation);
        $page = $orientation === 'landscape'
            ? '@page{size:A4 landscape;margin:14mm 14mm 14mm 18mm}'
            : '@page{size:A4 portrait;margin:20mm 16mm 18mm 22mm}';

        return $page
            . 'body{font-family:"Times New Roman",Arial,sans-serif;color:#111;font-size:13pt;line-height:1.32;margin:0;background:#fff}'
            . '.report-print-document{width:100%;max-width:100%}'
            . '.report-print-masthead{display:grid;grid-template-columns:minmax(58mm,.9fr) minmax(92mm,1.15fr);gap:14mm;align-items:start;margin-bottom:12mm}'
            . '.report-print-landscape .report-print-masthead{grid-template-columns:minmax(70mm,.9fr) minmax(122mm,1.15fr);gap:18mm}'
            . '.report-print-agency{text-align:center}'
            . '.report-print-agency-primary{font-weight:700;text-transform:uppercase;font-size:12.5pt}'
            . '.report-print-agency-secondary{font-size:12pt;font-weight:700;margin-top:2px}'
            . '.report-print-national{text-align:center}'
            . '.report-print-national-title{font-weight:700;text-transform:uppercase;font-size:12.5pt;margin:0}'
            . '.report-print-national-subtitle{display:inline-block;border-bottom:1px solid #111;font-weight:700;font-size:13pt;margin:2px 0 0;padding-bottom:2px}'
            . '.report-print-title{text-align:center;text-transform:uppercase;font-size:16pt;font-weight:700;margin:0 auto 10mm;max-width:180mm}'
            . '.report-print-landscape .report-print-title{max-width:210mm;margin-bottom:7mm}'
            . '.report-print-meta{margin:0 0 8mm;line-height:1.45}'
            . '.report-print-meta div{margin-bottom:2px}'
            . '.report-print-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:10.5pt;margin-top:4mm}'
            . '.report-print-table thead{display:table-header-group}'
            . '.report-print-table tr{page-break-inside:avoid;break-inside:avoid}'
            . '.report-print-table td,.report-print-table th{border:1px solid #374151;padding:4pt 5pt;vertical-align:top;word-break:break-word;overflow-wrap:anywhere}'
            . '.report-print-table th{font-weight:700;background:#eef2f7;text-align:center;vertical-align:middle}'
            . '.report-print-empty{text-align:center;color:#666;padding:14pt!important}'
            . '.report-print-summary{margin-top:7mm;page-break-inside:avoid;break-inside:avoid}'
            . '.report-print-summary-title{font-weight:700;margin-bottom:3mm}'
            . '.report-print-summary-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:3px 14px}'
            . '.report-print-signature{margin-top:12mm;page-break-inside:avoid;break-inside:avoid}'
            . '.report-print-date{margin:0 0 4mm auto;width:82mm;text-align:right;font-style:italic}'
            . '.report-print-signatures{display:flex;justify-content:flex-end;text-align:center;font-weight:700}'
            . '.report-print-signature-box{width:70mm}'
            . '.report-print-signature-title{text-transform:uppercase}'
            . '.report-print-signature-note{font-weight:400;margin-top:4mm}'
            . '.report-print-signature-space{height:28mm}'
            . '.report-print-compact{font-size:12pt}'
            . '.report-print-compact .report-print-table{font-size:10pt}'
            . '.report-print-dense{font-size:11pt}'
            . '.report-print-dense .report-print-table{font-size:9pt}'
            . '@media print{html,body{width:auto;height:auto}.report-print-document{width:100%;max-width:100%}}';
    }

    public static function headerHtml(string $title, array $agencyParts): string
    {
        $commune = mb_strtoupper(ExportEncoding::text((string) ($agencyParts['commune'] ?? '')), 'UTF-8');
        $hamlet = ExportEncoding::text((string) ($agencyParts['hamlet'] ?? ''));
        $title = mb_strtoupper(ExportEncoding::text($title !== '' ? $title : "B\u{00E1}o c\u{00E1}o"), 'UTF-8');

        return '<main class="report-print-document">'
            . '<div class="report-print-masthead">'
            . '<div class="report-print-agency"><div class="report-print-agency-primary">' . ExportEncoding::html($commune) . '</div><div class="report-print-agency-secondary">' . ExportEncoding::html($hamlet) . '</div></div>'
            . '<div class="report-print-national"><div class="report-print-national-title">C&#7896;NG H&#210;A X&#195; H&#7896;I CH&#7910; NGH&#296;A VI&#7878;T NAM</div><div class="report-print-national-subtitle">&#272;&#7897;c l&#7853;p - T&#7921; do - H&#7841;nh ph&#250;c</div></div>'
            . '</div><div class="report-print-title">' . ExportEncoding::html($title) . '</div>';
    }

    public static function metaHtml(array $lines): string
    {
        $lines = array_values(array_filter(array_map(static fn($line) => trim((string) $line), $lines), static fn(string $line): bool => $line !== ''));
        if (!$lines) return '';
        $html = '<section class="report-print-meta">';
        foreach ($lines as $line) $html .= '<div>' . ExportEncoding::html($line) . '</div>';
        return $html . '</section>';
    }

    public static function tableHtml(array $headers, array $rows): string
    {
        $headers = array_values($headers ?: ["N\u{1ED9}i dung"]);
        $html = '<table class="report-print-table"><thead><tr>';
        foreach ($headers as $header) $html .= '<th>' . ExportEncoding::html($header) . '</th>';
        $html .= '</tr></thead><tbody>';
        if (!$rows) {
            $html .= '<tr><td class="report-print-empty" colspan="' . count($headers) . '">Kh&#244;ng c&#243; d&#7919; li&#7879;u</td></tr>';
        } else {
            foreach ($rows as $row) {
                $cells = array_values(is_array($row) ? $row : [$row]);
                $html .= '<tr>';
                foreach ($headers as $index => $_) $html .= '<td>' . ExportEncoding::html($cells[$index] ?? '') . '</td>';
                $html .= '</tr>';
            }
        }
        return $html . '</tbody></table>';
    }

    public static function summaryHtml(?array $summary): string
    {
        if (!$summary) return '';
        $rows = [];
        foreach ($summary as $label => $value) {
            $label = trim((string) $label);
            $value = trim((string) $value);
            if ($label !== '' && $value !== '') $rows[] = [$label, $value];
        }
        if (!$rows) return '';
        $html = '<section class="report-print-summary"><div class="report-print-summary-title">T&#7893;ng h&#7907;p</div><div class="report-print-summary-list">';
        foreach ($rows as [$label, $value]) $html .= '<div><strong>' . ExportEncoding::html($label) . ':</strong> ' . ExportEncoding::html($value) . '</div>';
        return $html . '</div></section>';
    }

    public static function signatureHtml(string $locality = '', string $title = ''): string
    {
        $locality = trim($locality) !== '' ? ExportEncoding::text($locality) : "H\u{1ED3}ng Phong";
        $title = trim($title) !== '' ? ExportEncoding::text($title) : "Tr\u{01B0}\u{1EDF}ng th\u{00F4}n";
        return '<section class="report-print-signature"><div class="report-print-date">' . ExportEncoding::html($locality) . ', ng&#224;y ..... th&#225;ng ..... n&#259;m ......</div>'
            . '<div class="report-print-signatures"><div class="report-print-signature-box"><div class="report-print-signature-title">' . ExportEncoding::html($title) . '</div><div class="report-print-signature-note">(K&#253;, ghi r&#245; h&#7885; t&#234;n)</div><div class="report-print-signature-space"></div></div></div></section></main>';
    }

    private static function orientation(string $orientation): string
    {
        $orientation = strtolower(trim($orientation));
        return $orientation === 'landscape' ? 'landscape' : 'portrait';
    }
}

