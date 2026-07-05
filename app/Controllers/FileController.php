<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\FileAttachment;
use App\Services\FileStorageService;

final class FileController extends BaseController
{
    private FileAttachment $files;
    private FileStorageService $storage;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->files = new FileAttachment();
        $this->storage = new FileStorageService();
    }

    public function upload(): void
    {
        try {
            $entityType = $this->storage->normalizeEntityType((string) ($_POST['entity_type'] ?? $_POST['module'] ?? ''));
            $entityId = (int) ($_POST['entity_id'] ?? $_POST['entityId'] ?? 0);
            $fileType = $this->storage->normalizeFileType((string) ($_POST['file_type'] ?? $_POST['fileType'] ?? 'OTHER'));
            $categoryInput = (string) ($_POST['category'] ?? $_POST['profileSection'] ?? $_POST['profile_section'] ?? '');
            $description = trim((string) ($_POST['description'] ?? ''));

            $this->storage->validateEntity($entityType, $entityId);
            $user = $this->requirePermission($this->storage->permissionModule($entityType), 'update');
            if (empty($_FILES['file'])) $this->fail('Vui lòng chọn file');

            $file = $_FILES['file'];
            $inspection = $this->storage->inspectUpload($file, $fileType, $entityType);
            $category = $this->storage->normalizeCategory($categoryInput, $fileType, $inspection['mime']);
            $stored = $this->storage->storeUpload($file, $entityType, $category, $inspection['extension']);
            $originalName = basename((string) $file['name']);

            $row = $this->files->create([
                'module' => $this->storage->moduleForEntity($entityType),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'category' => $category,
                'file_type' => $fileType,
                'file_name' => $originalName,
                'original_name' => $originalName,
                'stored_name' => $stored['stored_name'],
                'file_path' => $stored['file_path'],
                'mime_type' => $inspection['mime'],
                'file_size' => (int) $file['size'],
                'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
                'profile_section' => $categoryInput !== '' ? preg_replace('/[^a-z0-9_\-]/', '', strtolower($categoryInput)) : $category,
            ], (int) $user['id']);
            $this->audit($user, $entityType, 'upload', 'Upload file đính kèm', $entityId, ['file' => $row['id'] ?? null, 'type' => $fileType, 'category' => $category]);
            $this->ok($this->files->normalizeRow($row));
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 400);
        }
    }

    public function index(string $entityType = '', string $entityId = ''): void
    {
        $entityType = $this->storage->normalizeEntityType($entityType !== '' ? $entityType : (string) $this->query('entity_type', $this->query('module', '')));
        $entityId = $entityId !== '' ? $entityId : (string) $this->query('entity_id', $this->query('entityId', ''));
        if (!in_array($entityType, ['household','citizen'], true) || (int) $entityId <= 0) {
            $this->fail('Invalid file query', 422);
        }
        $this->requirePermission($this->storage->permissionModule($entityType), 'read');
        $this->ok(array_map(fn(array $row): array => $this->files->normalizeRow($row), $this->files->byEntity($entityType, (int) $entityId)));
    }

    public function show(string $id): void
    {
        $file = $this->files->find((int) $id);
        if (!$file) $this->fail('Không tìm thấy file', 404);
        $entityType = $this->storage->normalizeEntityType((string) ($file['entity_type'] ?? $file['module'] ?? ''));
        $this->requirePermission($this->storage->permissionModule($entityType), 'read');
        $this->ok($this->files->normalizeRow($file));
    }

    public function download(string $id): void
    {
        $this->streamFile($id, true);
    }

    public function preview(string $id): void
    {
        $this->streamFile($id, false);
    }

    public function destroy(string $id): void
    {
        $file = $this->files->find((int) $id);
        if (!$file) $this->fail('Không tìm thấy file', 404);
        $entityType = $this->storage->normalizeEntityType((string) ($file['entity_type'] ?? $file['module'] ?? ''));
        $user = $this->requireFileMutationPermission($entityType);
        $this->files->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, $entityType, 'delete_file', 'Xóa file đính kèm', $file['entity_id'] ?? null, ['file' => (int) $id, 'name' => $file['original_name'] ?? $file['file_name'] ?? '']);
        $this->ok(['id' => (int) $id]);
    }

    private function requireFileMutationPermission(string $entityType): array
    {
        $module = $this->storage->permissionModule($entityType);
        $user = $this->user();
        $this->verifyCsrfToken();
        if (!$this->users()->can($user, $module, 'delete') && !$this->users()->can($user, $module, 'update')) {
            $this->fail('Không có quyền xóa file module ' . $module, 403);
        }
        return $user;
    }
    private function streamFile(string $id, bool $download): void
    {
        $file = $this->files->find((int) $id);
        if (!$file) $this->fail('Không tìm thấy file', 404);
        $entityType = $this->storage->normalizeEntityType((string) ($file['entity_type'] ?? $file['module'] ?? ''));
        $this->requirePermission($this->storage->permissionModule($entityType), 'read');
        $path = $this->storage->safeFilePath((string) $file['file_path']);
        if ($path === null || !is_file($path)) $this->fail('File is no longer available on the server', 404);
        $mime = (string) ($file['mime_type'] ?: 'application/octet-stream');
        if (!$download && !$this->storage->canPreview($mime)) $download = true;
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode((string) ($file['original_name'] ?? $file['file_name'] ?? 'attachment')) . '"');
        readfile($path);
        exit;
    }
}
