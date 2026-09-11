<?php

namespace App\Core;

final class SimpleXlsx
{
    private array $strings = [];
    private array $stringIndex = [];

    public function output(array $report): string
    {
        $rows = $this->rows($report);
        $sheet = $this->worksheet($rows);
        $shared = $this->sharedStrings();
        return $this->zip([
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rels(),
            'docProps/app.xml' => $this->appXml(),
            'docProps/core.xml' => $this->coreXml((string) ($report['title'] ?? 'Báo cáo')),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(),
            'xl/styles.xml' => $this->styles(),
            'xl/sharedStrings.xml' => $shared,
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    private function rows(array $report): array
    {
        $rows = [];
        $rows[] = [['v' => (string) ($report['title'] ?? 'Báo cáo'), 's' => 1]];
        $rows[] = [['v' => 'Thời gian xuất'], ['v' => date('d/m/Y H:i:s')]];
        foreach (($report['filters'] ?? []) as $key => $value) {
            if ($value !== null && $value !== '') $rows[] = [['v' => 'Filter: ' . $key], ['v' => (string) $value]];
        }
        $rows[] = [];
        $rows[] = array_map(fn($header) => ['v' => (string) $header, 's' => 2], $report['headers'] ?? []);
        foreach (($report['rows'] ?? []) as $row) {
            $rows[] = array_map(fn($cell) => ['v' => $cell], (array) $row);
        }
        $rows[] = [];
        $rows[] = [['v' => 'Tổng số dòng', 's' => 2], ['v' => (int) ($report['totalRows'] ?? count($report['rows'] ?? []))]];
        return $rows;
    }

    private function worksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData>';
        foreach ($rows as $r => $row) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach ($row as $c => $cell) {
                $ref = $this->cellRef($c, $r + 1);
                $style = isset($cell['s']) ? ' s="' . (int) $cell['s'] . '"' : '';
                $value = $cell['v'] ?? '';
                if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value))) {
                    $xml .= '<c r="' . $ref . '"' . $style . '><v>' . htmlspecialchars((string) $value, ENT_XML1, 'UTF-8') . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="s"' . $style . '><v>' . $this->sharedString((string) $value) . '</v></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private function sharedString(string $value): int
    {
        if (!array_key_exists($value, $this->stringIndex)) {
            $this->stringIndex[$value] = count($this->strings);
            $this->strings[] = $value;
        }
        return $this->stringIndex[$value];
    }

    private function sharedStrings(): string
    {
        $items = '';
        foreach ($this->strings as $string) {
            $items .= '<si><t xml:space="preserve">' . htmlspecialchars($string, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($this->strings) . '" uniqueCount="' . count($this->strings) . '">' . $items . '</sst>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Bao cao" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="12"/><name val="Arial"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="1" applyFont="1" applyBorder="1"/></cellXfs></styleSheet>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>QuanLyNhanKhau</Application></Properties>';
    }

    private function coreXml(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . htmlspecialchars($title, ENT_XML1, 'UTF-8') . '</dc:title><dc:creator>QuanLyNhanKhau</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
    }

    private function cellRef(int $col, int $row): string
    {
        $name = '';
        $col++;
        while ($col > 0) {
            $mod = ($col - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $col = intdiv($col - $mod - 1, 26);
        }
        return $name . $row;
    }

    private function zip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        foreach ($files as $name => $data) {
            $crc = crc32($data);
            if ($crc < 0) $crc += 0x100000000;
            $size = strlen($data);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0) . $name;
            $local .= $localHeader . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localHeader) + $size;
        }
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($local), 0);
        return $local . $central . $end;
    }
}
