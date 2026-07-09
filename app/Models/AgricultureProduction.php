<?php

namespace App\Models;

use App\Core\BaseModel;

final class AgricultureProduction extends BaseModel
{
    public const OWNER_TYPES = [
        'VILLAGE_HOUSEHOLD' => "Hộ trong thôn",
        'OUTSIDE_PERSON' => "Cá nhân ngoài thôn",
        'BUSINESS' => "Doanh nghiệp",
        'COOPERATIVE' => "Hợp tác xã",
        'ORGANIZATION' => "Tổ chức khác",
    ];
    public const USAGE_FORMS = ['SELF' => "Tự sản xuất", 'LEASE_OUT' => "Cho thuê", 'LEASE_IN' => "Thuê", 'BORROW' => "Mượn", 'PARTNERSHIP' => "Liên kết"];
    public const LAND_TYPES = ["Đất lúa", "Đất màu", "Đất cây lâu năm", "Đất nuôi trồng thủy sản", "Đất vườn", "Đất khác"];
    public const CROPS = ["Lúa", "Ngô", "Rau", "Khoai", "Lạc", "Cây ăn quả", "Cây lâu năm", "Khác"];
    public const SEASONS = ["Xuân", "Mùa", "Đông", "Đông Xuân"];
    public const STATUS_LABELS = ['ACTIVE' => "Đang sản xuất", 'IDLE' => "Tạm nghỉ", 'LEASED' => "Cho thuê", 'ABANDONED' => "Bỏ hoang", 'DELETED' => "Đã xóa"];
    public const LOG_TYPES = ['LAND_PREP' => "Làm đất", 'SOWING' => "Gieo", 'TRANSPLANT' => "Cấy", 'FERTILIZER' => "Bón phân", 'PESTICIDE' => "Phun thuốc", 'IRRIGATION' => "Tưới", 'HARVEST' => "Thu hoạch"];
    public const DAMAGE_TYPES = ['FLOOD' => "Ngập úng", 'DROUGHT' => "Hạn hán", 'PEST' => "Sâu bệnh", 'RAT' => "Chuột", 'STORM' => "Gió bão", 'HAIL' => "Mưa đá", 'OTHER' => "Khác"];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agri_stakeholders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stakeholder_type ENUM('VILLAGE_HOUSEHOLD','OUTSIDE_PERSON','BUSINESS','COOPERATIVE','ORGANIZATION') NOT NULL DEFAULT 'VILLAGE_HOUSEHOLD',
  household_id BIGINT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  identity_number VARCHAR(80) NULL,
  tax_code VARCHAR(80) NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(500) NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_agri_stakeholders_household (household_id),
  KEY idx_agri_stakeholders_name (name),
  CONSTRAINT fk_agri_stakeholders_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agri_land_parcels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_code VARCHAR(40) NOT NULL UNIQUE,
  map_sheet_no VARCHAR(80) NULL,
  parcel_no VARCHAR(80) NULL,
  field_area VARCHAR(255) NULL,
  field_name VARCHAR(255) NULL,
  land_type VARCHAR(120) NULL,
  legal_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  actual_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  cultivated_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  abandoned_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  owner_id BIGINT UNSIGNED NOT NULL,
  producer_id BIGINT UNSIGNED NOT NULL,
  usage_form ENUM('SELF','LEASE_OUT','LEASE_IN','BORROW','PARTNERSHIP') NOT NULL DEFAULT 'SELF',
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  polygon_geojson LONGTEXT NULL,
  status ENUM('ACTIVE','IDLE','LEASED','ABANDONED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_agri_parcels_owner (owner_id),
  KEY idx_agri_parcels_producer (producer_id),
  KEY idx_agri_parcels_status (status),
  KEY idx_agri_parcels_field (field_area),
  CONSTRAINT fk_agri_parcels_owner FOREIGN KEY (owner_id) REFERENCES agri_stakeholders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agri_parcels_producer FOREIGN KEY (producer_id) REFERENCES agri_stakeholders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agri_production_plots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_id BIGINT UNSIGNED NOT NULL,
  plot_code VARCHAR(80) NULL,
  plot_name VARCHAR(160) NOT NULL,
  area DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','IDLE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_agri_plots_parcel (parcel_id),
  CONSTRAINT fk_agri_plots_parcel FOREIGN KEY (parcel_id) REFERENCES agri_land_parcels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agri_crop_seasons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  season_name VARCHAR(80) NOT NULL,
  crop VARCHAR(120) NOT NULL,
  variety VARCHAR(160) NULL,
  area DECIMAL(14,2) NOT NULL DEFAULT 0,
  land_prep_date DATE NULL,
  sowing_date DATE NULL,
  transplant_date DATE NULL,
  fertilizer_date DATE NULL,
  pesticide_date DATE NULL,
  expected_harvest_date DATE NULL,
  actual_harvest_date DATE NULL,
  yield_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  output_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
  cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  profit DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('PLANNED','IN_PROGRESS','HARVESTED','CANCELLED','DELETED') NOT NULL DEFAULT 'IN_PROGRESS',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_agri_seasons_plot (plot_id),
  KEY idx_agri_seasons_crop (crop),
  KEY idx_agri_seasons_name (season_name),
  CONSTRAINT fk_agri_seasons_plot FOREIGN KEY (plot_id) REFERENCES agri_production_plots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agri_production_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id BIGINT UNSIGNED NOT NULL,
  activity_type VARCHAR(50) NOT NULL,
  activity_date DATE NOT NULL,
  actor_name VARCHAR(255) NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  KEY idx_agri_logs_season (season_id),
  CONSTRAINT fk_agri_logs_season FOREIGN KEY (season_id) REFERENCES agri_crop_seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agri_damages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_id BIGINT UNSIGNED NOT NULL,
  season_id BIGINT UNSIGNED NULL,
  damage_type VARCHAR(50) NOT NULL,
  event_date DATE NOT NULL,
  affected_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  damage_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  estimated_output_loss DECIMAL(14,2) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_agri_damages_parcel (parcel_id),
  KEY idx_agri_damages_season (season_id),
  CONSTRAINT fk_agri_damages_parcel FOREIGN KEY (parcel_id) REFERENCES agri_land_parcels(id) ON DELETE CASCADE,
  CONSTRAINT fk_agri_damages_season FOREIGN KEY (season_id) REFERENCES agri_crop_seasons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agri_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_id BIGINT UNSIGNED NULL,
  season_id BIGINT UNSIGNED NULL,
  damage_id BIGINT UNSIGNED NULL,
  file_kind ENUM('IMAGE','DOCUMENT') NOT NULL DEFAULT 'IMAGE',
  category VARCHAR(120) NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  KEY idx_agri_files_parcel (parcel_id),
  KEY idx_agri_files_season (season_id),
  KEY idx_agri_files_damage (damage_id),
  CONSTRAINT fk_agri_files_parcel FOREIGN KEY (parcel_id) REFERENCES agri_land_parcels(id) ON DELETE CASCADE,
  CONSTRAINT fk_agri_files_season FOREIGN KEY (season_id) REFERENCES agri_crop_seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_agri_files_damage FOREIGN KEY (damage_id) REFERENCES agri_damages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function catalogs(): array
    {
        return [
            'owner_types' => $this->pairs($this->ownerTypes()),
            'usage_forms' => $this->pairs($this->usageForms()),
            'land_types' => array_map(fn($v) => ['value' => $v, 'label' => $v], $this->landTypes()),
            'crops' => array_map(fn($v) => ['value' => $v, 'label' => $v], $this->crops()),
            'seasons' => array_map(fn($v) => ['value' => $v, 'label' => $v], $this->seasonsCatalog()),
            'statuses' => $this->pairs($this->statusLabels()),
            'log_types' => $this->pairs($this->logTypes()),
            'damage_types' => $this->pairs($this->damageTypes()),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM agri_land_parcels p INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT p.*, o.name AS owner_name, o.stakeholder_type AS owner_type, pr.name AS producer_name, pr.stakeholder_type AS producer_type,
                    COALESCE(pc.plot_count,0) AS plot_count, cs.crop AS current_crop, cs.season_name AS current_season
             FROM agri_land_parcels p
             INNER JOIN agri_stakeholders o ON o.id=p.owner_id
             INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id
             LEFT JOIN (SELECT parcel_id, COUNT(*) AS plot_count FROM agri_production_plots WHERE status <> 'DELETED' GROUP BY parcel_id) pc ON pc.parcel_id=p.id
             LEFT JOIN (
                SELECT pp.parcel_id, s.crop, s.season_name
                FROM agri_crop_seasons s
                INNER JOIN agri_production_plots pp ON pp.id=s.plot_id
                INNER JOIN (SELECT pp2.parcel_id, MAX(s2.id) AS season_id FROM agri_crop_seasons s2 INNER JOIN agri_production_plots pp2 ON pp2.id=s2.plot_id WHERE s2.status <> 'DELETED' AND pp2.status <> 'DELETED' GROUP BY pp2.parcel_id) latest ON latest.season_id=s.id
             ) cs ON cs.parcel_id=p.id
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalizeParcel($row), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int)ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            "SELECT p.*, o.name AS owner_name, o.stakeholder_type AS owner_type, o.phone AS owner_phone, o.address AS owner_address,
                    pr.name AS producer_name, pr.stakeholder_type AS producer_type, pr.phone AS producer_phone, pr.address AS producer_address,
                    COALESCE(pc.plot_count,0) AS plot_count, cs.crop AS current_crop, cs.season_name AS current_season
             FROM agri_land_parcels p
             INNER JOIN agri_stakeholders o ON o.id=p.owner_id
             INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id
             LEFT JOIN (SELECT parcel_id, COUNT(*) AS plot_count FROM agri_production_plots WHERE status <> 'DELETED' GROUP BY parcel_id) pc ON pc.parcel_id=p.id
             LEFT JOIN (
                SELECT pp.parcel_id, s.crop, s.season_name
                FROM agri_crop_seasons s
                INNER JOIN agri_production_plots pp ON pp.id=s.plot_id
                INNER JOIN (SELECT pp2.parcel_id, MAX(s2.id) AS season_id FROM agri_crop_seasons s2 INNER JOIN agri_production_plots pp2 ON pp2.id=s2.plot_id WHERE s2.status <> 'DELETED' AND pp2.status <> 'DELETED' GROUP BY pp2.parcel_id) latest ON latest.season_id=s.id
             ) cs ON cs.parcel_id=p.id
             WHERE p.id=:id AND p.status <> 'DELETED'",
            ['id' => $id]
        );
        if (!$row) return null;
        $parcel = $this->normalizeParcel($row);
        $parcel['plots'] = $this->plots($id);
        $parcel['seasons'] = $this->seasons($id);
        $parcel['logs'] = $this->logs($id);
        $parcel['damages'] = $this->damages($id);
        $parcel['files'] = $this->files($id);
        return $parcel;
    }

