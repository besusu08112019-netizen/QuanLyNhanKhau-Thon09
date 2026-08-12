<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\HouseholdContribution;
use Throwable;

final class ContributionController extends BaseController
{
    private HouseholdContribution $contributions;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->contributions = new HouseholdContribution();
    }

    public function index(): void
    {
        $this->requirePermission('contributions', 'read');
        $this->ok($this->contributions->campaigns([
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'category_id' => $this->query('category_id', $this->query('categoryId', '')),
            'year' => $this->query('year', ''),
            'status' => $this->query('status', ''),
        ]));
    }

    public function catalogs(): void
    {
        $this->requirePermission('contributions', 'read');
        $this->ok($this->contributions->catalogs());
    }

    public function categories(): void
    {
        $this->requirePermission('contributions', 'read');
        $this->ok($this->contributions->categories([
            'search' => $this->query('search', $this->query('q', '')),
            'status' => $this->query('status', ''),
        ]));
    }

    public function showCategory(string $id): void
    {
        $this->requirePermission('contributions', 'read');
        $row = $this->contributions->findCategory((int) $id);
        if (!$row) $this->fail('KhÃ´ng tÃ¬m tháº¥y khoáº£n thu', 404);
        $this->ok($row);
    }

    public function storeCategory(): void
    {
        $user = $this->requirePermission('contributions', 'create');
        try {
            $row = $this->contributions->upsertCategory((array) $this->input(), (int) $user['id']);
            $this->audit($user, 'contributions', 'create_category', 'ThÃªm khoáº£n thu', $row['id'], ['before' => null, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function updateCategory(string $id): void
    {
        $user = $this->requirePermission('contributions', 'update');
        try {
            $before = $this->contributions->findCategory((int) $id);
            if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y khoáº£n thu', 404);
            $row = $this->contributions->upsertCategory((array) $this->input(), (int) $user['id'], (int) $id);
            $this->audit($user, 'contributions', 'update_category', 'Chá»‰nh sá»­a khoáº£n thu', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function destroyCategory(string $id): void
    {
        $user = $this->requirePermission('contributions', 'delete');
        try {
            $before = $this->contributions->findCategory((int) $id);
            if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y khoáº£n thu', 404);
            $this->contributions->deleteCategory((int) $id, (int) $user['id']);
            $this->audit($user, 'contributions', 'delete_category', 'XÃ³a khoáº£n thu', $id, ['before' => $before, 'after' => null]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function householdSearch(): void
    {
        $this->requirePermission('contributions', 'read');
        $this->ok(['items' => $this->contributions->searchHouseholds((string) $this->query('q', $this->query('search', '')))]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('contributions', 'read');
        $row = $this->contributions->findCampaign((int) $id);
        if (!$row) $this->fail('KhÃ´ng tÃ¬m tháº¥y Ä‘á»£t thu', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('contributions', 'create');
        try {
            $row = $this->contributions->upsertCampaign((array) $this->input(), (int) $user['id']);
            $this->audit($user, 'contributions', 'create', 'ThÃªm Ä‘á»£t thu', $row['id'], ['before' => null, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('contributions', 'update');
        try {
            $before = $this->contributions->findCampaign((int) $id);
            if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y Ä‘á»£t thu', 404);
            $row = $this->contributions->upsertCampaign((array) $this->input(), (int) $user['id'], (int) $id);
            $this->audit($user, 'contributions', 'update', 'Chá»‰nh sá»­a Ä‘á»£t thu', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('contributions', 'delete');
        try {
            $before = $this->contributions->findCampaign((int) $id);
            if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y Ä‘á»£t thu', 404);
            $this->contributions->deleteCampaign((int) $id, (int) $user['id']);
            $this->audit($user, 'contributions', 'delete', 'XÃ³a Ä‘á»£t thu', $id, ['before' => $before, 'after' => null]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function tracking(string $campaignId): void
    {
        $this->requirePermission('contributions', 'read');
        try {
            $this->ok($this->contributions->tracking((int) $campaignId, [
                'page' => $this->query('page', 1),
                'pageSize' => $this->query('pageSize', 20),
                'search' => $this->query('search', $this->query('q', '')),
                'payment_status' => $this->query('payment_status', $this->query('paymentStatus', '')),
                'household_id' => $this->query('household_id', $this->query('householdId', '')),
                'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            ]));
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function updateTracking(string $campaignId, string $householdId): void
    {
        $user = $this->requirePermission('contributions', 'update');
        try {
            $row = $this->contributions->upsertTracking((int) $campaignId, (int) $householdId, (array) $this->input(), (int) $user['id']);
            $this->audit($user, 'contributions', 'update_payment', 'Cáº­p nháº­t Ä‘Ã³ng gÃ³p há»™', $row['id'] ?? null, ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function history(string $campaignId, string $householdId): void
    {
        $this->requirePermission('contributions', 'read');
        $this->ok(['items' => $this->contributions->history((int) $campaignId, (int) $householdId)]);
    }

    public function dashboard(): void
    {
        $this->requirePermission('contributions', 'read');
        $filters = ['year' => $this->query('year', ''), 'status' => $this->query('status', '')];
        $this->ok(['metrics' => $this->contributions->dashboard($filters), 'charts' => $this->contributions->charts($filters)]);
    }
}
