<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\House;
use App\Services\FileStorageService;

final class HouseController extends BaseController
{
    private House $houses;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->houses = new House();
    }

    public function index(): void
    {
        $this->requirePermission('houses', 'read');
        $this->ok($this->houses->paginate($this->filters()));
    }

    public function catalogs(): void
    {
        $this->requirePermission('houses', 'read');
        $this->ok($this->houses->catalogs());
    }

    public function dashboard(): void
    {
        $this->requirePermission('houses', 'read');
        $this->ok($this->houses->dashboard($this->filters()));
    }

    public function householdSearch(): void
    {
        $this->requirePermission('houses', 'read');
        $this->ok(['items' => $this->houses->searchHouseholds((string)$this->query('q', $this->query('search', '')))]);
    }

    public function byHousehold(string $householdId): void
    {
        $this->requirePermission('houses', 'read');
        $items = $this->houses->byHousehold((int)$householdId);
        $this->ok(['items' => $items, 'total' => count($items)]);
    }

    public function gis(): void
    {
        $this->requirePermission('houses', 'read');
        $this->ok(['items' => $this->houses->gisFeatures($this->filters())]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('houses', 'read');
        $row = $this->houses->find((int)$id);
        if (!$row) $this->fail('Khong tim thay nha o', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('houses', 'create');
        $row = $this->houses->upsert((array)$this->input(), (int)$user['id']);
        $this->audit($user, 'houses', 'create', 'Them nha o va cong trinh', $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('houses', 'update');
        $before = $this->houses->find((int)$id);
        if (!$before) $this->fail('Khong tim thay nha o', 404);
        $row = $this->houses->upsert((array)$this->input(), (int)$user['id'], (int)$id);
        $this->audit($user, 'houses', 'update', 'Cap nhat nha o va cong trinh', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('houses', 'delete');
        $before = $this->houses->find((int)$id);
        if (!$before) $this->fail('Khong tim thay nha o', 404);
        $this->houses->softDelete((int)$id, (int)$user['id']);
        $this->audit($user, 'houses', 'delete', 'Xoa nha o va cong trinh', $id, ['before' => $before, 'after' => null]);
        $this->ok(['id' => (int)$id]);
    }

    public function uploadPhoto(string $id): void
    {
        $user = $this->requirePermission('houses', 'update');
        $houseId = (int)$id;
        if (!$this->houses->find($houseId)) $this->fail('Khong tim thay nha o', 404);
        $uploads = $this->normalizeUploads($_FILES['file'] ?? $_FILES['files'] ?? null);
        if (!$uploads) $this->fail('Vui long chon anh', 422);
        $type = (string)($_POST['photo_type'] ?? $_POST['type'] ?? 'Khac');
        $description = trim((string)($_POST['description'] ?? ''));
        $storage = new FileStorageService();
        $items = [];
        foreach ($uploads as $file) {
            $info = $storage->inspectUpload($file, 'IMAGE', 'house');
            if (!in_array($info['mime'], ['image/jpeg','image/png','image/webp'], true)) throw new \RuntimeException('Anh nha o chi ho tro JPG, PNG hoac WEBP');
            $stored = $storage->storeUpload($file, 'house', 'images', $info['extension']);
            $item = $this->houses->addPhoto($houseId, $stored, $file, $info['mime'], $type, $description, (int)$user['id']);
            $items[] = $item;
            $this->audit($user, 'houses', 'upload_photo', 'Upload anh nha o', $houseId, ['file' => $item]);
        }
        $this->ok(['items' => $items]);
    }

    public function deletePhoto(string $id, string $photoId): void
    {
        $user = $this->requirePermission('houses', 'delete');
        $photo = $this->houses->photo((int)$photoId);
        if (!$photo || (int)$photo['house_id'] !== (int)$id) $this->fail('Khong tim thay anh', 404);
        $before = $this->houses->deletePhoto((int)$photoId, (int)$user['id']);
        $this->audit($user, 'houses', 'delete_photo', 'Xoa anh nha o', $id, ['before' => $before]);
        $this->ok(['id' => (int)$photoId]);
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'house_type' => $this->query('house_type', $this->query('houseType', '')),
            'structure_type' => $this->query('structure_type', $this->query('structureType', '')),
            'condition' => $this->query('condition', ''),
            'solidity' => $this->query('solidity', ''),
            'floors' => $this->query('floors', ''),
            'usage' => $this->query('usage', ''),
            'legal_status' => $this->query('legal_status', $this->query('legalStatus', '')),
            'internet' => $this->query('internet', ''),
            'security_camera' => $this->query('security_camera', $this->query('securityCamera', '')),
            'fire_extinguisher' => $this->query('fire_extinguisher', $this->query('fireExtinguisher', '')),
            'fire_risk' => $this->query('fire_risk', $this->query('fireRisk', '')),
            'located' => $this->query('located', ''),
            'status' => $this->query('status', ''),
            'sort' => $this->query('sort', 'house_code'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }

    private function normalizeUploads(mixed $input): array
    {
        if (!is_array($input) || !isset($input['name'])) return [];
        if (!is_array($input['name'])) return [$input];
        $items = [];
        foreach ($input['name'] as $i => $name) $items[] = ['name' => $name, 'type' => $input['type'][$i] ?? '', 'tmp_name' => $input['tmp_name'][$i] ?? '', 'error' => $input['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $input['size'][$i] ?? 0];
        return $items;
    }
}