    public function upsertParcel(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $before = $id ? $this->find($id) : null;
        if ($id && !$before) throw new \RuntimeException("Không tìm thấy thửa đất");
        $ownerId = $this->upsertStakeholder((array)($data['owner'] ?? []), 'owner', $before['owner_id'] ?? null);
        $producerId = $this->upsertStakeholder((array)($data['producer'] ?? []), 'producer', $before['producer_id'] ?? null);
        $params = $this->parcelParams($data, $ownerId, $producerId, $userId);
        if ($id) {
            unset($params['created_by']);
            $params['id'] = $id;
            $this->execute('UPDATE agri_land_parcels SET map_sheet_no=:map_sheet_no, parcel_no=:parcel_no, field_area=:field_area, field_name=:field_name, land_type=:land_type, legal_area=:legal_area, actual_area=:actual_area, cultivated_area=:cultivated_area, abandoned_area=:abandoned_area, owner_id=:owner_id, producer_id=:producer_id, usage_form=:usage_form, latitude=:latitude, longitude=:longitude, polygon_geojson=:polygon_geojson, status=:status, note=:note, updated_by=:updated_by WHERE id=:id', $params);
            return $this->find($id);
        }
        $params['parcel_code'] = $this->nextParcelCode();
        $newId = $this->insert('INSERT INTO agri_land_parcels (parcel_code, map_sheet_no, parcel_no, field_area, field_name, land_type, legal_area, actual_area, cultivated_area, abandoned_area, owner_id, producer_id, usage_form, latitude, longitude, polygon_geojson, status, note, created_by, updated_by) VALUES (:parcel_code, :map_sheet_no, :parcel_no, :field_area, :field_name, :land_type, :legal_area, :actual_area, :cultivated_area, :abandoned_area, :owner_id, :producer_id, :usage_form, :latitude, :longitude, :polygon_geojson, :status, :note, :created_by, :updated_by)', $params);
        return $this->find($newId);
    }

