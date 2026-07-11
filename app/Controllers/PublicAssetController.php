<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\PublicAsset;
use App\Services\FileStorageService;

final class PublicAssetController extends BaseController
{
    private PublicAsset $assets;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->assets = new PublicAsset();
    }

    public function index(): void { $this->requirePermission('public_assets', 'read'); $this->ok($this->assets->paginate($this->filters())); }
    public function catalogs(): void { $this->requirePermission('public_assets', 'read'); $this->ok($this->assets->catalogs()); }
    public function dashboard(): void { $this->requirePermission('public_assets', 'read'); $this->ok($this->assets->dashboard($this->filters())); }
    public function gis(): void { $this->requirePermission('public_assets', 'read'); $this->ok(['items' => $this->assets->gisFeatures($this->filters())]); }

    public function show(string $id): void
    {
        $this->requirePermission('public_assets', 'read');
        $row = $this->assets->find((int)$id);
        if (!$row) $this->fail('Khong tim thay cong trinh', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('public_assets', 'create');
        $row = $this->assets->upsert((array)$this->input(), (int)$user['id']);
        $this->audit($user, 'public_assets', 'create', 'Them cong trinh cong cong', $row['id'], ['after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        $before = $this->assets->find((int)$id);
        if (!$before) $this->fail('Khong tim thay cong trinh', 404);
        $row = $this->assets->upsert((array)$this->input(), (int)$user['id'], (int)$id);
        $this->audit($user, 'public_assets', 'update', 'Cap nhat cong trinh cong cong', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function uploadPhoto(string $id): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        $assetId = (int)$id;
        if (!$this->assets->find($assetId)) $this->fail('Khong tim thay cong trinh', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) $this->fail('Vui long chon anh cong trinh', 422);
        $storage = new FileStorageService();
        $info = $storage->inspectUpload($file, 'IMAGE', 'public_asset');
        if (!in_array($info['mime'], ['image/jpeg','image/png','image/webp'], true)) throw new \RuntimeException('Anh cong trinh chi ho tro JPG, PNG hoac WEBP');
        $stored = $storage->storeUpload($file, 'public_asset', 'images', $info['extension']);
        $url = '/' . ltrim(str_replace('\\', '/', $stored['file_path']), '/');
        $row = $this->assets->setCoverPhoto($assetId, $url, (int)$user['id']);
        $this->audit($user, 'public_assets', 'upload_photo', 'Upload anh cong trinh cong cong', $assetId, ['file' => $stored]);
        $this->ok(['item' => $row, 'url' => $url]);
    }

    public function deletePhoto(string $id): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        $assetId = (int)$id;
        if (!$this->assets->find($assetId)) $this->fail('Khong tim thay cong trinh', 404);
        $row = $this->assets->setCoverPhoto($assetId, null, (int)$user['id']);
        $this->audit($user, 'public_assets', 'delete_photo', 'Xoa anh cong trinh cong cong', $assetId);
        $this->ok(['item' => $row]);
    }
    public function destroy(string $id): void
    {
        $user = $this->requirePermission('public_assets', 'delete');
        $before = $this->assets->find((int)$id);
        if (!$before) $this->fail('Khong tim thay cong trinh', 404);
        $this->assets->softDelete((int)$id, (int)$user['id']);
        $this->audit($user, 'public_assets', 'delete', 'Xoa cong trinh cong cong', $id, ['before' => $before]);
        $this->ok(['id' => (int)$id]);
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'type_id' => $this->query('type_id', $this->query('typeId', '')),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'status' => $this->query('status', ''),
            'located' => $this->query('located', ''),
            'area_min' => $this->query('area_min', $this->query('areaMin', '')),
            'area_max' => $this->query('area_max', $this->query('areaMax', '')),
            'sort' => $this->query('sort', 'asset_code'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }
}