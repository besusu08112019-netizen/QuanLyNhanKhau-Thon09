<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\AssociationMembership;
use Throwable;

final class AssociationController extends BaseController
{
    private AssociationMembership $members;

    public function __construct($request) { parent::__construct($request); $this->members = new AssociationMembership(); }
    public function index(): void { $this->requirePermission('associations', 'read'); $this->ok($this->members->paginate($this->filters())); }
    public function catalogs(): void { $this->requirePermission('associations', 'read'); $this->ok($this->members->catalogs()); }
    public function citizenSearch(): void { $this->requirePermission('associations', 'read'); $org = (int) $this->query('organization_id', 0); $this->ok(['items' => $this->members->searchCitizens((string) $this->query('q', ''), $org ?: null)]); }
    public function show(string $id): void { $this->requirePermission('associations', 'read'); $row=$this->members->find((int)$id); if(!$row)$this->fail('Không tìm thấy thành viên',404); $this->ok($row); }
    public function store(): void { $user=$this->requirePermission('associations','create'); $this->save(null,$user); }
    public function update(string $id): void { $user=$this->requirePermission('associations','update'); $this->save((int)$id,$user); }
    public function destroy(string $id): void { $user=$this->requirePermission('associations','delete'); $this->members->leave((int)$id,(int)$user['id']); $this->audit($user,'associations','delete','Chuyển thành viên sang trạng thái đã rời tổ chức',(int)$id); $this->ok(['id'=>(int)$id,'status'=>'LEFT']); }
    public function dashboard(): void { $this->requirePermission('associations','read'); $this->ok(['metrics'=>$this->members->dashboard($this->filters())]); }
    public function report(): void { $this->requirePermission('associations','export'); $this->ok($this->members->report($this->filters())); }
    private function save(?int $id,array $user): void { try { $row=$this->members->upsert((array)$this->input(),(int)$user['id'],$id); $this->audit($user,'associations',$id?'update':'create',$id?'Cập nhật thành viên đoàn thể':'Thêm thành viên đoàn thể',$row['id']??$id,['after'=>$row]); $this->ok($row); } catch (Throwable $e) { $this->fail($this->safeExceptionMessage('Không thể lưu thành viên',$e),422); } }
    private function filters(): array { return ['page'=>$this->query('page',1),'pageSize'=>$this->query('pageSize',20),'search'=>$this->query('search',$this->query('q','')),'organization_id'=>$this->query('organization_id',''),'status'=>$this->query('status',''),'area_code'=>$this->query('area_code',''),'from_age'=>$this->query('from_age',$this->query('age_from','')),'to_age'=>$this->query('to_age',$this->query('age_to','')),'sort'=>$this->query('sort','full_name'),'direction'=>$this->query('direction','ASC')]; }
}
