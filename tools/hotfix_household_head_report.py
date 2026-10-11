import hashlib
import io
import os
import subprocess
from ftplib import FTP_TLS
from pathlib import Path

REMOTE = "app/Models/Report.php"
ROOT = os.environ.get("FTP_ROOT_DIR", "").strip() or "/public_html/"

def connect():
    ftp = FTP_TLS(os.environ["FTP_SERVER"], timeout=60)
    ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
    ftp.prot_p()
    ftp.cwd(ROOT)
    return ftp

def download(ftp):
    data = io.BytesIO()
    ftp.retrbinary("RETR " + REMOTE, data.write)
    return data.getvalue()

def patch(source):
    text = source.decode("utf-8")
    start = text.index("    public function householdReport(array $filters = []): array")
    end = text.index("\n    public function settledElsewhereHouseholdsReport", start)
    method = r'''    public function householdReport(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $currentHeadNameExpr = $this->currentHeadNameExpression('h');
        $meritoriousHouseholdExpr = $this->meritoriousHouseholdExists('h');
        $disabledHouseholdExpr = $this->disabledHouseholdExists('h');
        $policySubjectHouseholdExpr = $this->policySubjectHouseholdExists('h');
        $activePovertyTypeExpr = $this->activePovertyTypeExpr('h');
        $rows = $this->fetchAll("SELECT h.household_code, $currentHeadNameExpr AS current_head_citizen_name, h.address, h.phone, COALESCE(v.total_members,0) AS members, COALESCE(v.at_home_count,0) AS at_home, COALESCE(v.away_count,0) AS away, $meritoriousHouseholdExpr AS meritorious_policy, $disabledHouseholdExpr AS disabled_policy, $policySubjectHouseholdExpr AS policy_subject_household, $activePovertyTypeExpr AS active_poverty_type, h.poor_household, h.near_poor_household, h.note FROM households h LEFT JOIN v_household_member_counts v ON v.household_id=h.id $where ORDER BY h.household_code", $params);
        return $this->table('Danh sách hộ dân', ['Mã hộ','Chủ hộ','Địa chỉ','Số điện thoại','Nhân khẩu','Ở nhà','Đi vắng','Diện hộ'], array_map(fn($r) => [$r['household_code'], $this->reportHeadName($r['current_head_citizen_name'] ?? null), $r['address'], $r['phone'], (int) $r['members'], (int) $r['at_home'], (int) $r['away'], $this->householdCategories($r)], $rows), $filters);
    }

'''
    result = text[:start] + method + text[end + 1:]
    anchor = "    private function statistics(): PopulationStatistics"
    if anchor not in result:
        raise RuntimeError("statistics() insertion anchor not found")
    if "private function currentHeadNameExpression(" in result:
        raise RuntimeError("HOST already contains household head helper")
    helpers = r'''    private function currentHeadNameExpression(string $householdAlias): string
    {
        $condition = $this->activeCitizenCondition('report_head');
        $head = addslashes(\App\Policies\HouseholdRelationPolicy::HEAD);

        return "(SELECT CASE WHEN COUNT(*) = 1 THEN MAX(NULLIF(TRIM(report_head.full_name), '')) ELSE NULL END FROM citizens report_head WHERE report_head.household_id = $householdAlias.id AND report_head.relationship = '$head' AND $condition)";
    }

    private function reportHeadName(mixed $value): string
    {
        $name = trim((string) $value);
        return $name !== '' ? $name : 'Chưa xác định chủ hộ';
    }

'''
    return result.replace(anchor, helpers + anchor, 1).encode("utf-8")

Path("hotfix/backup").mkdir(parents=True, exist_ok=True)
Path("hotfix/patched").mkdir(parents=True, exist_ok=True)
with connect() as session:
    current = download(session)
Path("hotfix/backup/Report.php").write_bytes(current)
patched = patch(current)
Path("hotfix/patched/Report.php").write_bytes(patched)
subprocess.run(["php", "-l", "hotfix/patched/Report.php"], check=True)
with connect() as session:
    session.cwd("app/Models")
    session.storbinary("STOR Report.php", io.BytesIO(patched))
    session.cwd(ROOT)
    readback = download(session)
expected = hashlib.sha256(patched).hexdigest()
actual = hashlib.sha256(readback).hexdigest()
if actual != expected:
    raise RuntimeError(f"HOST readback mismatch: expected {expected}, got {actual}")
print("HOST Report.php verified sha256=" + actual)
