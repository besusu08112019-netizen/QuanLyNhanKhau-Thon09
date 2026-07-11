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
    public function inventoryCatalogs(): void { $this->requirePermission('public_assets', 'read'); $this->ok($this->assets->inventoryCatalogs()); }
    public function inventoryDashboard(): void { $this->requirePermission('public_assets', 'read'); $this->ok($this->assets->inventoryDashboard($this->filters())); }

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
        $this->ok(['item' => $row, 'url' => $row['cover_photo_url'] ?? null]);
    }

    public function photo(string $id): void
    {
        $this->requirePermission('public_assets', 'read');
        $path = $this->assets->coverPhotoPath((int)$id);
        if (!$path) $this->fail('Cong trinh chua co anh', 404);
        $storage = new FileStorageService();
        $file = $storage->safeFilePath($path);
        if (!$file || !is_file($file)) $this->fail('Anh cong trinh khong con ton tai', 404);
        $mime = mime_content_type($file) ?: 'application/octet-stream';
        if (!str_starts_with($mime, 'image/')) $this->fail('File khong phai hinh anh', 415);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=300');
        readfile($file);
        exit;
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

    public function inventoryIndex(string $id): void
    {
        $this->requirePermission('public_assets', 'read');
        if (!$this->assets->find((int)$id)) $this->fail('Khong tim thay cong trinh', 404);
        try {
            $this->ok($this->assets->inventoryList((int)$id));
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function inventoryStore(string $id): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        if (!$this->assets->find((int)$id)) $this->fail('Khong tim thay cong trinh', 404);
        try {
            $row = $this->assets->upsertInventoryItem((int)$id, (array)$this->input(), (int)$user['id']);
            $this->audit($user, 'public_assets', 'inventory_create', 'Them tai san kiem ke cong trinh cong cong', $row['id'], ['after' => $row, 'asset_id' => (int)$id]);
            $this->ok($row);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function inventoryUpdate(string $id, string $itemId): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        try {
            $before = $this->assets->findInventoryItem((int)$id, (int)$itemId);
            if (!$before) $this->fail('Khong tim thay tai san kiem ke', 404);
            $row = $this->assets->upsertInventoryItem((int)$id, (array)$this->input(), (int)$user['id'], (int)$itemId);
            $this->audit($user, 'public_assets', 'inventory_update', 'Cap nhat tai san kiem ke cong trinh cong cong', $itemId, ['before' => $before, 'after' => $row, 'asset_id' => (int)$id]);
            $this->ok($row);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function inventoryDestroy(string $id, string $itemId): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        try {
            $before = $this->assets->findInventoryItem((int)$id, (int)$itemId);
            if (!$before) $this->fail('Khong tim thay tai san kiem ke', 404);
            $this->assets->softDeleteInventoryItem((int)$id, (int)$itemId, (int)$user['id']);
            $this->audit($user, 'public_assets', 'inventory_delete', 'Xoa tai san kiem ke cong trinh cong cong', $itemId, ['before' => $before, 'asset_id' => (int)$id]);
            $this->ok(['id' => (int)$itemId, 'public_asset_id' => (int)$id]);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function inventoryUploadPhoto(string $id, string $itemId): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        if (!$this->assets->findInventoryItem((int)$id, (int)$itemId)) $this->fail('Khong tim thay tai san kiem ke', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) $this->fail('Vui long chon anh tai san', 422);
        $storage = new FileStorageService();
        $info = $storage->inspectUpload($file, 'IMAGE', 'public_asset_inventory');
        if (!in_array($info['mime'], ['image/jpeg','image/png','image/webp'], true)) throw new \RuntimeException('Anh tai san chi ho tro JPG, PNG hoac WEBP');
        $stored = $storage->storeUpload($file, 'public_asset_inventory', 'images', $info['extension']);
        $url = '/' . ltrim(str_replace('\\', '/', $stored['file_path']), '/');
        $row = $this->assets->setInventoryPhoto((int)$id, (int)$itemId, $url, (int)$user['id']);
        $this->audit($user, 'public_assets', 'inventory_upload_photo', 'Upload anh tai san kiem ke', $itemId, ['file' => $stored, 'asset_id' => (int)$id]);
        $this->ok(['item' => $row, 'url' => $row['photo_url'] ?? null]);
    }

    public function inventoryPhoto(string $id, string $itemId): void
    {
        $this->requirePermission('public_assets', 'read');
        $path = $this->assets->inventoryPhotoPath((int)$id, (int)$itemId);
        if (!$path) $this->fail('Tai san chua co anh', 404);
        $storage = new FileStorageService();
        $file = $storage->safeFilePath($path);
        if (!$file || !is_file($file)) $this->fail('Anh tai san khong con ton tai', 404);
        $mime = mime_content_type($file) ?: 'application/octet-stream';
        if (!str_starts_with($mime, 'image/')) $this->fail('File khong phai hinh anh', 415);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=300');
        readfile($file);
        exit;
    }

    public function inventoryDeletePhoto(string $id, string $itemId): void
    {
        $user = $this->requirePermission('public_assets', 'update');
        if (!$this->assets->findInventoryItem((int)$id, (int)$itemId)) $this->fail('Khong tim thay tai san kiem ke', 404);
        $row = $this->assets->setInventoryPhoto((int)$id, (int)$itemId, null, (int)$user['id']);
        $this->audit($user, 'public_assets', 'inventory_delete_photo', 'Xoa anh tai san kiem ke', $itemId, ['asset_id' => (int)$id]);
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
