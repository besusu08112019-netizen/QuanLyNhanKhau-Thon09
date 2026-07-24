<?php

namespace App\Models;

use App\Core\BaseModel;

final class Finance extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS finance_funds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fund_code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  opening_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
  note TEXT NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_finance_funds_status (status),
  KEY idx_finance_funds_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS finance_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  transaction_type ENUM('INCOME','EXPENSE') NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_finance_categories_type (transaction_type),
  KEY idx_finance_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS finance_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_code VARCHAR(40) NOT NULL UNIQUE,
  transaction_type ENUM('INCOME','EXPENSE') NOT NULL,
  fund_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  transaction_date DATE NOT NULL,
  payer_name VARCHAR(180) NULL,
  receiver_name VARCHAR(180) NULL,
  payment_method VARCHAR(60) NULL,
  receipt_number VARCHAR(100) NULL,
  description TEXT NULL,
  source_module VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  status ENUM('POSTED','CANCELLED','DELETED') NOT NULL DEFAULT 'POSTED',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_finance_transactions_type (transaction_type),
  KEY idx_finance_transactions_fund (fund_id),
  KEY idx_finance_transactions_category (category_id),
  KEY idx_finance_transactions_date (transaction_date),
  KEY idx_finance_transactions_status (status),
  KEY idx_finance_transactions_receipt (receipt_number),
  KEY idx_finance_transactions_source (source_module, source_id),
  CONSTRAINT fk_finance_transactions_fund FOREIGN KEY (fund_id) REFERENCES finance_funds(id),
  CONSTRAINT fk_finance_transactions_category FOREIGN KEY (category_id) REFERENCES finance_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS finance_transaction_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('PDF','IMAGE','DOCUMENT','OTHER') NOT NULL DEFAULT 'DOCUMENT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_finance_attachments_transaction (transaction_id),
  CONSTRAINT fk_finance_attachments_transaction FOREIGN KEY (transaction_id) REFERENCES finance_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedDefaults();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'funds' => array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['fund_code'], 'label' => (string)$r['name'], 'opening_balance' => (float)$r['opening_balance']], $this->fetchAll('SELECT id, fund_code, name, opening_balance FROM finance_funds WHERE status="ACTIVE" ORDER BY name ASC')),
            'categories' => array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name'], 'transaction_type' => (string)$r['transaction_type']], $this->fetchAll('SELECT id, code, name, transaction_type FROM finance_categories WHERE is_active=1 ORDER BY transaction_type ASC, sort_order ASC, name ASC')),
            'types' => [['value' => 'INCOME', 'label' => 'Thu'], ['value' => 'EXPENSE', 'label' => 'Chi']],
            'statuses' => [['value' => 'POSTED', 'label' => 'Da ghi so'], ['value' => 'CANCELLED', 'label' => 'Da huy']],
            'payment_methods' => [['value' => 'CASH', 'label' => 'Tien mat'], ['value' => 'BANK_TRANSFER', 'label' => 'Chuyen khoan'], ['value' => 'OTHER', 'label' => 'Khac']],
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $order = $this->listOrder($filters, ['transaction_code' => 't.transaction_code', 'transaction_date' => 't.transaction_date', 'transaction_type' => 't.transaction_type', 'amount' => 't.amount', 'fund' => 'f.name', 'category' => 'c.name', 'status' => 't.status'], 'transaction_date', 'DESC', ['t.id DESC']);
        $from = $this->fromSql();
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($r) => $this->normalize($r), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE t.id=:id AND t.status <> "DELETED"', ['id' => $id]);
        if (!$row) return null;
        $item = $this->normalize($row);
        $item['attachments'] = $this->attachments($id);
        return $item;
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new \RuntimeException('Khong tim thay phieu thu chi');
        $params = $this->params($data, $userId);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE finance_transactions SET transaction_type=:transaction_type, fund_id=:fund_id, category_id=:category_id, amount=:amount, transaction_date=:transaction_date, payer_name=:payer_name, receiver_name=:receiver_name, payment_method=:payment_method, receipt_number=:receipt_number, description=:description, source_module=:source_module, source_id=:source_id, status=:status, updated_by=:updated_by WHERE id=:id AND status <> "DELETED"', $params);
            return $this->find($id);
        }
        $params['transaction_code'] = $this->nextCode((string)$params['transaction_type']);
        $newId = $this->insert('INSERT INTO finance_transactions (transaction_code, transaction_type, fund_id, category_id, amount, transaction_date, payer_name, receiver_name, payment_method, receipt_number, description, source_module, source_id, status, created_by, updated_by) VALUES (:transaction_code,:transaction_type,:fund_id,:category_id,:amount,:transaction_date,:payer_name,:receiver_name,:payment_method,:receipt_number,:description,:source_module,:source_id,:status,:created_by,:updated_by)', $params);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        if (!$this->find($id)) throw new \RuntimeException('Khong tim thay phieu thu chi');
        $this->execute('UPDATE finance_transactions SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function addAttachment(int $id, array $stored, array $file, int $userId): array
    {
        if (!$this->find($id)) throw new \RuntimeException('Khong tim thay phieu thu chi');
        $mime = (string)$stored['mime'];
        $kind = $mime === 'application/pdf' ? 'PDF' : (str_starts_with($mime, 'image/') ? 'IMAGE' : 'DOCUMENT');
        $fileId = $this->insert('INSERT INTO finance_transaction_attachments (transaction_id, original_name, stored_path, mime_type, file_size, file_kind, created_by) VALUES (:id,:name,:path,:mime,:size,:kind,:user)', ['id' => $id, 'name' => basename((string)($file['name'] ?? 'attachment')), 'path' => $stored['file_path'], 'mime' => $mime, 'size' => (int)($file['size'] ?? 0), 'kind' => $kind, 'user' => $userId]);
        return $this->attachment($id, $fileId) ?? ['id' => $fileId];
    }

    public function attachment(int $transactionId, int $fileId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM finance_transaction_attachments WHERE transaction_id=:transaction_id AND id=:id AND deleted_at IS NULL', ['transaction_id' => $transactionId, 'id' => $fileId]);
        return $row ? $this->normalizeAttachment($row) : null;
    }

    public function deleteAttachment(int $transactionId, int $fileId, int $userId): void
    {
        if (!$this->attachment($transactionId, $fileId)) throw new \RuntimeException('Khong tim thay file dinh kem');
        $this->execute('UPDATE finance_transaction_attachments SET deleted_at=NOW(), deleted_by=:user WHERE transaction_id=:transaction_id AND id=:id', ['transaction_id' => $transactionId, 'id' => $fileId, 'user' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters);
        $from = $this->fromSql();
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN t.transaction_type='INCOME' AND t.status='POSTED' THEN t.amount ELSE 0 END),0) AS total_income, COALESCE(SUM(CASE WHEN t.transaction_type='EXPENSE' AND t.status='POSTED' THEN t.amount ELSE 0 END),0) AS total_expense, COALESCE(SUM(CASE WHEN t.status='CANCELLED' THEN 1 ELSE 0 END),0) AS cancelled_count $from $where", $params) ?: [];
        $metrics['balance'] = (float)($metrics['total_income'] ?? 0) - (float)($metrics['total_expense'] ?? 0);
        return [
            'metrics' => ['total' => (int)($metrics['total'] ?? 0), 'total_income' => (float)($metrics['total_income'] ?? 0), 'total_expense' => (float)($metrics['total_expense'] ?? 0), 'balance' => (float)$metrics['balance'], 'cancelled_count' => (int)($metrics['cancelled_count'] ?? 0)],
            'by_category' => $this->fetchAll("SELECT COALESCE(c.name,'Khac') AS label, t.transaction_type, COALESCE(SUM(t.amount),0) AS value $from $where AND t.status='POSTED' GROUP BY label, t.transaction_type ORDER BY value DESC LIMIT 12", $params),
            'by_month' => $this->fetchAll("SELECT DATE_FORMAT(t.transaction_date, '%Y-%m') AS label, COALESCE(SUM(CASE WHEN t.transaction_type='INCOME' THEN t.amount ELSE 0 END),0) AS income, COALESCE(SUM(CASE WHEN t.transaction_type='EXPENSE' THEN t.amount ELSE 0 END),0) AS expense $from $where AND t.status='POSTED' GROUP BY label ORDER BY label DESC LIMIT 12", $params),
            'funds' => $this->fundBalances(),
        ];
    }

    public function report(array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['pageSize'] = 500;
        $data = $this->paginate($filters);
        $income = 0.0; $expense = 0.0;
        foreach ($data['items'] as $row) {
            if ($row['status'] !== 'POSTED') continue;
            if ($row['transaction_type'] === 'INCOME') $income += (float)$row['amount'];
            if ($row['transaction_type'] === 'EXPENSE') $expense += (float)$row['amount'];
        }
        return [
            'title' => 'Bao cao thu chi',
            'headers' => ['Ma phieu', 'Ngay', 'Loai', 'Quy', 'Danh muc', 'So tien', 'Nguoi nop', 'Nguoi nhan', 'So chung tu', 'Trang thai'],
            'rows' => array_map(fn($r) => [$r['transaction_code'], $r['transaction_date'], $r['type_label'], $r['fund_name'], $r['category_name'], number_format((float)$r['amount'], 0, ',', '.'), $r['payer_name'], $r['receiver_name'], $r['receipt_number'], $r['status_label']], $data['items']),
            'summary' => ['total_income' => $income, 'total_expense' => $expense, 'balance' => $income - $expense],
            'totalRows' => $data['total'],
        ];
    }

    private function params(array $data, int $userId): array
    {
        $type = strtoupper(trim((string)($data['transaction_type'] ?? $data['transactionType'] ?? 'INCOME')));
        if (!in_array($type, ['INCOME','EXPENSE'], true)) throw new \RuntimeException('Loai giao dich khong hop le');
        $fundId = (int)($data['fund_id'] ?? $data['fundId'] ?? 0);
        if ($fundId <= 0) throw new \RuntimeException('Quy la bat buoc');
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) throw new \RuntimeException('So tien phai lon hon 0');
        $date = $this->dateRequired($data['transaction_date'] ?? $data['transactionDate'] ?? '', 'Ngay thu chi khong hop le');
        $status = strtoupper(trim((string)($data['status'] ?? 'POSTED')));
        if (!in_array($status, ['POSTED','CANCELLED'], true)) $status = 'POSTED';
        $sourceId = (int)($data['source_id'] ?? $data['sourceId'] ?? 0);
        return [
            'transaction_type' => $type,
            'fund_id' => $fundId,
            'category_id' => ((int)($data['category_id'] ?? $data['categoryId'] ?? 0)) ?: null,
            'amount' => $amount,
            'transaction_date' => $date,
            'payer_name' => $this->nullable($data['payer_name'] ?? $data['payerName'] ?? ''),
            'receiver_name' => $this->nullable($data['receiver_name'] ?? $data['receiverName'] ?? ''),
            'payment_method' => $this->nullable($data['payment_method'] ?? $data['paymentMethod'] ?? 'CASH'),
            'receipt_number' => $this->nullable($data['receipt_number'] ?? $data['receiptNumber'] ?? ''),
            'description' => $this->nullableText($data['description'] ?? ''),
            'source_module' => $this->nullable($data['source_module'] ?? $data['sourceModule'] ?? ''),
            'source_id' => $sourceId > 0 ? $sourceId : null,
            'status' => $status,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function where(array $filters): array
    {
        $where = ['t.status <> "DELETED"'];
        $params = [];
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(LOWER(t.transaction_code) LIKE :q OR LOWER(t.receipt_number) LIKE :q OR LOWER(t.payer_name) LIKE :q OR LOWER(t.receiver_name) LIKE :q OR LOWER(t.description) LIKE :q OR LOWER(f.name) LIKE :q OR LOWER(c.name) LIKE :q)';
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        }
        foreach (['transaction_type' => 't.transaction_type', 'fund_id' => 't.fund_id', 'category_id' => 't.category_id', 'status' => 't.status'] as $key => $column) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { $where[] = "t.transaction_date $op :$key"; $params[$key] = $value; }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function fundBalances(): array
    {
        return $this->fetchAll(<<<SQL
SELECT f.id, f.fund_code, f.name, f.opening_balance,
  f.opening_balance + COALESCE(SUM(CASE WHEN t.status='POSTED' AND t.transaction_type='INCOME' THEN t.amount WHEN t.status='POSTED' AND t.transaction_type='EXPENSE' THEN -t.amount ELSE 0 END),0) AS balance
FROM finance_funds f
LEFT JOIN finance_transactions t ON t.fund_id=f.id AND t.status <> 'DELETED'
WHERE f.status='ACTIVE'
GROUP BY f.id, f.fund_code, f.name, f.opening_balance
ORDER BY f.name ASC
SQL);
    }

    private function attachments(int $id): array
    {
        return array_map(fn($r) => $this->normalizeAttachment($r), $this->fetchAll('SELECT * FROM finance_transaction_attachments WHERE transaction_id=:id AND deleted_at IS NULL ORDER BY id DESC', ['id' => $id]));
    }

    private function selectSql(): string { return 'SELECT t.*, f.name AS fund_name, f.fund_code, c.name AS category_name, c.code AS category_code, (SELECT COUNT(*) FROM finance_transaction_attachments a WHERE a.transaction_id=t.id AND a.deleted_at IS NULL) AS attachment_count'; }
    private function fromSql(): string { return 'FROM finance_transactions t INNER JOIN finance_funds f ON f.id=t.fund_id LEFT JOIN finance_categories c ON c.id=t.category_id'; }
    private function normalize(array $row): array { $type = (string)$row['transaction_type']; $status = (string)$row['status']; return ['id' => (int)$row['id'], 'transaction_code' => (string)$row['transaction_code'], 'transaction_type' => $type, 'type_label' => $type === 'EXPENSE' ? 'Chi' : 'Thu', 'fund_id' => (int)$row['fund_id'], 'fund_name' => (string)($row['fund_name'] ?? ''), 'category_id' => $row['category_id'] !== null ? (int)$row['category_id'] : null, 'category_name' => (string)($row['category_name'] ?? ''), 'amount' => (float)$row['amount'], 'transaction_date' => $row['transaction_date'] ?? null, 'payer_name' => (string)($row['payer_name'] ?? ''), 'receiver_name' => (string)($row['receiver_name'] ?? ''), 'payment_method' => (string)($row['payment_method'] ?? ''), 'receipt_number' => (string)($row['receipt_number'] ?? ''), 'description' => (string)($row['description'] ?? ''), 'source_module' => (string)($row['source_module'] ?? ''), 'source_id' => $row['source_id'] !== null ? (int)$row['source_id'] : null, 'status' => $status, 'status_label' => $status === 'CANCELLED' ? 'Da huy' : 'Da ghi so', 'attachment_count' => (int)($row['attachment_count'] ?? 0), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null]; }
    private function normalizeAttachment(array $row): array { $id = (int)$row['id']; return ['id' => $id, 'transaction_id' => (int)$row['transaction_id'], 'original_name' => (string)$row['original_name'], 'stored_path' => (string)$row['stored_path'], 'mime_type' => (string)$row['mime_type'], 'file_size' => (int)$row['file_size'], 'file_kind' => (string)$row['file_kind'], 'preview_url' => '/api/finance/' . (int)$row['transaction_id'] . '/attachments/' . $id . '/preview', 'download_url' => '/api/finance/' . (int)$row['transaction_id'] . '/attachments/' . $id . '/download']; }
    private function seedDefaults(): void { $this->execute('INSERT INTO finance_funds (fund_code,name,opening_balance) VALUES ("GENERAL","Quy chung",0) ON DUPLICATE KEY UPDATE name=VALUES(name)'); $items = [['CONTRIBUTION','Thu dong gop','INCOME',10], ['SUPPORT','Thu ho tro','INCOME',20], ['OTHER_INCOME','Thu khac','INCOME',90], ['COMMUNITY_ACTIVITY','Chi hoat dong cong dong','EXPENSE',10], ['PUBLIC_ASSET_MAINTENANCE','Chi bao tri cong trinh tai san','EXPENSE',20], ['ENVIRONMENT','Chi ve sinh moi truong','EXPENSE',30], ['OTHER_EXPENSE','Chi khac','EXPENSE',90]]; foreach ($items as [$code, $name, $type, $order]) { $this->execute('INSERT INTO finance_categories (code,name,transaction_type,sort_order) VALUES (:code,:name,:type,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), transaction_type=VALUES(transaction_type), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'type' => $type, 'sort_order' => $order]); } }
    private function nextCode(string $type): string { $prefix = $type === 'EXPENSE' ? 'PC09-' : 'PT09-'; $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM finance_transactions'); return $prefix . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : mb_substr($value, 0, 180); }
    private function nullableText(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : mb_substr($value, 0, 4000); }
    private function dateRequired(mixed $value, string $message): string { $value = trim((string)($value ?? '')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) throw new \RuntimeException($message); return $value; }
}
