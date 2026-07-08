<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\HouseholdBusiness;
use App\Services\FileStorageService;

final class HouseholdBusinessController extends BaseController
{
    private HouseholdBusiness $businesses;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->businesses = new HouseholdBusiness();
    }

    public function index(): void
    {
        $this->requirePermission('household_business', 'read');
        $this->ok($this->businesses->paginate([
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'business_type' => $this->query('business_type', $this->query('businessType', '')),
            'economic_type' => $this->query('economic_type', $this->query('economicType', '')),
            'business_scale' => $this->query('business_scale', $this->query('businessScale', '')),
            'product' => $this->query('product', ''),
            'ocop' => $this->query('ocop', ''),
            'food_safety' => $this->query('food_safety', ''),
            'social_insurance' => $this->query('social_insurance', ''),
            'sector' => $this->query('sector', ''),
            'status' => $this->query('status', ''),
            'license' => $this->query('license', ''),
            'tax' => $this->query('tax', ''),
            'located' => $this->query('located', ''),
            'sort' => $this->query('sort', 'household_code'),
            'direction' => $this->query('direction', 'ASC'),
        ]));
    }

    public function householdSearch(): void
    {
        $this->requirePermission('household_business', 'read');
        $q = (string) $this->query('q', $this->query('search', ''));
        $this->ok(['items' => $this->businesses->searchHouseholds($q)]);
    }

    public function catalogs(): void
    {
        $this->requirePermission('household_business', 'read');
        $this->ok($this->businesses->catalogs());
    }

    public function show(string $id): void
    {
        $this->requirePermission('household_business', 'read');
        $row = $this->businesses->find((int) $id);
        if (!$row) $this->fail('Không tìm thấy thông tin hộ sản xuất/kinh doanh', 404);
        $row['members'] = $this->businesses->members((int) $row['household_id']);
        $row['files'] = $this->businesses->files((int) $row['id']);
        $this->ok($row);
    }

    public function byHousehold(string $householdId): void
    {
        $this->requirePermission('household_business', 'read');
        $row = $this->businesses->findByHousehold((int) $householdId);
        $row ? $this->ok($row) : $this->fail('Không tìm thấy hộ gia đình', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('household_business', 'create');
        $input = (array) $this->input();
        $this->requireInputFields($input, ['household_id' => 'Hộ gia đình', 'business_type' => 'Loại hình']);
        $row = $this->businesses->upsert($input, (int) $user['id']);
        $action = $this->auditAction($row['business_type']);
        $this->audit($user, 'household_business', 'create', $action, $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('household_business', 'update');
        $before = $this->businesses->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy thông tin hộ sản xuất/kinh doanh', 404);
        $row = $this->businesses->upsert((array) $this->input(), (int) $user['id'], (int) $id);
        $action = $before['status'] !== $row['status'] ? 'Thay đổi trạng thái hộ sản xuất/kinh doanh' : 'Chỉnh sửa hộ sản xuất/kinh doanh';
        $this->audit($user, 'household_business', 'update', $action, $id, ['before' => $before, 'after' => $row]);
        foreach (['is_ocop' => 'Thay đổi OCOP', 'food_safety_certified' => 'Thay đổi ATTP', 'social_insurance' => 'Thay đổi BHXH'] as $field => $message) {
            if (($before[$field] ?? null) !== ($row[$field] ?? null)) $this->audit($user, 'household_business', 'update', $message, $id, ['before' => $before[$field] ?? null, 'after' => $row[$field] ?? null]);
        }
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('household_business', 'delete');
        $before = $this->businesses->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy thông tin hộ sản xuất/kinh doanh', 404);
        $this->businesses->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, 'household_business', 'delete', 'Xóa thông tin hộ sản xuất/kinh doanh', $id, ['before' => $before, 'after' => null]);
        $this->ok(['id' => (int) $id]);
    }


    public function files(string $id): void
    {
        $this->requirePermission('household_business', 'read');
        if (!$this->businesses->find((int) $id)) $this->fail('Không tìm thấy hồ sơ sản xuất/kinh doanh', 404);
        $this->ok(['items' => $this->businesses->files((int) $id, (string) $this->query('kind', ''))]);
    }

    public function uploadFile(string $id): void
    {
        $user = $this->requirePermission('household_business', 'update');
        $businessId = (int) $id;
        if (!$this->businesses->find($businessId)) $this->fail('Không tìm thấy hồ sơ sản xuất/kinh doanh', 404);
        $kind = strtoupper((string) ($_POST['file_kind'] ?? $_POST['kind'] ?? 'DOCUMENT')) === 'IMAGE' ? 'IMAGE' : 'DOCUMENT';
        $category = (string) ($_POST['category'] ?? 'Khác');
        $uploads = $this->normalizeUploads($_FILES['file'] ?? $_FILES['files'] ?? null);
        if (!$uploads) $this->fail('Vui lòng chọn file', 422);
        $storage = new FileStorageService();
        $items = [];
        foreach ($uploads as $file) {
            $fileType = $kind === 'IMAGE' ? 'IMAGE' : 'DOCUMENT';
            $info = $storage->inspectUpload($file, $fileType, 'household_business');
            $this->validateBusinessFile($kind, $info['mime']);
            $stored = $storage->storeUpload($file, 'household_business', $kind === 'IMAGE' ? 'images' : 'documents', $info['extension']);
            $item = $this->businesses->addFile($businessId, $kind, $category, $stored, $file, $info['mime'], (int) $user['id']);
            $items[] = $item;
            $this->audit($user, 'household_business', $kind === 'IMAGE' ? 'upload_image' : 'upload_document', $kind === 'IMAGE' ? 'Upload ảnh cơ sở' : 'Upload tài liệu cơ sở', $businessId, ['file' => $item]);
        }
        $this->ok(['items' => $items]);
    }

    public function deleteFile(string $id, string $fileId): void
    {
        $user = $this->requirePermission('household_business', 'delete');
        $file = $this->businesses->file((int) $fileId);
        if (!$file || (int) $file['household_business_id'] !== (int) $id) $this->fail('Không tìm thấy file', 404);
        $before = $this->businesses->deleteFile((int) $fileId, (int) $user['id']);
        $this->audit($user, 'household_business', 'delete_file', 'Xóa tài liệu/ảnh cơ sở', $id, ['before' => $before]);
        $this->ok(['id' => (int) $fileId]);
    }

    public function previewFile(string $id, string $fileId): void { $this->streamFile($id, $fileId, false); }
    public function downloadFile(string $id, string $fileId): void { $this->streamFile($id, $fileId, true); }

    private function streamFile(string $id, string $fileId, bool $download): void
    {
        $this->requirePermission('household_business', 'read');
        $file = $this->businesses->file((int) $fileId);
        if (!$file || (int) $file['household_business_id'] !== (int) $id) $this->fail('Không tìm thấy file', 404);
        $storage = new FileStorageService();
        $path = $storage->safeFilePath($file['file_path']);
        if (!$path || !is_file($path)) $this->fail('File không còn tồn tại', 404);
        if (!$download && !$storage->canPreview($file['mime_type'])) $download = true;
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . addslashes($file['original_name']) . '"');
        readfile($path);
        exit;
    }

    private function normalizeUploads(mixed $input): array
    {
        if (!is_array($input) || !isset($input['name'])) return [];
        if (!is_array($input['name'])) return [$input];
        $items = [];
        foreach ($input['name'] as $i => $name) $items[] = ['name' => $name, 'type' => $input['type'][$i] ?? '', 'tmp_name' => $input['tmp_name'][$i] ?? '', 'error' => $input['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $input['size'][$i] ?? 0];
        return $items;
    }

    private function validateBusinessFile(string $kind, string $mime): void
    {
        $image = ['image/jpeg','image/png'];
        $doc = ['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','image/jpeg','image/png'];
        if ($kind === 'IMAGE' && !in_array($mime, $image, true)) throw new \RuntimeException('Ảnh cơ sở chỉ hỗ trợ JPG hoặc PNG');
        if ($kind === 'DOCUMENT' && !in_array($mime, $doc, true)) throw new \RuntimeException('Tài liệu chỉ hỗ trợ PDF, DOCX, XLSX, JPG hoặc PNG');
    }

    public function dashboard(): void
    {
        $this->requirePermission('household_business', 'read');
        $this->ok(['metrics' => $this->businesses->dashboard(), 'charts' => $this->businesses->charts()]);
    }

    private function auditAction(string $type): string
    {
        return match ($type) {
            'PRODUCTION' => 'Thêm hộ sản xuất',
            'BUSINESS' => 'Thêm hộ kinh doanh',
            'BOTH' => 'Thêm hộ sản xuất và kinh doanh',
            default => 'Thêm thông tin hộ dân',
        };
    }
}
