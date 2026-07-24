<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\PhotoGallery;
use App\Services\FileStorageService;

final class PhotoGalleryController extends BaseController
{
    private PhotoGallery $gallery;
    private FileStorageService $storage;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->gallery = new PhotoGallery();
        $this->storage = new FileStorageService();
    }

    public function index(): void
    {
        $this->requirePermission('photo_gallery', 'read');
        $this->ok($this->gallery->paginate($this->filters()));
    }

    public function catalogs(): void
    {
        $this->requirePermission('photo_gallery', 'read');
        $this->ok($this->gallery->catalogs());
    }

    public function dashboard(): void
    {
        $this->requirePermission('photo_gallery', 'read');
        $this->ok($this->gallery->dashboard($this->filters()));
    }

    public function albums(): void
    {
        $this->requirePermission('photo_gallery', 'read');
        $this->ok($this->gallery->albums());
    }

    public function createAlbum(): void
    {
        $user = $this->requirePermission('photo_gallery', 'create');
        try {
            $row = $this->gallery->createAlbum((array)$this->input(), (int)$user['id']);
            $this->audit($user, 'photo_gallery', 'album_create', 'Them album anh', $row['id'], ['after' => $row]);
            $this->ok($row);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function show(string $id): void
    {
        $this->requirePermission('photo_gallery', 'read');
        $row = $this->gallery->findItem((int)$id);
        if (!$row) $this->fail('Khong tim thay anh', 404);
        $this->ok($row);
    }

    public function upload(): void
    {
        $user = $this->requirePermission('photo_gallery', 'upload');
        $files = $this->uploads($_FILES['files'] ?? $_FILES['file'] ?? null);
        if (!$files) $this->fail('Vui long chon anh', 422);
        $rows = [];
        try {
            foreach ($files as $file) {
                $inspection = $this->storage->inspectUpload($file, 'IMAGE', 'photo_gallery');
                if (!str_starts_with($inspection['mime'], 'image/')) throw new \RuntimeException('Kho anh chi nhan file hinh anh');
                $stored = $this->storage->storeUpload($file, 'photo_gallery', 'images', $inspection['extension']);
                $rows[] = $this->gallery->createItem([
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'album_id' => $_POST['album_id'] ?? '',
                    'event_date' => $_POST['event_date'] ?? '',
                    'area_code' => $_POST['area_code'] ?? '',
                    'source_module' => $_POST['source_module'] ?? '',
                    'source_id' => $_POST['source_id'] ?? '',
                    'tags' => $_POST['tags'] ?? '',
                    'original_name' => $file['name'] ?? '',
                    'file_size' => $file['size'] ?? 0,
                ], $stored, $inspection, (int)$user['id']);
            }
            $this->audit($user, 'photo_gallery', 'upload', 'Upload anh vao kho anh', null, ['files' => array_column($rows, 'id')]);
            $this->ok(count($rows) === 1 ? $rows[0] : ['items' => $rows]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('photo_gallery', 'update');
        try {
            $before = $this->gallery->findItem((int)$id);
            if (!$before) $this->fail('Khong tim thay anh', 404);
            $row = $this->gallery->updateItem((int)$id, (array)$this->input(), (int)$user['id']);
            $this->audit($user, 'photo_gallery', 'update', 'Cap nhat thong tin anh', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('photo_gallery', 'delete');
        $before = $this->gallery->findItem((int)$id);
        if (!$before) $this->fail('Khong tim thay anh', 404);
        $this->gallery->softDeleteItem((int)$id, (int)$user['id']);
        $this->audit($user, 'photo_gallery', 'delete', 'Xoa anh kho anh', $id, ['before' => $before]);
        $this->ok(['id' => (int)$id]);
    }

    public function preview(string $id): void
    {
        $this->stream($id, false);
    }

    public function download(string $id): void
    {
        $this->stream($id, true);
    }

    private function stream(string $id, bool $download): void
    {
        $this->requirePermission('photo_gallery', $download ? 'download' : 'read');
        $item = $this->gallery->findItem((int)$id);
        if (!$item) $this->fail('Khong tim thay anh', 404);
        $path = $this->storage->safeFilePath($this->gallery->itemPath((int)$id) ?? '');
        if (!$path || !is_file($path)) $this->fail('Anh khong con ton tai', 404);
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        if (!str_starts_with($mime, 'image/')) $this->fail('File khong phai hinh anh', 415);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode((string)$item['original_name']) . '"');
        readfile($path);
        exit;
    }

    private function uploads(mixed $file): array
    {
        if (!is_array($file)) return [];
        if (!is_array($file['name'] ?? null)) return [$file];
        $rows = [];
        foreach ($file['name'] as $index => $name) {
            $rows[] = ['name' => $name, 'type' => $file['type'][$index] ?? null, 'tmp_name' => $file['tmp_name'][$index] ?? null, 'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE, 'size' => $file['size'][$index] ?? 0];
        }
        return $rows;
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 24),
            'search' => $this->query('search', $this->query('q', '')),
            'album_id' => $this->query('album_id', $this->query('albumId', '')),
            'tag' => $this->query('tag', ''),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'source_module' => $this->query('source_module', $this->query('sourceModule', '')),
            'date_from' => $this->query('date_from', $this->query('dateFrom', '')),
            'date_to' => $this->query('date_to', $this->query('dateTo', '')),
            'sort' => $this->query('sort', 'created_at'),
            'direction' => $this->query('direction', 'DESC'),
        ];
    }
}
