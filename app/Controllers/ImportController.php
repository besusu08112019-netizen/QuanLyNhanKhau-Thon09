<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Core\Encoding;
use App\Core\TenantContext;
use App\Models\Citizen;
use App\Models\Household;

final class ImportController extends BaseController
{
    private Household $households;
    private Citizen $citizens;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->households = new Household();
        $this->citizens = new Citizen();
    }

    public function template(): void
    {
        $this->requirePermission('import', 'read');
        $type = (string) ($_GET['type'] ?? $this->input('type', 'person'));
        $fileName = match ($type) {
            'household', 'households', 'ho-dan', 'hodan' => 'Mau_Import_HoDan.xlsx',
            default => 'Mau_Import_NhanKhau.xlsx',
        };
        $path = BASE_PATH . '/sample-data/' . $fileName;
        if (!is_file($path)) throw new \RuntimeException('Chưa có file mẫu Import Excel: ' . $fileName);
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }

    public function preview(): void
    {
        $user = $this->requirePermission('import', 'import');
        $type = $this->type();
        $rows = $this->readRows($type);
        $result = $this->validateRows($type, $rows);
        $this->audit($user, 'import', 'read', 'Kiểm tra file import', null, ['type' => $type, 'total' => count($rows), 'errors' => count($result['errors']), 'warnings' => count($result['warnings'] ?? [])]);
        $this->ok($result + ['type' => $type]);
    }

    public function process(): void
    {
        $user = $this->requirePermission('import', 'import');
        $type = $this->type();
        $mode = (string) ($_POST['mode'] ?? $this->input('mode', 'skip'));
        $rows = $this->readRows($type);
        $result = $this->validateRows($type, $rows);
        $success = 0;
        $skipped = 0;
        $errors = $result['errors'];
        $rolledBack = false;

        if (!empty($errors)) {
            $payload = ['type' => $type, 'total' => count($rows), 'success' => 0, 'skipped' => 0, 'failed' => count($errors), 'rolledBack' => false, 'warnings' => $result['warnings'] ?? [], 'errors' => $errors];
            $this->audit($user, 'import', 'import', 'Import dữ liệu không hợp lệ', null, $payload, 'WARN');
            $this->ok($payload);
            return;
        }

        $db = Database::pdo();
        $db->beginTransaction();
        try {
            foreach ($result['validRows'] as $item) {
                try {
                    if ($type === 'household') {
                        $existing = $this->households->findByCode((string) $item['data']['householdCode']);
                        if ($existing && $mode === 'update') {
                            $this->households->update((int) $existing['id'], $item['data'], (int) $user['id']);
                        } elseif ($existing) {
                            $skipped++;
                            continue;
                        } else {
                            $this->households->create($item['data'], (int) $user['id']);
                        }
                    } else {
                        $existing = !empty($item['data']['identityNumber']) ? $this->citizens->findByIdentity((string) $item['data']['identityNumber']) : null;
                        if ($existing) {
                            $this->citizens->update((int) $existing['id'], $item['data'], (int) $user['id']);
                        } else {
                            $this->citizens->create($item['data'], (int) $user['id']);
                        }
                    }
                    $success++;
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $item['row'], 'message' => $e->getMessage()];
                }
            }

            if (!empty($errors)) {
                $db->rollBack();
                $rolledBack = true;
                $success = 0;
            } else {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $rolledBack = true;
            $success = 0;
            $errors[] = ['row' => null, 'message' => $e->getMessage()];
        }

        $payload = ['type' => $type, 'total' => count($rows), 'success' => $success, 'skipped' => $skipped, 'failed' => count($errors), 'rolledBack' => $rolledBack, 'warnings' => $result['warnings'] ?? [], 'errors' => $errors];
        $this->audit($user, 'import', 'import', 'Import dữ liệu', null, $payload, count($errors) ? 'WARN' : 'INFO');
        $this->ok($payload);
    }

    private function type(): string
    {
        $type = (string) ($_POST['type'] ?? $this->input('type', 'household'));
        if (!in_array($type, ['household', 'person'], true)) throw new \RuntimeException('Loại dữ liệu import không hợp lệ');
        return $type;
    }

    private function readRows(string $type): array
    {
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            $this->fail('Vui lòng chọn file CSV hoặc XLSX', 422);
        }
        if (($_FILES['file']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Invalid import upload');
        }
        if ((int) ($_FILES['file']['size'] ?? 0) <= 0 || (int) ($_FILES['file']['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Import file size is invalid');
        }
        $name = strtolower((string) $_FILES['file']['name']);
        $mime = mime_content_type($_FILES['file']['tmp_name']) ?: 'application/octet-stream';
        if (str_ends_with($name, '.csv') && in_array($mime, ['text/plain','text/csv','application/csv','application/vnd.ms-excel'], true)) return $this->readCsv($_FILES['file']['tmp_name']);
        if (str_ends_with($name, '.xlsx') && in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'], true)) return $this->readXlsx($_FILES['file']['tmp_name'], $type);
        throw new \RuntimeException('Chỉ hỗ trợ file CSV hoặc XLSX');
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) throw new \RuntimeException('Không đọc được file CSV');
        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (!array_filter($values, fn($value) => trim((string) $value) !== '')) continue;
            $rows[] = ['row' => $line, 'data' => $this->mapRow($headers, $values)];
            if (count($rows) > 5000) throw new \RuntimeException('Import file has too many rows');
        }
        fclose($handle);
        return $rows;
    }

    private function readXlsx(string $path, string $importType): array
    {
        if (!class_exists('ZipArchive')) throw new \RuntimeException('Hosting chưa bật ZipArchive để đọc file XLSX');
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \RuntimeException('Không mở được file XLSX');
        $worksheetName = $this->worksheetName($zip, $importType);
        $this->assertZipEntrySafe($zip, $worksheetName, 15 * 1024 * 1024);
        $this->assertZipEntrySafe($zip, 'xl/sharedStrings.xml', 10 * 1024 * 1024, false);
        $shared = $this->sharedStrings($zip);
        $xml = $zip->getFromName($worksheetName);
        $zip->close();
        if ($xml === false) throw new \RuntimeException('File XLSX chưa có sheet dữ liệu đầu tiên');
        if (strlen($xml) > 15 * 1024 * 1024) throw new \RuntimeException('XLSX worksheet is too large');
        $sheet = simplexml_load_string($this->stripSpreadsheetNamespaces($xml), 'SimpleXMLElement', LIBXML_NONET);
        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $line = (int) $row['r'];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = $this->columnIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');
                if ($type === 's') $value = $shared[(int) $value] ?? '';
                if ($type === 'inlineStr') $value = (string) ($cell->is->t ?? '');
                $value = $this->cleanImportedText($value);
                if ($value !== '') $matrix[$line][$col] = $value;
            }
        }
        if (!$matrix) return [];
        ksort($matrix);
        $headerLine = $this->findHeaderLine($matrix, $importType);
        if ($headerLine === null) {
            throw new \RuntimeException('File XLSX chua co dong tieu de dung mau');
        }
        $headerCells = $matrix[$headerLine] ?? [];
        $lastColumn = $headerCells ? max(array_keys($headerCells)) : -1;
        $headers = [];
        for ($index = 0; $index <= $lastColumn; $index++) $headers[] = $headerCells[$index] ?? '';
        $rows = [];
        foreach ($matrix as $line => $cells) {
            if ($line === $headerLine) continue;
            $values = [];
            for ($index = 0; $index < count($headers); $index++) $values[] = $cells[$index] ?? '';
            if (!array_filter($values, fn($value) => trim((string) $value) !== '')) continue;
            $rows[] = ['row' => $line, 'data' => $this->mapRow($headers, $values)];
            if (count($rows) > 5000) throw new \RuntimeException('Import file has too many rows');
        }
        return $rows;
    }

    private function stripSpreadsheetNamespaces(string $xml): string
    {
        $xml = preg_replace('/(<\/?)[A-Za-z0-9_\-]+:/', '$1', $xml) ?? $xml;
        $xml = preg_replace('/\s+[A-Za-z0-9_\-]+:([A-Za-z0-9_\-]+)=/', ' $1=', $xml) ?? $xml;
        $xml = preg_replace('/\s+xmlns(:[A-Za-z0-9_\-]+)?="[^"]*"/', '', $xml) ?? $xml;
        return $xml;
    }

    private function worksheetName(\ZipArchive $zip, string $importType): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) return 'xl/worksheets/sheet1.xml';

        $workbook = simplexml_load_string($this->stripSpreadsheetNamespaces($workbookXml), 'SimpleXMLElement', LIBXML_NONET);
        $rels = simplexml_load_string($this->stripSpreadsheetNamespaces($relsXml), 'SimpleXMLElement', LIBXML_NONET);
        if (!$workbook || !$rels) return 'xl/worksheets/sheet1.xml';

        $targets = [];
        foreach ($rels->Relationship as $rel) {
            $id = (string) $rel['Id'];
            $target = (string) $rel['Target'];
            if ($id === '' || $target === '') continue;
            if (str_starts_with($target, '/')) {
                $target = ltrim($target, '/');
            } elseif (!str_starts_with($target, 'xl/')) {
                $target = 'xl/' . ltrim($target, '/');
            }
            $targets[$id] = $target;
        }

        $needles = $importType === 'household'
            ? ['hodan', 'ho dan', 'household']
            : ['nhankhau', 'nhan khau', 'person', 'citizen'];
        $fallback = 'xl/worksheets/sheet1.xml';

        foreach ($workbook->sheets->sheet as $sheet) {
            $id = (string) ($sheet['id'] ?? '');
            $name = $this->headerKey((string) ($sheet['name'] ?? ''));
            if (isset($targets[$id]) && $fallback === 'xl/worksheets/sheet1.xml') {
                $fallback = $targets[$id];
            }
            foreach ($needles as $needle) {
                if (str_contains($name, $needle) && isset($targets[$id])) {
                    return $targets[$id];
                }
            }
        }

        return $fallback;
    }

    private function findHeaderLine(array $matrix, string $importType): ?int
    {
        $required = $importType === 'household'
            ? ['householdCode', 'address']
            : ['householdCode', 'fullName', 'dateOfBirth'];
        $aliases = $this->aliases();
        $bestLine = null;
        $bestScore = 0;

        foreach ($matrix as $line => $cells) {
            $fields = [];
            foreach ($cells as $value) {
                $key = $this->headerKey((string) $value);
                foreach ($aliases as $field => $names) {
                    if (in_array($key, $names, true)) {
                        $fields[$field] = true;
                        break;
                    }
                }
            }

            $requiredHits = count(array_filter($required, fn($field) => isset($fields[$field])));
            $score = $requiredHits * 10 + count($fields);
            if ($requiredHits === count($required)) return (int) $line;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLine = (int) $line;
            }
        }

        return $bestScore >= 10 ? $bestLine : null;
    }

    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        if (strlen($xml) > 10 * 1024 * 1024) throw new \RuntimeException('XLSX shared strings are too large');
        $data = simplexml_load_string($this->stripSpreadsheetNamespaces($xml), 'SimpleXMLElement', LIBXML_NONET);
        $strings = [];
        foreach ($data->si as $item) {
            if (isset($item->t)) { $strings[] = (string) $item->t; continue; }
            $text = '';
            foreach ($item->r as $run) $text .= (string) $run->t;
            $strings[] = $text;
        }
        return $strings;
    }

    private function assertZipEntrySafe(\ZipArchive $zip, string $name, int $maxSize, bool $required = true): void
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            if ($required) throw new \RuntimeException('File XLSX thiếu thành phần dữ liệu bắt buộc');
            return;
        }

        $size = (int) ($stat['size'] ?? 0);
        $compressed = max(1, (int) ($stat['comp_size'] ?? 1));
        if ($size <= 0 || $size > $maxSize || ($size / $compressed) > 100) {
            throw new \RuntimeException('File XLSX có kích thước giải nén không an toàn');
        }
    }

    private function mapRow(array $headers, array $values): array
    {
        $aliases = $this->aliases();
        $data = [];
        foreach ($headers as $index => $header) {
            $key = $this->headerKey($this->cleanImportedText((string) $header));
            foreach ($aliases as $field => $names) {
                if (in_array($key, $names, true)) {
                    $data[$field] = $this->cleanImportedText((string) ($values[$index] ?? ''));
                    break;
                }
            }
        }
        foreach (['dateOfBirth', 'identityIssueDate', 'healthInsuranceStartDate', 'healthInsuranceEndDate'] as $dateField) {
            if (!empty($data[$dateField])) $data[$dateField] = $this->dateValue($data[$dateField]);
        }
        return $data;
    }

    private function cleanImportedText(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = trim($value);
        return Encoding::repairMojibake($value);
    }

    private function validateRows(string $type, array $rows): array
    {
        $validRows = [];
        $errors = [];
        $warnings = [];
        $seenHouseholds = [];
        $seenCitizenCodes = [];
        $seenIdentities = [];

        foreach ($rows as $item) {
            $raw = $item['data'];
            $data = $this->normalizeData($type, $raw);
            $messages = [];
            $developmentMatches = $this->developmentDataMatches($data);
            if ($developmentMatches) {
                foreach ($developmentMatches as $match) {
                    $messages[] = 'Du lieu QA/UAT/TEST/DEMO khong duoc phep trong production: ' . ($match['field'] ?? '') . ' = ' . ($match['marker'] ?? '');
                }
            }

            if ($type === 'household') {
                foreach (['householdCode' => 'Mã hộ', 'address' => 'Địa chỉ'] as $field => $label) {
                    if (!array_key_exists($field, $raw)) $messages[] = 'Thiếu cột ' . $label;
                }
                if (empty($data['householdCode'])) $messages[] = 'Thiếu Mã hộ';
                if (empty($data['address'])) $messages[] = 'Thiếu Địa chỉ';
                if ($data['phone'] !== '' && !$this->validPhone($data['phone'])) $messages[] = 'Số điện thoại không hợp lệ';
                if ($data['householdCode'] !== '') {
                    if (isset($seenHouseholds[$data['householdCode']])) $messages[] = 'Trùng Mã hộ trong file';
                    $seenHouseholds[$data['householdCode']] = true;
                    if ($this->households->findByCode($data['householdCode'])) $warnings[] = ['row' => $item['row'], 'message' => 'Mã hộ đã tồn tại, hệ thống sẽ bỏ qua hoặc cập nhật theo chế độ đã chọn'];
                }
            } else {
                foreach (['householdCode' => 'Mã hộ', 'fullName' => 'Họ tên', 'dateOfBirth' => 'Ngày sinh'] as $field => $label) {
                    if (!array_key_exists($field, $raw)) $messages[] = 'Thiếu cột ' . $label;
                }
                if (empty($data['householdCode'])) $messages[] = 'Thiếu Mã hộ';
                if (empty($data['fullName'])) $messages[] = 'Thiếu Họ và tên';
                if (empty($data['dateOfBirth'])) $messages[] = 'Ngày sinh không hợp lệ';
                if ($data['identityNumber'] !== '' && !$this->validIdentity($data['identityNumber'])) $messages[] = 'CCCD phải gồm 9 hoặc 12 chữ số';
                if ($data['phone'] !== '' && !$this->validPhone($data['phone'])) $messages[] = 'Số điện thoại không hợp lệ';
                if ($data['householdCode'] !== '' && !$this->households->findByCode($data['householdCode'])) $messages[] = 'Mã hộ chưa tồn tại trong hệ thống';
                if ($data['citizenCode'] !== '') {
                    if (isset($seenCitizenCodes[$data['citizenCode']])) $messages[] = 'Trùng Mã nhân khẩu trong file';
                    $seenCitizenCodes[$data['citizenCode']] = true;
                    if ($this->citizenCodeExists($data['citizenCode'])) $warnings[] = ['row' => $item['row'], 'message' => 'Mã nhân khẩu đã tồn tại trong hệ thống'];
                }
                if ($data['identityNumber'] !== '') {
                    if (isset($seenIdentities[$data['identityNumber']])) $messages[] = 'Trùng CCCD trong file';
                    $seenIdentities[$data['identityNumber']] = true;
                    if ($this->citizens->findByIdentity($data['identityNumber'])) $warnings[] = ['row' => $item['row'], 'message' => 'CCCD đã tồn tại, hệ thống sẽ cập nhật nhân khẩu tương ứng'];
                }
            }

            if ($messages) {
                foreach (array_unique($messages) as $message) $errors[] = ['row' => $item['row'], 'message' => $message];
                continue;
            }
            $validRows[] = ['row' => $item['row'], 'data' => $data];
        }
        return ['total' => count($rows), 'valid' => count($validRows), 'warnings' => $warnings, 'warning' => count($warnings), 'failed' => count($errors), 'errors' => $errors, 'validRows' => $validRows, 'previewRows' => array_slice($validRows, 0, 20)];
    }

    private function validIdentity(string $value): bool { return (bool) preg_match('/^\d{9}(\d{3})?$/', $this->normalizeIdentity($value)); }

    private function normalizeIdentity(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';
        if (strlen($digits) === 11) return '0' . $digits;
        return $digits;
    }
    private function validPhone(string $value): bool { return (bool) preg_match('/^0\d{9,10}$/', $this->normalizePhone($value)); }

    private function normalizePhone(string $value): string
    {
        $raw = trim($value);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '' || preg_match('/^0+$/', $digits)) return '';
        if (strlen($digits) === 9 && preg_match('/^[35789]/', $digits)) return '0' . $digits;
        return $digits;
    }
    private function citizenCodeExists(string $code): bool
    {
        $row = Database::pdo()->prepare('SELECT COUNT(*) FROM citizens WHERE citizen_code = :code AND village_id = :village_id AND status <> "DELETED"');
        $row->execute(['code' => strtoupper(trim($code)), 'village_id' => TenantContext::id()]);
        return (int) $row->fetchColumn() > 0;
    }

    private function normalizeData(string $type, array $data): array
    {
        if ($type === 'household') {
            return [
                'householdCode' => strtoupper(trim((string) ($data['householdCode'] ?? ''))),
                'headCitizenName' => trim((string) ($data['headCitizenName'] ?? '')),
                'address' => trim((string) ($data['address'] ?? '')),
                'phone' => $this->normalizePhone((string) ($data['phone'] ?? '')),
                'areaCode' => strtoupper(trim((string) ($data['areaCode'] ?? ''))),
                'householdType' => trim((string) ($data['householdType'] ?? '')),
                'poorHousehold' => $data['poorHousehold'] ?? 0,
                'nearPoorHousehold' => $data['nearPoorHousehold'] ?? 0,
                'note' => trim((string) ($data['note'] ?? '')),
            ];
        }
        return [
            'householdCode' => strtoupper(trim((string) ($data['householdCode'] ?? ''))),
            'citizenCode' => strtoupper(trim((string) ($data['citizenCode'] ?? ''))),
            'fullName' => trim((string) ($data['fullName'] ?? '')),
            'gender' => $data['gender'] ?? 'Nam',
            'dateOfBirth' => $data['dateOfBirth'] ?? '',
            'identityNumber' => $this->normalizeIdentity((string) ($data['identityNumber'] ?? '')),
            'identityIssueDate' => $data['identityIssueDate'] ?? null,
            'identityIssuePlace' => trim((string) ($data['identityIssuePlace'] ?? '')),
            'phone' => $this->normalizePhone((string) ($data['phone'] ?? '')),
            'relationship' => $data['relationship'] ?? 'Khác',
            'fatherName' => trim((string) ($data['fatherName'] ?? '')),
            'motherName' => trim((string) ($data['motherName'] ?? '')),
            'ethnicity' => $data['ethnicity'] ?? 'Kinh',
            'religion' => $data['religion'] ?? 'Không',
            'occupation' => $data['occupation'] ?? 'Khác',
            'educationLevel' => $data['educationLevel'] ?? 'Khác',
            'maritalStatus' => $data['maritalStatus'] ?? 'Khác',
            'residency_status' => $this->residencyValue((string) ($data['residency_status'] ?? 'PERMANENT')),
            'presenceStatus' => $this->presenceValue((string) ($data['presenceStatus'] ?? 'AT_HOME')),
            'status' => $this->lifeValue((string) ($data['status'] ?? 'ALIVE')),
            'currentAddress' => trim((string) ($data['currentAddress'] ?? '')),
            'partyMember' => $this->yesNo($data['partyMember'] ?? 0),
            'youthUnionMember' => $this->yesNo($data['youthUnionMember'] ?? 0),
            'womenUnionMember' => $this->yesNo($data['womenUnionMember'] ?? 0),
            'farmersUnionMember' => $this->yesNo($data['farmersUnionMember'] ?? 0),
            'veteransUnionMember' => $this->yesNo($data['veteransUnionMember'] ?? 0),
            'elderlyUnionMember' => $this->yesNo($data['elderlyUnionMember'] ?? 0),
            'meritoriousPerson' => $this->yesNo($data['meritoriousPerson'] ?? 0),
            'martyrRelative' => $this->yesNo($data['martyrRelative'] ?? 0),
            'woundedSoldier' => $this->yesNo($data['woundedSoldier'] ?? 0),
            'sickSoldier' => $this->yesNo($data['sickSoldier'] ?? 0),
            'chemicalWarfareVictim' => $this->yesNo($data['chemicalWarfareVictim'] ?? 0),
            'imprisonedResistanceActivist' => $this->yesNo($data['imprisonedResistanceActivist'] ?? 0),
            'youthVolunteer' => $this->yesNo($data['youthVolunteer'] ?? 0),
            'resistanceHero' => $this->yesNo($data['resistanceHero'] ?? 0),
            'revolutionaryActivist' => $this->yesNo($data['revolutionaryActivist'] ?? 0),
            'disabledPerson' => $this->yesNo($data['disabledPerson'] ?? 0),
            'socialAssistance' => $this->yesNo($data['socialAssistance'] ?? 0),
            'employed' => $this->yesNo($data['employed'] ?? 0),
            'unemployed' => $this->yesNo($data['unemployed'] ?? 0),
            'freelanceLabor' => $this->yesNo($data['freelanceLabor'] ?? 0),
            'outProvinceLabor' => $this->yesNo($data['outProvinceLabor'] ?? 0),
            'foreignLabor' => $this->yesNo($data['foreignLabor'] ?? 0),
            'notAttendingSchool' => $this->yesNo($data['notAttendingSchool'] ?? 0),
            'pupil' => $this->yesNo($data['pupil'] ?? 0),
            'student' => $this->yesNo($data['student'] ?? 0),
            'retired' => $this->yesNo($data['retired'] ?? 0),
            'hasHealthInsurance' => array_key_exists('hasHealthInsurance', $data) && trim((string) $data['hasHealthInsurance']) !== '' ? $this->yesNo($data['hasHealthInsurance']) : 1,
            'healthInsuranceNumber' => trim((string) ($data['healthInsuranceNumber'] ?? '')),
            'healthInsuranceGroup' => trim((string) ($data['healthInsuranceGroup'] ?? '')),
            'healthInsuranceStartDate' => $data['healthInsuranceStartDate'] ?? null,
            'healthInsuranceEndDate' => $data['healthInsuranceEndDate'] ?? null,
            'healthInsuranceFacility' => trim((string) ($data['healthInsuranceFacility'] ?? '')),
        ];
    }

    private function aliases(): array
    {
        return [
            'householdCode' => ['ma ho','ma ho gia dinh','household code','householdcode','householdid'],
            'headCitizenName' => ['chu ho','ten chu ho','ho ten chu ho'],
            'address' => ['dia chi','thon','dia chi thuong tru'],
            'phone' => ['so dien thoai','dien thoai','sdt','phone'],
            'areaCode' => ['ma dia ban','ma khu vuc','ma thon','area code','areacode'],
            'householdType' => ['dien ho','loai ho','household type','category'],
            'poorHousehold' => ['ho ngheo'],
            'nearPoorHousehold' => ['ho can ngheo','can ngheo'],
            'note' => ['ghi chu','note'],
            'citizenCode' => ['ma nhan khau','ma cong dan','citizen code','citizencode'],
            'fullName' => ['ho va ten','ho ten','ten nhan khau','full name','fullname','displayname'],
            'gender' => ['gioi tinh'],
            'dateOfBirth' => ['ngay sinh','nam sinh','date of birth','dateofbirth','dob'],
            'identityNumber' => ['cccd','cmnd','so cccd','so cmnd','identitynumber','identity'],
            'identityIssueDate' => ['ngay cap cccd','ngay cap cmnd','ngay cap','identity issue date','identityissuedate'],
            'identityIssuePlace' => ['noi cap cccd','noi cap cmnd','noi cap','identity issue place','identityissueplace'],
            'relationship' => ['quan he voi chu ho','quan he'],
            'fatherName' => ['ho ten bo','ten bo','bo','father name','fathername'],
            'motherName' => ['ho ten me','ten me','me','mother name','mothername'],
            'ethnicity' => ['dan toc'],
            'religion' => ['ton giao'],
            'occupation' => ['nghe nghiep'],
            'educationLevel' => ['hoc van','trinh do hoc van'],
            'maritalStatus' => ['hon nhan','tinh trang hon nhan'],
            'residency_status' => ['thuong tru','cu tru','tam tru'],
            'presenceStatus' => ['hien tai','o nha di vang','presencestatus'],
            'status' => ['trang thai','con song da chet','status'],
            'currentAddress' => ['dia chi hien tai'],
            'partyMember' => ['dang vien','la dang vien','party member'],
            'youthUnionMember' => ['doan vien','doan vien thanh nien','youth union'],
            'womenUnionMember' => ['hoi vien phu nu','hoi phu nu','phu nu'],
            'farmersUnionMember' => ['hoi vien nong dan','hoi nong dan','nong dan'],
            'veteransUnionMember' => ['hoi vien cuu chien binh','cuu chien binh'],
            'elderlyUnionMember' => ['hoi vien nguoi cao tuoi','hoi nguoi cao tuoi','nguoi cao tuoi'],
            'meritoriousPerson' => ['nguoi co cong','ca nhan co cong'],
            'martyrRelative' => ['than nhan liet si','than nhan liet sy'],
            'woundedSoldier' => ['thuong binh'],
            'sickSoldier' => ['benh binh'],
            'chemicalWarfareVictim' => ['nhiem chat doc hoa hoc','chat doc hoa hoc','chemical warfare victim','chemicalwarfarevictim'],
            'imprisonedResistanceActivist' => ['nguoi bi dich bat tu day','bi bat tu day','tu day','imprisoned resistance activist','imprisonedresistanceactivist'],
            'youthVolunteer' => ['thanh nien xung phong','youth volunteer','youthvolunteer'],
            'resistanceHero' => ['anh hung luc luong vu trang','anh hung lao dong','anh hung khang chien','resistance hero','resistancehero'],
            'revolutionaryActivist' => ['nguoi hoat dong cach mang','hoat dong cach mang','revolutionary activist','revolutionaryactivist'],
            'disabledPerson' => ['nguoi khuyet tat','khuyet tat'],
            'socialAssistance' => ['bao tro xa hoi'],
            'employed' => ['co viec lam','viec lam'],
            'unemployed' => ['that nghiep'],
            'freelanceLabor' => ['lao dong tu do'],
            'outProvinceLabor' => ['lao dong ngoai tinh','ngoai tinh'],
            'foreignLabor' => ['lao dong nuoc ngoai','nuoc ngoai'],
            'notAttendingSchool' => ['chua di hoc','not attending school','notattendingschool'],
            'pupil' => ['hoc sinh'],
            'student' => ['sinh vien'],
            'retired' => ['nghi huu'],
            'hasHealthInsurance' => ['bhyt','bao hiem y te','co bhyt','has health insurance','hashealthinsurance'],
            'healthInsuranceNumber' => ['ma so bhyt','so the bhyt','health insurance number','healthinsurancenumber'],
            'healthInsuranceGroup' => ['nhom bhyt','doi tuong bhyt','health insurance group','healthinsurancegroup'],
            'healthInsuranceStartDate' => ['ngay bat dau bhyt','bhyt tu ngay','health insurance start date','healthinsurancestartdate'],
            'healthInsuranceEndDate' => ['ngay het han bhyt','bhyt den ngay','health insurance end date','healthinsuranceenddate'],
            'healthInsuranceFacility' => ['noi dang ky kham chua benh','noi kcb ban dau','co so kham chua benh bhyt','health insurance facility','healthinsurancefacility'],
        ];
    }

    private function headerKey(string $value): string
    {
        $value = trim(mb_strtolower(Encoding::repairMojibake($value)));
        $value = $this->removeVietnameseMarks($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return preg_replace('/[^a-z0-9 ]+/', '', $ascii) ?: $value;
    }

    private function removeVietnameseMarks(string $value): string
    {
        $groups = [
            'a' => '&#224;&#225;&#7843;&#227;&#7841;&#259;&#7857;&#7855;&#7859;&#7861;&#7863;&#226;&#7847;&#7845;&#7849;&#7851;&#7853;',
            'd' => '&#273;',
            'e' => '&#232;&#233;&#7867;&#7869;&#7865;&#234;&#7873;&#7871;&#7875;&#7877;&#7879;',
            'i' => '&#236;&#237;&#7881;&#297;&#7883;',
            'o' => '&#242;&#243;&#7887;&#245;&#7885;&#244;&#7891;&#7889;&#7893;&#7895;&#7897;&#417;&#7901;&#7899;&#7903;&#7905;&#7907;',
            'u' => '&#249;&#250;&#7911;&#361;&#7909;&#432;&#7915;&#7913;&#7917;&#7919;&#7921;',
            'y' => '&#7923;&#253;&#7927;&#7929;&#7925;',
        ];

        foreach ($groups as $ascii => $entities) {
            $chars = preg_split('//u', html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($chars) $value = str_replace($chars, $ascii, $value);
        }
        return $value;
    }

    private function dateValue(string $value): string
    {
        $value = trim($value);
        if (is_numeric($value) && (float) $value > 20000) return gmdate('Y-m-d', ((int) $value - 25569) * 86400);
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        return '';
    }

    private function yesNo(mixed $value): int { $text = $this->headerKey((string) $value); return in_array($text, ['1','co','yes','true','x'], true) ? 1 : 0; }
    private function residencyValue(string $value): string { return str_contains($this->headerKey($value), 'tam tru') ? 'TEMPORARY' : 'PERMANENT'; }
    private function presenceValue(string $value): string { return str_contains($this->headerKey($value), 'vang') ? 'AWAY' : 'AT_HOME'; }
    private function lifeValue(string $value): string { return str_contains($this->headerKey($value), 'chet') ? 'DECEASED' : 'ALIVE'; }
    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) $index = $index * 26 + ord(strtoupper($letter)) - 64;
        return $index - 1;
    }
}
