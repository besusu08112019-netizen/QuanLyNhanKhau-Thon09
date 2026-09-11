<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Citizen;
use RuntimeException;

final class RelationshipReviewController extends BaseController
{
    private Citizen $citizens;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->citizens = new Citizen();
    }

    public function index(): void
    {
        $this->requirePermission('citizen', 'read');
        $this->ok($this->citizens->relationshipReview($this->query()));
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('citizen', 'update');
        $citizenId = (int) $id;
        $before = $this->citizens->relationshipReviewCitizen($citizenId);
        if (!$before) $this->fail('Không tìm thấy nhân khẩu', 404);
        $relationship = trim((string) ($this->input('relationship') ?? ''));
        if ($relationship === '') $this->fail('Vui lòng chọn quan hệ cần xác nhận', 422);

        try {
            $after = $this->citizens->confirmRelationship($citizenId, $relationship, (int) $user['id']);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            if ($code === 'HEAD_CHANGE_REQUIRES_SEPARATE_WORKFLOW') {
                $this->fail('HEAD_CHANGE_REQUIRES_SEPARATE_WORKFLOW', 409);
            }
            if ($code === 'INVALID_RELATIONSHIP_OPTION') {
                $this->fail('Quan hệ không nằm trong danh mục chuẩn', 422);
            }
            if ($code === 'RELATIONSHIP_ALREADY_RESOLVED') {
                $this->fail('Quan hệ đã được xác nhận, không thuộc workflow V1', 409);
            }
            if ($code === 'CITIZEN_NOT_CURRENT') {
                $this->fail('Nhân khẩu không còn hiện hành', 409);
            }
            throw $e;
        }

        $this->audit($user, 'citizen', 'relationship_review', 'Xác nhận quan hệ trong hộ', $citizenId, [
            'citizen_id' => $citizenId,
            'household_id' => $after['household_id'] ?? $before['household_id'] ?? null,
            'old_relationship' => $before['relationship'] ?? null,
            'new_relationship' => $after['relationship'] ?? null,
        ]);
        $this->ok($after);
    }
}