    public function softDeleteParcel(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException("Không tìm thấy thửa đất");
        $this->execute('UPDATE agri_land_parcels SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id', ['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]);
    }

    public function addPlot(int $parcelId, array $data): array
    {
        $this->ensureSchema();
        if (!$this->find($parcelId)) throw new \RuntimeException("Không tìm thấy thửa đất");
        $name = trim((string)($data['plot_name'] ?? $data['plotName'] ?? ''));
        if ($name === '') throw new \RuntimeException("Tên lô sản xuất là bắt buộc");
        $id = $this->insert('INSERT INTO agri_production_plots (parcel_id, plot_code, plot_name, area, status, note) VALUES (:parcel_id, :plot_code, :plot_name, :area, :status, :note)', [
            'parcel_id' => $parcelId,
            'plot_code' => trim((string)($data['plot_code'] ?? $data['plotCode'] ?? '')) ?: null,
            'plot_name' => $name,
            'area' => $this->number($data['area'] ?? 0),
            'status' => in_array((string)($data['status'] ?? 'ACTIVE'), ['ACTIVE','IDLE'], true) ? (string)$data['status'] : 'ACTIVE',
            'note' => trim((string)($data['note'] ?? '')) ?: null,
        ]);
        $this->validatePlotTotal($parcelId);
        return $this->fetchOne('SELECT * FROM agri_production_plots WHERE id=:id', ['id' => $id]) ?: [];
    }

    public function addSeason(int $plotId, array $data): array
    {
        $this->ensureSchema();
        $plot = $this->fetchOne('SELECT * FROM agri_production_plots WHERE id=:id AND status <> "DELETED"', ['id' => $plotId]);
        if (!$plot) throw new \RuntimeException("Không tìm thấy lô sản xuất");
        $season = trim((string)($data['season_name'] ?? $data['seasonName'] ?? ''));
        $crop = trim((string)($data['crop'] ?? ''));
        if ($season === '' || $crop === '') throw new \RuntimeException("Mùa vụ và cây trồng là bắt buộc");
        $revenue = $this->number($data['revenue'] ?? 0);
        $cost = $this->number($data['cost'] ?? 0);
        $id = $this->insert('INSERT INTO agri_crop_seasons (plot_id, season_name, crop, variety, area, land_prep_date, sowing_date, transplant_date, fertilizer_date, pesticide_date, expected_harvest_date, actual_harvest_date, yield_value, output_value, sale_price, revenue, cost, profit, status, note) VALUES (:plot_id, :season_name, :crop, :variety, :area, :land_prep_date, :sowing_date, :transplant_date, :fertilizer_date, :pesticide_date, :expected_harvest_date, :actual_harvest_date, :yield_value, :output_value, :sale_price, :revenue, :cost, :profit, :status, :note)', [
            'plot_id' => $plotId, 'season_name' => $season, 'crop' => $crop, 'variety' => trim((string)($data['variety'] ?? '')) ?: null,
            'area' => $this->number($data['area'] ?? $plot['area'] ?? 0), 'land_prep_date' => $this->dateOrNull($data['land_prep_date'] ?? null), 'sowing_date' => $this->dateOrNull($data['sowing_date'] ?? null), 'transplant_date' => $this->dateOrNull($data['transplant_date'] ?? null), 'fertilizer_date' => $this->dateOrNull($data['fertilizer_date'] ?? null), 'pesticide_date' => $this->dateOrNull($data['pesticide_date'] ?? null), 'expected_harvest_date' => $this->dateOrNull($data['expected_harvest_date'] ?? null), 'actual_harvest_date' => $this->dateOrNull($data['actual_harvest_date'] ?? null),
            'yield_value' => $this->number($data['yield_value'] ?? $data['yield'] ?? 0), 'output_value' => $this->number($data['output_value'] ?? $data['output'] ?? 0), 'sale_price' => $this->number($data['sale_price'] ?? 0), 'revenue' => $revenue, 'cost' => $cost, 'profit' => $this->number($data['profit'] ?? ($revenue - $cost)), 'status' => in_array((string)($data['status'] ?? 'IN_PROGRESS'), ['PLANNED','IN_PROGRESS','HARVESTED','CANCELLED'], true) ? (string)$data['status'] : 'IN_PROGRESS', 'note' => trim((string)($data['note'] ?? '')) ?: null,
        ]);
        return $this->fetchOne('SELECT * FROM agri_crop_seasons WHERE id=:id', ['id' => $id]) ?: [];
    }

    public function addLog(int $seasonId, array $data, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->fetchOne('SELECT id FROM agri_crop_seasons WHERE id=:id AND status <> "DELETED"', ['id' => $seasonId])) throw new \RuntimeException("Không tìm thấy mùa vụ");
        $type = (string)($data['activity_type'] ?? $data['activityType'] ?? '');
        if (!isset($this->logTypes()[$type])) $type = 'LAND_PREP';
        $date = $this->dateOrNull($data['activity_date'] ?? $data['activityDate'] ?? null) ?: date('Y-m-d');
        $id = $this->insert('INSERT INTO agri_production_logs (season_id, activity_type, activity_date, actor_name, note, created_by) VALUES (:season_id, :activity_type, :activity_date, :actor_name, :note, :created_by)', ['season_id' => $seasonId, 'activity_type' => $type, 'activity_date' => $date, 'actor_name' => trim((string)($data['actor_name'] ?? $data['actorName'] ?? '')) ?: null, 'note' => trim((string)($data['note'] ?? '')) ?: null, 'created_by' => $userId]);
        return $this->fetchOne('SELECT * FROM agri_production_logs WHERE id=:id', ['id' => $id]) ?: [];
    }

    public function addDamage(int $parcelId, array $data): array
    {
        $this->ensureSchema();
        if (!$this->find($parcelId)) throw new \RuntimeException("Không tìm thấy thửa đất");
        $type = (string)($data['damage_type'] ?? $data['damageType'] ?? 'OTHER');
        if (!isset($this->damageTypes()[$type])) $type = 'OTHER';
        $id = $this->insert('INSERT INTO agri_damages (parcel_id, season_id, damage_type, event_date, affected_area, damage_percent, estimated_output_loss, note) VALUES (:parcel_id, :season_id, :damage_type, :event_date, :affected_area, :damage_percent, :estimated_output_loss, :note)', ['parcel_id' => $parcelId, 'season_id' => (int)($data['season_id'] ?? 0) ?: null, 'damage_type' => $type, 'event_date' => $this->dateOrNull($data['event_date'] ?? null) ?: date('Y-m-d'), 'affected_area' => $this->number($data['affected_area'] ?? 0), 'damage_percent' => min(100, max(0, $this->number($data['damage_percent'] ?? 0))), 'estimated_output_loss' => $this->number($data['estimated_output_loss'] ?? 0), 'note' => trim((string)($data['note'] ?? '')) ?: null]);
        return $this->fetchOne('SELECT * FROM agri_damages WHERE id=:id', ['id' => $id]) ?: [];
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $metrics = $this->fetchOne("SELECT COUNT(*) AS parcels, COUNT(DISTINCT p.owner_id) AS owners, COUNT(DISTINCT p.producer_id) AS producers, COALESCE(SUM(p.actual_area),0) AS total_area, COALESCE(SUM(p.cultivated_area),0) AS cultivated_area, COALESCE(SUM(p.abandoned_area),0) AS abandoned_area FROM agri_land_parcels p INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where", $params) ?: [];
        $plots = $this->fetchOne("SELECT COUNT(*) AS plots FROM agri_production_plots pp INNER JOIN agri_land_parcels p ON p.id=pp.parcel_id INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where AND pp.status <> 'DELETED'", $params) ?: [];
        $production = $this->fetchOne("SELECT COALESCE(SUM(s.output_value),0) AS output_total, COALESCE(AVG(NULLIF(s.yield_value,0)),0) AS avg_yield, COALESCE(SUM(s.revenue),0) AS revenue, COALESCE(SUM(s.cost),0) AS cost, COALESCE(SUM(s.profit),0) AS profit FROM agri_crop_seasons s INNER JOIN agri_production_plots pp ON pp.id=s.plot_id INNER JOIN agri_land_parcels p ON p.id=pp.parcel_id INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where AND s.status <> 'DELETED' AND pp.status <> 'DELETED'", $params) ?: [];
        return [
            'metrics' => [
                'producer_households' => (int)($metrics['producers'] ?? 0), 'land_owners' => (int)($metrics['owners'] ?? 0), 'parcels' => (int)($metrics['parcels'] ?? 0), 'plots' => (int)($plots['plots'] ?? 0),
                'total_area' => (float)($metrics['total_area'] ?? 0), 'cultivated_area' => (float)($metrics['cultivated_area'] ?? 0), 'abandoned_area' => (float)($metrics['abandoned_area'] ?? 0),
                'output_total' => (float)($production['output_total'] ?? 0), 'avg_yield' => (float)($production['avg_yield'] ?? 0), 'revenue' => (float)($production['revenue'] ?? 0), 'cost' => (float)($production['cost'] ?? 0), 'profit' => (float)($production['profit'] ?? 0),
            ],
            'charts' => [
                'crops' => $this->fetchAll("SELECT s.crop AS label, COALESCE(SUM(s.area),0) AS value FROM agri_crop_seasons s INNER JOIN agri_production_plots pp ON pp.id=s.plot_id INNER JOIN agri_land_parcels p ON p.id=pp.parcel_id INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where AND s.status <> 'DELETED' GROUP BY s.crop ORDER BY value DESC LIMIT 10", $params),
                'seasons' => $this->fetchAll("SELECT s.season_name AS label, COUNT(*) AS value FROM agri_crop_seasons s INNER JOIN agri_production_plots pp ON pp.id=s.plot_id INNER JOIN agri_land_parcels p ON p.id=pp.parcel_id INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where AND s.status <> 'DELETED' GROUP BY s.season_name ORDER BY value DESC", $params),
                'damages' => $this->fetchAll("SELECT d.damage_type AS label, COUNT(*) AS value FROM agri_damages d INNER JOIN agri_land_parcels p ON p.id=d.parcel_id INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where GROUP BY d.damage_type ORDER BY value DESC", $params),
            ],
        ];
    }



    public function gisFeatures(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $rows = $this->fetchAll("SELECT p.id, p.parcel_code, p.field_area, p.actual_area, p.latitude, p.longitude, p.polygon_geojson, p.status, o.name AS owner_name, pr.name AS producer_name, cs.crop AS current_crop, cs.season_name AS current_season
             FROM agri_land_parcels p
             INNER JOIN agri_stakeholders o ON o.id=p.owner_id
             INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id
             LEFT JOIN (
                SELECT pp.parcel_id, s.crop, s.season_name
                FROM agri_crop_seasons s
                INNER JOIN agri_production_plots pp ON pp.id=s.plot_id
                INNER JOIN (SELECT pp2.parcel_id, MAX(s2.id) AS season_id FROM agri_crop_seasons s2 INNER JOIN agri_production_plots pp2 ON pp2.id=s2.plot_id WHERE s2.status <> 'DELETED' AND pp2.status <> 'DELETED' GROUP BY pp2.parcel_id) latest ON latest.season_id=s.id
             ) cs ON cs.parcel_id=p.id
             $where AND (p.polygon_geojson IS NOT NULL OR (p.latitude IS NOT NULL AND p.longitude IS NOT NULL))
             ORDER BY p.parcel_code ASC LIMIT 1000", $params);
        return array_map(fn($r) => ['id' => (int)$r['id'], 'parcel_code' => (string)$r['parcel_code'], 'field_area' => (string)($r['field_area'] ?? ''), 'owner_name' => (string)($r['owner_name'] ?? ''), 'producer_name' => (string)($r['producer_name'] ?? ''), 'actual_area' => (float)($r['actual_area'] ?? 0), 'latitude' => $r['latitude'] !== null ? (float)$r['latitude'] : null, 'longitude' => $r['longitude'] !== null ? (float)$r['longitude'] : null, 'polygon_geojson' => (string)($r['polygon_geojson'] ?? ''), 'current_crop' => (string)($r['current_crop'] ?? ''), 'current_season' => (string)($r['current_season'] ?? ''), 'status' => (string)($r['status'] ?? 'ACTIVE')], $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'crop' && !empty($filters['crop'])) $filters['crop'] = (string) $filters['crop'];
        if ($mode === 'season' && !empty($filters['season'])) $filters['season'] = (string) $filters['season'];
        if ($mode === 'damage') {
            $this->ensureSchema();
            [$where, $params] = $this->where($filters, false);
            $rows = $this->fetchAll("SELECT p.parcel_code, p.field_area, o.name AS owner_name, pr.name AS producer_name, d.damage_type, d.event_date, d.affected_area, d.damage_percent, d.estimated_output_loss, d.note FROM agri_damages d INNER JOIN agri_land_parcels p ON p.id=d.parcel_id INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id $where ORDER BY d.event_date DESC, d.id DESC LIMIT 500", $params);
            return $this->table($this->u("B\u00e1o c\u00e1o thi\u1ec7t h\u1ea1i s\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p"), [$this->u("M\u00e3 th\u1eeda"),$this->u("Khu \u0111\u1ed3ng"),$this->u("Ch\u1ee7 s\u1eed d\u1ee5ng"),$this->u("Ng\u01b0\u1eddi s\u1ea3n xu\u1ea5t"),$this->u("Lo\u1ea1i thi\u1ec7t h\u1ea1i"),$this->u("Ng\u00e0y"),$this->u("Di\u1ec7n t\u00edch \u1ea3nh h\u01b0\u1edfng"),$this->u("T\u1ef7 l\u1ec7"),$this->u("S\u1ea3n l\u01b0\u1ee3ng m\u1ea5t"),$this->u("Ghi ch\u00fa")], array_map(fn($r) => [$r['parcel_code'], $r['field_area'], $r['owner_name'], $r['producer_name'], $this->damageTypes()[$r['damage_type']] ?? $r['damage_type'], $r['event_date'], $r['affected_area'], $r['damage_percent'] . '%', $r['estimated_output_loss'], $r['note']], $rows), $filters);
        }
        $filters['page'] = 1;
        $filters['pageSize'] = 500;
        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'area' => $this->u("B\u00e1o c\u00e1o di\u1ec7n t\u00edch s\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p"),
            'crop' => $this->u("B\u00e1o c\u00e1o c\u00e2y tr\u1ed3ng"),
            'season' => $this->u("B\u00e1o c\u00e1o m\u00f9a v\u1ee5"),
            'production' => $this->u("B\u00e1o c\u00e1o s\u1ea3n l\u01b0\u1ee3ng s\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p"),
            'revenue' => $this->u("B\u00e1o c\u00e1o doanh thu s\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p"),
            'producer' => $this->u("Danh s\u00e1ch ch\u1ee7 th\u1ec3 s\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p"),
            default => $this->u("Danh s\u00e1ch th\u1eeda s\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p"),
        };
        return $this->table($title, [$this->u("M\u00e3 th\u1eeda"),$this->u("Khu \u0111\u1ed3ng"),$this->u("Ch\u1ee7 s\u1eed d\u1ee5ng"),$this->u("Ng\u01b0\u1eddi s\u1ea3n xu\u1ea5t"),$this->u("Di\u1ec7n t\u00edch th\u1ef1c t\u1ebf"),$this->u("\u0110ang s\u1ea3n xu\u1ea5t"),$this->u("B\u1ecf hoang"),$this->u("C\u00e2y tr\u1ed3ng hi\u1ec7n t\u1ea1i"),$this->u("M\u00f9a v\u1ee5 hi\u1ec7n t\u1ea1i"),$this->u("Tr\u1ea1ng th\u00e1i")], array_map(fn($r) => [$r['parcel_code'], $r['field_area'], $r['owner_name'], $r['producer_name'], $r['actual_area'], $r['cultivated_area'], $r['abandoned_area'], $r['current_crop'], $r['current_season'], $r['status_label']], $rows), $filters);
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')];
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['p.status <> "DELETED"'];
        $params = [];
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $kw = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(p.parcel_code) LIKE :q_code OR LOWER(p.field_area) LIKE :q_field OR LOWER(p.field_name) LIKE :q_name OR LOWER(o.name) LIKE :q_owner OR LOWER(pr.name) LIKE :q_producer OR LOWER(p.parcel_no) LIKE :q_no)';
            $params += ['q_code' => $kw, 'q_field' => $kw, 'q_name' => $kw, 'q_owner' => $kw, 'q_producer' => $kw, 'q_no' => $kw];
        }
        foreach (['land_type' => 'p.land_type', 'usage_form' => 'p.usage_form', 'status' => 'p.status'] as $key => $col) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$col = :$key"; $params[$key] = $value; }
        }
        $crop = trim((string)($filters['crop'] ?? ''));
        if ($crop !== '') { $where[] = 'EXISTS (SELECT 1 FROM agri_production_plots pp2 INNER JOIN agri_crop_seasons s2 ON s2.plot_id=pp2.id WHERE pp2.parcel_id=p.id AND pp2.status <> "DELETED" AND s2.status <> "DELETED" AND s2.crop = :crop)'; $params['crop'] = $crop; }
        $season = trim((string)($filters['season'] ?? ''));
        if ($season !== '') { $where[] = 'EXISTS (SELECT 1 FROM agri_production_plots pp3 INNER JOIN agri_crop_seasons s3 ON s3.plot_id=pp3.id WHERE pp3.parcel_id=p.id AND pp3.status <> "DELETED" AND s3.status <> "DELETED" AND s3.season_name = :season)'; $params['season'] = $season; }
        $sort = preg_replace('/[^a-z_]/', '', (string)($filters['sort'] ?? 'parcel_code'));
        $direction = strtoupper((string)($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortMap = ['parcel_code' => 'p.parcel_code', 'field_area' => 'p.field_area', 'owner_name' => 'o.name', 'producer_name' => 'pr.name', 'actual_area' => 'p.actual_area', 'status' => 'p.status', 'updated_at' => 'COALESCE(p.updated_at,p.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = 'ORDER BY ' . ($sortMap[$sort] ?? 'p.parcel_code') . ' ' . $direction . ', p.id DESC';
        return $result;
    }

    private function upsertStakeholder(array $data, string $role, ?int $existingId = null): int
    {
        $type = (string)($data['stakeholder_type'] ?? $data['type'] ?? 'VILLAGE_HOUSEHOLD');
        if (!isset($this->ownerTypes()[$type])) $type = 'VILLAGE_HOUSEHOLD';
        $householdId = (int)($data['household_id'] ?? $data['householdId'] ?? 0) ?: null;
        $name = trim((string)($data['name'] ?? ''));
        if ($householdId && $name === '') {
            $h = $this->fetchOne('SELECT head_citizen_name, address, phone FROM households WHERE id=:id', ['id' => $householdId]);
            if ($h) $name = (string)$h['head_citizen_name'];
        }
        if ($name === '') throw new \RuntimeException(($role === 'owner' ? "Chủ sử dụng đất" : "Người sản xuất") . " là bắt buộc");
        $params = ['stakeholder_type' => $type, 'household_id' => $householdId, 'name' => $name, 'identity_number' => trim((string)($data['identity_number'] ?? '')) ?: null, 'tax_code' => trim((string)($data['tax_code'] ?? '')) ?: null, 'phone' => trim((string)($data['phone'] ?? '')) ?: null, 'address' => trim((string)($data['address'] ?? '')) ?: null, 'note' => trim((string)($data['note'] ?? '')) ?: null];
        if ($existingId) {
            $params['id'] = $existingId;
            $this->execute('UPDATE agri_stakeholders SET stakeholder_type=:stakeholder_type, household_id=:household_id, name=:name, identity_number=:identity_number, tax_code=:tax_code, phone=:phone, address=:address, note=:note WHERE id=:id', $params);
            return $existingId;
        }
        return $this->insert('INSERT INTO agri_stakeholders (stakeholder_type, household_id, name, identity_number, tax_code, phone, address, note) VALUES (:stakeholder_type, :household_id, :name, :identity_number, :tax_code, :phone, :address, :note)', $params);
    }

    private function parcelParams(array $data, int $ownerId, int $producerId, int $userId): array
    {
        $actual = $this->number($data['actual_area'] ?? $data['actualArea'] ?? 0);
        $cultivated = $this->number($data['cultivated_area'] ?? $data['cultivatedArea'] ?? 0);
        $abandoned = $this->number($data['abandoned_area'] ?? $data['abandonedArea'] ?? 0);
        if (abs(($cultivated + $abandoned) - $actual) > 0.01) throw new \RuntimeException("Diện tích đang sản xuất + diện tích bỏ hoang phải bằng diện tích thực tế");
        $usage = (string)($data['usage_form'] ?? $data['usageForm'] ?? 'SELF');
        if (!isset($this->usageForms()[$usage])) $usage = 'SELF';
        $status = (string)($data['status'] ?? 'ACTIVE');
        if (!isset($this->statusLabels()[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return ['map_sheet_no' => trim((string)($data['map_sheet_no'] ?? $data['mapSheetNo'] ?? '')) ?: null, 'parcel_no' => trim((string)($data['parcel_no'] ?? $data['parcelNo'] ?? '')) ?: null, 'field_area' => trim((string)($data['field_area'] ?? $data['fieldArea'] ?? '')) ?: null, 'field_name' => trim((string)($data['field_name'] ?? $data['fieldName'] ?? '')) ?: null, 'land_type' => trim((string)($data['land_type'] ?? $data['landType'] ?? '')) ?: null, 'legal_area' => $this->number($data['legal_area'] ?? $data['legalArea'] ?? 0), 'actual_area' => $actual, 'cultivated_area' => $cultivated, 'abandoned_area' => $abandoned, 'owner_id' => $ownerId, 'producer_id' => $producerId, 'usage_form' => $usage, 'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float)$data['latitude'] : null, 'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null, 'polygon_geojson' => trim((string)($data['polygon_geojson'] ?? $data['polygonGeojson'] ?? '')) ?: null, 'status' => $status, 'note' => trim((string)($data['note'] ?? '')) ?: null, 'created_by' => $userId, 'updated_by' => $userId];
    }

    private function nextParcelCode(): string
    {
        $row = $this->fetchOne("SELECT MAX(id) AS max_id FROM agri_land_parcels");
        return 'NN09-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function validatePlotTotal(int $parcelId): void
    {
        $row = $this->fetchOne('SELECT p.actual_area, COALESCE(SUM(pp.area),0) AS plot_area FROM agri_land_parcels p LEFT JOIN agri_production_plots pp ON pp.parcel_id=p.id AND pp.status <> "DELETED" WHERE p.id=:id GROUP BY p.id, p.actual_area', ['id' => $parcelId]);
        if ($row && (float)$row['plot_area'] - (float)$row['actual_area'] > 0.01) throw new \RuntimeException("Tổng diện tích các lô không được vượt diện tích thực tế của thửa");
    }

    private function plots(int $parcelId): array { return $this->fetchAll('SELECT * FROM agri_production_plots WHERE parcel_id=:id AND status <> "DELETED" ORDER BY id ASC', ['id' => $parcelId]); }
    private function seasons(int $parcelId): array { return $this->fetchAll('SELECT s.*, pp.plot_name FROM agri_crop_seasons s INNER JOIN agri_production_plots pp ON pp.id=s.plot_id WHERE pp.parcel_id=:id AND pp.status <> "DELETED" AND s.status <> "DELETED" ORDER BY COALESCE(s.sowing_date,s.created_at) DESC, s.id DESC', ['id' => $parcelId]); }
    private function logs(int $parcelId): array { return $this->fetchAll('SELECT l.*, s.crop, s.season_name FROM agri_production_logs l INNER JOIN agri_crop_seasons s ON s.id=l.season_id INNER JOIN agri_production_plots pp ON pp.id=s.plot_id WHERE pp.parcel_id=:id ORDER BY l.activity_date DESC, l.id DESC', ['id' => $parcelId]); }
    private function damages(int $parcelId): array { return $this->fetchAll('SELECT * FROM agri_damages WHERE parcel_id=:id ORDER BY event_date DESC, id DESC', ['id' => $parcelId]); }
    private function files(int $parcelId): array { return $this->fetchAll('SELECT * FROM agri_files WHERE parcel_id=:id ORDER BY created_at DESC, id DESC', ['id' => $parcelId]); }

    private function normalizeParcel(array $row): array
    {
        return ['id' => (int)$row['id'], 'parcel_code' => (string)$row['parcel_code'], 'map_sheet_no' => (string)($row['map_sheet_no'] ?? ''), 'parcel_no' => (string)($row['parcel_no'] ?? ''), 'field_area' => (string)($row['field_area'] ?? ''), 'field_name' => (string)($row['field_name'] ?? ''), 'land_type' => (string)($row['land_type'] ?? ''), 'legal_area' => (float)($row['legal_area'] ?? 0), 'actual_area' => (float)($row['actual_area'] ?? 0), 'cultivated_area' => (float)($row['cultivated_area'] ?? 0), 'abandoned_area' => (float)($row['abandoned_area'] ?? 0), 'owner_id' => (int)$row['owner_id'], 'owner_name' => (string)($row['owner_name'] ?? ''), 'owner_type' => (string)($row['owner_type'] ?? ''), 'producer_id' => (int)$row['producer_id'], 'producer_name' => (string)($row['producer_name'] ?? ''), 'producer_type' => (string)($row['producer_type'] ?? ''), 'usage_form' => (string)($row['usage_form'] ?? 'SELF'), 'usage_form_label' => $this->usageForms()[$row['usage_form'] ?? 'SELF'] ?? "Tự sản xuất", 'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float)$row['latitude'] : null, 'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float)$row['longitude'] : null, 'polygon_geojson' => (string)($row['polygon_geojson'] ?? ''), 'status' => (string)($row['status'] ?? 'ACTIVE'), 'status_label' => $this->statusLabels()[$row['status'] ?? 'ACTIVE'] ?? "Đang sản xuất", 'note' => (string)($row['note'] ?? ''), 'plot_count' => (int)($row['plot_count'] ?? 0), 'current_crop' => (string)($row['current_crop'] ?? ''), 'current_season' => (string)($row['current_season'] ?? ''), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null];
    }


    private function ownerTypes(): array { return ['VILLAGE_HOUSEHOLD' => $this->u('H\u1ed9 trong th\u00f4n'), 'OUTSIDE_PERSON' => $this->u('C\u00e1 nh\u00e2n ngo\u00e0i th\u00f4n'), 'BUSINESS' => $this->u('Doanh nghi\u1ec7p'), 'COOPERATIVE' => $this->u('H\u1ee3p t\u00e1c x\u00e3'), 'ORGANIZATION' => $this->u('T\u1ed5 ch\u1ee9c kh\u00e1c')]; }
    private function usageForms(): array { return ['SELF' => $this->u('T\u1ef1 s\u1ea3n xu\u1ea5t'), 'LEASE_OUT' => $this->u('Cho thu\u00ea'), 'LEASE_IN' => $this->u('Thu\u00ea'), 'BORROW' => $this->u('M\u01b0\u1ee3n'), 'PARTNERSHIP' => $this->u('Li\u00ean k\u1ebft')]; }
    private function landTypes(): array { return [$this->u('\u0110\u1ea5t l\u00faa'), $this->u('\u0110\u1ea5t m\u00e0u'), $this->u('\u0110\u1ea5t c\u00e2y l\u00e2u n\u0103m'), $this->u('\u0110\u1ea5t nu\u00f4i tr\u1ed3ng th\u1ee7y s\u1ea3n'), $this->u('\u0110\u1ea5t v\u01b0\u1eddn'), $this->u('\u0110\u1ea5t kh\u00e1c')]; }
    private function crops(): array { return [$this->u('L\u00faa'), $this->u('Ng\u00f4'), $this->u('Rau'), $this->u('Khoai'), $this->u('L\u1ea1c'), $this->u('C\u00e2y \u0103n qu\u1ea3'), $this->u('C\u00e2y l\u00e2u n\u0103m'), $this->u('Kh\u00e1c')]; }
    private function seasonsCatalog(): array { return [$this->u('Xu\u00e2n'), $this->u('M\u00f9a'), $this->u('\u0110\u00f4ng'), $this->u('\u0110\u00f4ng Xu\u00e2n')]; }
    private function statusLabels(): array { return ['ACTIVE' => $this->u('\u0110ang s\u1ea3n xu\u1ea5t'), 'IDLE' => $this->u('T\u1ea1m ngh\u1ec9'), 'LEASED' => $this->u('Cho thu\u00ea'), 'ABANDONED' => $this->u('B\u1ecf hoang'), 'DELETED' => $this->u('\u0110\u00e3 x\u00f3a')]; }
    private function logTypes(): array { return ['LAND_PREP' => $this->u('L\u00e0m \u0111\u1ea5t'), 'SOWING' => $this->u('Gieo'), 'TRANSPLANT' => $this->u('C\u1ea5y'), 'FERTILIZER' => $this->u('B\u00f3n ph\u00e2n'), 'PESTICIDE' => $this->u('Phun thu\u1ed1c'), 'IRRIGATION' => $this->u('T\u01b0\u1edbi'), 'HARVEST' => $this->u('Thu ho\u1ea1ch')]; }
    private function damageTypes(): array { return ['FLOOD' => $this->u('Ng\u1eadp \u00fang'), 'DROUGHT' => $this->u('H\u1ea1n h\u00e1n'), 'PEST' => $this->u('S\u00e2u b\u1ec7nh'), 'RAT' => $this->u('Chu\u1ed9t'), 'STORM' => $this->u('Gi\u00f3 b\u00e3o'), 'HAIL' => $this->u('M\u01b0a \u0111\u00e1'), 'OTHER' => $this->u('Kh\u00e1c')]; }
    private function u(string $value): string { return json_decode('"' . $value . '"') ?: $value; }

    private function pairs(array $map): array { return array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys($map), array_values($map)); }
    private function number(mixed $value): float { return max(0, (float)str_replace(',', '.', (string)$value)); }
    private function dateOrNull(mixed $value): ?string { $value = trim((string)$value); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null; }
}
