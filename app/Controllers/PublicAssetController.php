<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\PublicAsset;

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
            'sort' => $this->query('sort', 'asset_code'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }
}