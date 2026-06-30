<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
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
        $user = $this->requirePermission('import', 'create');
        $type = $this->type();
        $rows = $this->readRows();
        $result = $this->validateRows($type, $rows);
        $this->audit($user, 'import', 'read', 'Kiểm tra file import', null, ['type' => $type, 'total' => count($rows), 'errors' => count($result['errors']), 'warnings' => count($result['warnings'] ?? [])]);
        $this->ok($result + ['type' => $type]);
    }

    public function process(): void
    {
        $user = $this->requirePermission('import', 'create');
        $type = $this->type();
        $mode = (string) ($_POST['mode'] ?? $this->input('mode', 'skip'));
        $rows = $this->readRows();
        $result = $this->validateRows($type, $rows);
        $success = 0;
        $skipped = 0;
        $errors = $result['errors'];
        $rolledBack = false;

        if (!empty($errors)) {
            $payload = ['type' => $type, 'total' => count($rows), 'success' => 0, 'skipped' => 0, 'failed' => count($errors), 'rolledBack' => false, 'warnings' => $result['warnings'] ?? [], 'errors' => $errors];
            $this->audit($user, 'import', 'create', 'Import dữ liệu không hợp lệ', null, $payload, 'WARN');
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
        $this->audit($user, 'import', 'create', 'Import dữ liệu', null, $payload, count($errors) ? 'WARN' : 'INFO');
        $this->ok($payload);
    }

    private function type(): string
    {
        $type = (string) ($_POST['type'] ?? $this->input('type', 'household'));
        if (!in_array($type, ['household', 'person'], true)) throw new \RuntimeException('Loại dữ liệu import không hợp lệ');
        return $type;
    }

    private function readRows(): array
    {
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            throw new \RuntimeException('Vui lòng chọn file CSV hoặc XLSX');
        }
        if ((int) ($_FILES['file']['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \RuntimeException('File import tối đa 5MB');
        }
        $name = strtolower((string) $_FILES['file']['name']);
        if (str_ends_with($name, '.csv')) return $this->readCsv($_FILES['file']['tmp_name']);
        if (str_ends_with($name, '.xlsx')) return $this->readXlsx($_FILES['file']['tmp_name']);
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
        }
        fclose($handle);
        return $rows;
    }

    private function readXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) throw new \RuntimeException('Hosting chưa bật ZipArchive để đọc file XLSX');
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \RuntimeException('Không mở được file XLSX');
        $shared = $this->sharedStrings($zip);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($xml === false) throw new \RuntimeException('File XLSX chưa có sheet dữ liệu đầu tiên');
        $sheet = simplexml_load_string($this->stripSpreadsheetNamespaces($xml));
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
                $matrix[$line][$col] = trim($value);
            }
        }
        if (!$matrix) return [];
        ksort($matrix);
        $headerLine = array_key_first($matrix);
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
        }
        return $rows;
    }

    private function stripSpreadsheetNamespaces(string $xml): string
    {
        $xml = preg_replace('/(<\/?)[A-Za-z0-9_\-]+:/', '$1', $xml) ?? $xml;
        $xml = preg_replace('/\s+xmlns(:[A-Za-z0-9_\-]+)?="[^"]*"/', '', $xml) ?? $xml;
        return $xml;
    }

    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        $data = simplexml_load_string($this->stripSpreadsheetNamespaces($xml));
        $strings = [];
        foreach ($data->si as $item) {
            if (isset($item->t)) { $strings[] = (string) $item->t; continue; }
            $text = '';
            foreach ($item->r as $run) $text .= (string) $run->t;
            $strings[] = $text;
        }
        return $strings;
    }

    private function mapRow(array $headers, array $values): array
    {
        $aliases = $this->aliases();
        $data = [];
        foreach ($headers as $index => $header) {
            $key = $this->headerKey((string) $header);
            foreach ($aliases as $field => $names) {
                if (in_array($key, $names, true)) {
                    $data[$field] = trim((string) ($values[$index] ?? ''));
                    break;
                }
            }
        }
        foreach (['dateOfBirth'] as $dateField) if (!empty($data[$dateField])) $data[$dateField] = $this->dateValue($data[$dateField]);
        return $data;
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

    private function validIdentity(string $value): bool { return (bool) preg_match('/^\d{9}(\d{3})?$/', preg_replace('/\D+/', '', $value)); }
    private function validPhone(string $value): bool { return (bool) preg_match('/^0\d{9,10}$/', preg_replace('/\D+/', '', $value)); }
    private function citizenCodeExists(string $code): bool
    {
        $row = Database::pdo()->prepare('SELECT COUNT(*) FROM citizens WHERE citizen_code = :code AND status <> "DELETED"');
        $row->execute(['code' => strtoupper(trim($code))]);
        return (int) $row->fetchColumn() > 0;
    }

    private function normalizeData(string $type, array $data): array
    {
        if ($type === 'household') {
            return [
                'householdCode' => strtoupper(trim((string) ($data['householdCode'] ?? ''))),
                'headCitizenName' => trim((string) ($data['headCitizenName'] ?? '')),
                'address' => trim((string) ($data['address'] ?? '')),
                'phone' => trim((string) ($data['phone'] ?? '')),
                'householdType' => trim((string) ($data['householdType'] ?? '')),
                'meritoriousFamily' => $data['meritoriousFamily'] ?? 0,
                'poorHousehold' => $data['poorHousehold'] ?? 0,
                'nearPoorHousehold' => $data['nearPoorHousehold'] ?? 0,
                'disabledHousehold' => $data['disabledHousehold'] ?? 0,
                'note' => trim((string) ($data['note'] ?? '')),
            ];
        }
        return [
            'householdCode' => strtoupper(trim((string) ($data['householdCode'] ?? ''))),
            'citizenCode' => strtoupper(trim((string) ($data['citizenCode'] ?? ''))),
            'fullName' => trim((string) ($data['fullName'] ?? '')),
            'gender' => $data['gender'] ?? 'Nam',
            'dateOfBirth' => $data['dateOfBirth'] ?? '',
            'identityNumber' => trim((string) ($data['identityNumber'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'relationship' => $data['relationship'] ?? 'Khác',
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
            'disabledPerson' => $this->yesNo($data['disabledPerson'] ?? 0),
            'socialAssistance' => $this->yesNo($data['socialAssistance'] ?? 0),
            'employed' => $this->yesNo($data['employed'] ?? 0),
            'unemployed' => $this->yesNo($data['unemployed'] ?? 0),
            'freelanceLabor' => $this->yesNo($data['freelanceLabor'] ?? 0),
            'outProvinceLabor' => $this->yesNo($data['outProvinceLabor'] ?? 0),
            'foreignLabor' => $this->yesNo($data['foreignLabor'] ?? 0),
            'pupil' => $this->yesNo($data['pupil'] ?? 0),
            'student' => $this->yesNo($data['student'] ?? 0),
            'retired' => $this->yesNo($data['retired'] ?? 0),
        ];
    }

    private function aliases(): array
    {
        return [
            'householdCode' => ['ma ho','ma ho gia dinh','household code','householdcode','householdid'],
            'headCitizenName' => ['chu ho','ten chu ho','ho ten chu ho'],
            'address' => ['dia chi','thon','dia chi thuong tru'],
            'phone' => ['so dien thoai','dien thoai','sdt','phone'],
            'householdType' => ['dien ho','loai ho','household type','category'],
            'meritoriousFamily' => ['gia dinh co cong','co cong'],
            'poorHousehold' => ['ho ngheo'],
            'nearPoorHousehold' => ['ho can ngheo','can ngheo'],
            'disabledHousehold' => ['tan tat','khuyet tat'],
            'note' => ['ghi chu','note'],
            'citizenCode' => ['ma nhan khau','ma cong dan','citizen code','citizencode'],
            'fullName' => ['ho va ten','ho ten','ten nhan khau','full name','fullname','displayname'],
            'gender' => ['gioi tinh'],
            'dateOfBirth' => ['ngay sinh','nam sinh','date of birth','dateofbirth','dob'],
            'identityNumber' => ['cccd','cmnd','so cccd','so cmnd','identitynumber','identity'],
            'relationship' => ['quan he voi chu ho','quan he'],
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
            'disabledPerson' => ['nguoi khuyet tat','khuyet tat'],
            'socialAssistance' => ['bao tro xa hoi'],
            'employed' => ['co viec lam','viec lam'],
            'unemployed' => ['that nghiep'],
            'freelanceLabor' => ['lao dong tu do'],
            'outProvinceLabor' => ['lao dong ngoai tinh','ngoai tinh'],
            'foreignLabor' => ['lao dong nuoc ngoai','nuoc ngoai'],
            'pupil' => ['hoc sinh'],
            'student' => ['sinh vien'],
            'retired' => ['nghi huu'],
        ];
    }

    private function headerKey(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return preg_replace('/[^a-z0-9 ]+/', '', $ascii) ?: $value;
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
