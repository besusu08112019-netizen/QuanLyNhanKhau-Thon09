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
            $this->requirePermission($this->storage->permissionModule($entityType), 'read');
            $user = $this->requirePermission('file', 'upload');
            if (empty($_FILES['file'])) $this->fail('Vui long chon file');

            $uploads = $this->normalizeUploadedFiles($_FILES['file']);
            $rows = [];
            foreach ($uploads as $file) {
                $rows[] = $this->uploadOne($file, $entityType, $entityId, $fileType, $categoryInput, $description, (int) $user['id']);
            }

            $this->audit($user, $entityType, 'upload', 'Upload file dinh kem', $entityId, ['files' => array_column($rows, 'id'), 'type' => $fileType, 'category' => $categoryInput]);
            $this->ok(count($rows) === 1 ? $rows[0] : $rows);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 400);
        }
    }

    public function index(string $entityType = '', string $entityId = ''): void
    {
        $entityType = $this->storage->normalizeEntityType($entityType !== '' ? $entityType : (string) $this->query('entity_type', $this->query('module', '')));
        $entityId = $entityId !== '' ? $entityId : (string) $this->query('entity_id', $this->query('entityId', ''));
        if (!in_array($entityType, ['household','citizen'], true) || (int) $entityId <= 0) {
            $this->requirePermission('file', 'read');
            $this->ok(['items' => [], 'total' => 0, 'page' => (int) $this->query('page', 1), 'pageSize' => (int) $this->query('pageSize', 24)]);
        }
        $this->requirePermission($this->storage->permissionModule($entityType), 'read');
        $this->requirePermission('file', 'read');
        $filters = [
            'page' => $this->query('page', ''),
            'pageSize' => $this->query('pageSize', $this->query('page_size', '')),
            'search' => $this->query('search', ''),
            'category' => $this->query('category', $this->query('profileSection', '')),
            'fileType' => $this->query('fileType', $this->query('file_type', '')),
        ];
        $hasPagedQuery = trim(implode('', array_map('strval', $filters))) !== '';
        if ($hasPagedQuery) {
            $this->ok($this->files->searchByEntity($entityType, (int) $entityId, $filters));
        }
        $this->ok(array_map(fn(array $row): array => $this->files->normalizeRow($row), $this->files->byEntity($entityType, (int) $entityId)));
    }

    public function show(string $id): void
    {
        $file = $this->files->find((int) $id);
        if (!$file) $this->fail('Khong tim thay file', 404);
        $entityType = $this->storage->normalizeEntityType((string) ($file['entity_type'] ?? $file['module'] ?? ''));
        $this->requirePermission($this->storage->permissionModule($entityType), 'read');
        $this->requirePermission('file', 'read');
        $this->ok($this->files->normalizeRow($file));
    }

    public function update(string $id): void
    {
        try {
            $file = $this->files->find((int) $id);
            if (!$file) $this->fail('Khong tim thay file', 404);
            $entityType = $this->storage->normalizeEntityType((string) ($file['entity_type'] ?? $file['module'] ?? ''));
            $this->requirePermission($this->storage->permissionModule($entityType), 'read');
            $user = $this->requirePermission('file', 'update');
            $input = $this->input();
            $payload = [];
            if (array_key_exists('file_name', $input) || array_key_exists('fileName', $input)) {
                $payload['file_name'] = $input['file_name'] ?? $input['fileName'];
            }
            if (array_key_exists('original_name', $input) || array_key_exists('originalName', $input)) {
                $payload['original_name'] = $input['original_name'] ?? $input['originalName'];
            }
            if (array_key_exists('description', $input)) {
                $payload['description'] = $input['description'];
            }
            if (array_key_exists('profile_section', $input) || array_key_exists('profileSection', $input) || array_key_exists('category', $input)) {
                $section = (string) ($input['profile_section'] ?? $input['profileSection'] ?? $input['category'] ?? '');
                $payload['category'] = $section;
                $payload['profile_section'] = $section;
            }
            if (array_key_exists('file_type', $input) || array_key_exists('fileType', $input)) {
                $payload['file_type'] = $input['file_type'] ?? $input['fileType'];
            }
            $updated = $this->files->updateMetadata((int) $id, $payload, (int) $user['id']);
            $this->audit($user, $entityType, 'update_file', 'Cap nhat thong tin file dinh kem', $file['entity_id'] ?? null, ['file' => (int) $id]);
            $this->ok($updated ? $this->files->normalizeRow($updated) : null);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 400);
        }
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
        if (!$file) $this->fail('Khong tim thay file', 404);
        $entityType = $this->storage->normalizeEntityType((string) ($file['entity_type'] ?? $file['module'] ?? ''));
        $user = $this->requireFileMutationPermission($entityType);
        $this->files->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, $entityType, 'delete_file', 'Xoa file dinh kem', $file['entity_id'] ?? null, ['file' => (int) $id, 'name' => $file['original_name'] ?? $file['file_name'] ?? '']);
        $this->ok(['id' => (int) $id]);
    }

    private function uploadOne(array $file, string $entityType, int $entityId, string $fileType, string $categoryInput, string $description, int $userId): array
    {
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
        ], $userId);
        return $this->files->normalizeRow($row);
    }

    private function normalizeUploadedFiles(array $file): array
    {
        if (!is_array($file['name'] ?? null)) return [$file];
        $rows = [];
        foreach ($file['name'] as $index => $name) {
            $rows[] = [
                'name' => $name,
                'type' => $file['type'][$index] ?? null,
                'tmp_name' => $file['tmp_name'][$index] ?? null,
                'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'][$index] ?? 0,
            ];
        }
        return $rows;
    }

    private function requireFileMutationPermission(string $entityType): array
    {
        $module = $this->storage->permissionModule($entityType);
        $user = $this->user();
        $this->verifyCsrfToken();
        if (!$this->users()->can($user, $module, 'read') || !$this->users()->can($user, 'file', 'delete')) {
            $this->fail('Khong co quyen xoa file', 403);
        }
        return $user;
    }

    private function streamFile(string $id, bool $download): void
    {
        $file = $this->files->find((int) $id);
        if (!$file) $this->fail('Khong tim thay file', 404);
        $entityType = $this->storage->normalizeEntityType((string) ($file['entity_type'] ?? $file['module'] ?? ''));
        $this->requirePermission($this->storage->permissionModule($entityType), 'read');
        $diagnostics = $this->storage->filePathDiagnostics((string) ($file['file_path'] ?? ''));
        $path = $diagnostics['path'] ?? null;
        if ($path === null || !is_file($path)) {
            error_log('[FilePreviewMissing] id=' . (int) $id
                . ' entity_type=' . ($file['entity_type'] ?? $file['module'] ?? '')
                . ' entity_id=' . ($file['entity_id'] ?? '')
                . ' file_path=' . ($file['file_path'] ?? '')
                . ' stored_name=' . ($file['stored_name'] ?? '')
                . ' original_name=' . ($file['original_name'] ?? $file['file_name'] ?? '')
                . ' normalized=' . ($diagnostics['normalized'] ?? '')
                . ' checked=' . json_encode($diagnostics['checked'] ?? [], JSON_UNESCAPED_SLASHES));
            $this->fail('File is no longer available on the server', 404);
        }
        $mime = (string) ($file['mime_type'] ?: 'application/octet-stream');
        if (!$download && !$this->storage->canPreview($mime)) $download = true;
        $this->requirePermission('file', $download ? 'download' : 'read');
        header('X-Content-Type-Options: nosniff');
        if ($mime === 'image/svg+xml') {
            header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox");
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode((string) ($file['original_name'] ?? $file['file_name'] ?? 'attachment')) . '"');
        readfile($path);
        exit;
    }
}
