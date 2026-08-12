<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Movement;

final class MovementController extends BaseController
{
    private Movement $movements;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->movements = new Movement();
    }

    public function index(): void
    {
        $this->requirePermission('movement', 'read');
        $this->ok($this->movements->paginate($this->query()));
    }

    public function show(string $id): void
    {
        $this->requirePermission('movement', 'read');
        $row = $this->movements->find((int) $id);
        $row ? $this->ok($row) : $this->fail('KhÃ´ng tÃ¬m tháº¥y biáº¿n Ä‘á»™ng', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('movement', 'create');
        $row = $this->movements->create($this->input(), (int) $user['id']);
        $this->audit($user, 'movement', 'create', 'Táº¡o biáº¿n Ä‘á»™ng nhÃ¢n kháº©u', $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $this->requirePermission('movement', 'update');
        $this->fail('Biáº¿n Ä‘á»™ng dÃ¢n cÆ° lÃ  nháº­t kÃ½ lá»‹ch sá»­, khÃ´ng Ä‘Æ°á»£c sá»­a trá»±c tiáº¿p.', 409);
    }

    public function destroy(string $id): void
    {
        $this->requirePermission('movement', 'delete');
        $this->fail('Biáº¿n Ä‘á»™ng dÃ¢n cÆ° lÃ  nháº­t kÃ½ lá»‹ch sá»­, khÃ´ng Ä‘Æ°á»£c xÃ³a.', 409);
    }

    public function types(): void
    {
        $this->requirePermission('movement', 'read');
        $this->ok($this->movements->types());
    }
}
