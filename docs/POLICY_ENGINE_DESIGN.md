# Policy Engine Design

## 1. Scope

Sprint nay chi la giai doan khao sat va thiet ke. Khong refactor, khong thay doi logic production, khong thay doi database, khong thay doi hanh vi cua cac tenant dang chay.

Muc tieu:

- Khao sat toan bo business rule dang nam trong source.
- Phan loai rule theo nhom policy.
- Phat hien rule trung lap hoac hard-code.
- De xuat kien truc Policy Engine.
- Lap roadmap chuyen doi tung buoc, co rollback.

## 2. Current Business Rule Inventory

### 2.1 Age Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Tinh tuoi theo ngay sinh | `app/Config/CitizenPolicyDefaults.php`, `app/Models/Citizen.php`, `app/Models/PolicyAlert.php`, `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Dung `DateTime::diff()` trong PHP va `TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())` trong SQL. | Dang ton tai ca PHP rule va SQL rule. Can gom ve mot policy source. |
| Tre em | `app/Models/PopulationStatistics.php`, `app/Models/Report.php`, `app/Models/Dashboard.php` | Thong ke tre em theo nguong `< 16` hoac age band `0-5`, `6-14`, `15-17`. | Nguong tuoi dang hard-code trong SQL. |
| Nguoi cao tuoi thong ke | `app/Models/PopulationStatistics.php`, `app/Models/Report.php`, `app/Models/Dashboard.php` | Thong ke nguoi cao tuoi tu `>= 60`. | Khac voi nguong chinh sach 70/75. Can tach "statistical age" va "policy eligibility age". |
| Do tuoi lao dong | `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Thong ke lao dong theo nguong `16-59` hoac age band `18-59`. | Rule hien nam trong SQL, can chuan hoa. |
| BHYT tuoi 70 | `app/Config/CitizenPolicyDefaults.php`, `config/policy_alerts.php`, `app/Models/PolicyAlert.php` | Tuoi 70 duoc dung de goi y/bao canh bao BHYT. | Da co constant, nhung SQL va canh bao van phan tan. |
| Tro cap xa hoi tuoi 75 | `app/Config/CitizenPolicyDefaults.php`, `config/policy_alerts.php`, `app/Models/PolicyAlert.php` | Tuoi 75 duoc dung de mac dinh `social_assistance` va canh bao tro cap. | Can quy ve Social Support Policy. |
| Sap den nguong tuoi | `config/policy_alerts.php`, `app/Models/PolicyAlert.php` | Canh bao truoc `lookahead_days = 90` ngay cho tuoi 70/75. | Dang cau hinh PHP array, chua co policy repository rieng. |

### 2.2 Insurance Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Mac dinh BHYT theo nghe nghiep hoc sinh/nguoi cao tuoi | `app/Services/HealthInsuranceDefaultService.php`, `app/Models/Citizen.php` | Neu la hoc sinh hoac nguoi cao tuoi 70+ thi mac dinh `has_health_insurance = 1`. | Dang gan voi occupation text va age threshold. |
| Nhom nghe nghiep duoc xem la co BHYT | `app/Services/HealthInsuranceDefaultService.php` | Normalize occupation, match `hoc sinh`, `nguoi cao tuoi 70`, `nguoi cao tuoi 70+`, `elderly 70`. | Hard-code text aliases. |
| BHYT con hieu luc | `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Co BHYT khi `has_health_insurance = 1` va ngay het han null hoac >= ngay hien tai. | Rule lap lai qua thong ke, dashboard, report. |
| BHYT sap het han | `app/Models/Report.php` | Report mode `expiring` dung khoang 30 ngay. | Hard-code 30 ngay trong report. |
| BHYT het han | `app/Models/Report.php` | Report mode `expired` khi end date < current date. | Can dua vao Insurance Policy. |

### 2.3 Social Support Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Mac dinh tro cap xa hoi theo tuoi | `app/Config/CitizenPolicyDefaults.php`, `app/Models/Citizen.php` | Neu tuoi >= 75 thi mac dinh `social_assistance = 1`. | Rule dang ap dung khi tao/cap nhat nhan khau. |
| Canh bao du tuoi tro cap | `config/policy_alerts.php`, `app/Models/PolicyAlert.php` | Canh bao `age_75`, exclude neu da co `social_assistance`. | Logic exclude nam trong alert model. |
| Ho chinh sach | `app/Models/Household.php`, `app/Models\PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Dua tren note co chu "chinh sach" hoac cac flag cong dan co cong/khuyet tat. | Dang vua dung field flag, vua dung text search trong note. |
| Nguoi co cong | `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php`, `app/Models/Household.php` | Dung tap cot `martyr_relative`, `wounded_soldier`, `sick_soldier`, ... de xac dinh. | Danh sach cot duoc lap lai trong nhieu model. |

### 2.4 Education Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Nam hoc | `app/Services/StudentStatusService.php` | Nam hoc bat dau tu thang 8. | Hard-code `ACADEMIC_YEAR_START_MONTH = 8`. |
| Hoc sinh theo tuoi hoc vu | `app/Services/StudentStatusService.php` | Hoc sinh neu academic age <= 17. | Hard-code `STUDENT_MAX_ACADEMIC_AGE = 17`. |
| Default truong hoc sinh | `app/Services/StudentStatusService.php`, `app/Models/Citizen.php` | Khi la hoc sinh: set education/occupation, `pupil = 1`, `student = 0`, `not_attending_school = 0`, `employed = 0`. | Rule default da tap trung trong service nhung duoc goi tu Citizen. |
| SQL hoc sinh | `app/Services/StudentStatusService.php`, `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | `studentSql()` duoc dung de thong ke va filter hoc sinh. | Day la mot mau tot co the dua vao PolicyQuery. |

### 2.5 Employment Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Lao dong theo tuoi | `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Working age chu yeu theo `16-59`; chart co nhom `18-59`. | Can chuan hoa dinh nghia. |
| Trang thai lao dong tu flag | `app/Models/Dashboard.php`, `app/Models/Report.php`, `app/Models/Citizen.php` | Dung `employed`, `unemployed`, `freelance_labor`, `out_province_labor`, `foreign_labor`, `retired`. | Rule phan nhom dang nam trong Dashboard/Report. |
| Hoc sinh khong mac dinh la lao dong | `app/Services/StudentStatusService.php`, `app/Models/Citizen.php` | Neu la hoc sinh thi `employed = 0`. | Can nam trong Education/Employment boundary rule. |

### 2.6 Household Relation Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Moi ho chi co mot chu ho | `app/Models/Citizen.php` | `ensureSingleHead()` chan tao/cap nhat neu ho da co `relationship = Chu ho`. | Rule quan trong, can dua vao Household Relation Policy. |
| Mac dinh quan he | `app/Models/Citizen.php`, `app/Controllers/ImportController.php` | Khi khong co du lieu thi dung `Khac` hoac `Chua xac dinh`. | Dang khong dong nhat giua manual form va import. |
| Suy luan quan he voi chu ho | `app/Services/HouseholdRelationshipInferenceService.php`, `app/Controllers/ImportController.php` | Dung ten cha/me va ten chu ho de suy luan chu ho, con, chau, chat, con dau, con re. | Service doc lap, co the tai su dung trong Policy Engine. |
| Sap xep chu ho len truoc | `app/Models/Citizen.php` | List order uu tien `relationship = Chu ho`. | Day la presentation/listing rule, khong phai core policy. |

### 2.7 Statistics Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Cong dan dang tinh thong ke | `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Loai `status = DELETED`, `life_status = DECEASED`, `residency_status = TRANSFERRED_OUT`. | Core rule can tap trung. |
| Ho dang tinh thong ke | `app/Models/PopulationStatistics.php`, `app/Models/Household.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Loai `DELETED`, `ENDED`, `MERGED`, `TRANSFERRED_OUT`, `MOVED_OUT`, `INACTIVE` neu co cot status. | Dang da co PopulationStatistics lam nguon chinh, nhung model khac van goi/lap lai. |
| Tam tru/tam vang | `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Tam tru theo `residency_status = TEMPORARY`; tam vang theo `presence_status = AWAY`. | Can nam trong Residency/Statistics policy. |
| Ty le phan tram | `app/Models/PopulationStatistics.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Dung total max(1, total) hoac custom percent helpers. | Can dong nhat cach chia khi total = 0. |
| Ho ngheo/can ngheo/chinh sach/khuyet tat | `app/Models/PopulationStatistics.php`, `app/Models/Household.php`, `app/Models/Dashboard.php`, `app/Models/Report.php` | Dua tren flag household, citizen flags, va note text. | Trung lap cao. |

### 2.8 Warning Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Policy alerts | `config/policy_alerts.php`, `app/Models/PolicyAlert.php`, `app/Controllers/PolicyAlertController.php` | Cac alert tuoi 70/75, sap den 70/75, pending/reviewed/processed. | Day la ung vien dau tien de migrate sang Policy Engine. |
| Review status | `app/Models/PolicyAlert.php` | `pending`, `reviewed`, `processed`. | Hard-code trong model. |
| Alert review storage | `app/Models/PolicyAlert.php` | Model tu tao table `policy_alert_reviews` neu chua co. | Runtime schema creation la technical debt; khong sua trong sprint nay. |
| Alert filter | `app/Models/PolicyAlert.php` | Chi tinh cong dan con song/dang cu tru, co search, household, alert type. | Can tach thanh reusable PolicyQuery. |

### 2.9 Import Policy

| Rule | Current location | Current behavior | Notes |
| --- | --- | --- | --- |
| Dinh dang file import | `app/Controllers/ImportController.php` | Chi chap nhan CSV/XLSX, size <= 5MB, toi da 5000 rows. | Hard-code import constraints. |
| Header bat buoc | `app/Controllers/ImportController.php` | Household: `householdCode`, `address`; Person: `householdCode`, `fullName`, `dateOfBirth`. | Rule validate import can tach khoi controller. |
| Duplicate trong file | `app/Controllers/ImportController.php` | Check trung ma ho, ma nhan khau, CCCD trong file. | Import-specific policy. |
| Duplicate trong database | `app/Controllers/ImportController.php`, `app/Models/Household.php`, `app/Models/Citizen.php` | Household ton tai thi skip/update theo mode; citizen trung CCCD thi update. | Logic phan tan giua controller va model. |
| Identity number | `app/Controllers/ImportController.php` | CCCD/CMND phai gom 9 hoac 12 chu so; 11 so duoc them `0` dau. | Hard-code validate/normalize. |
| Phone | `app/Controllers/ImportController.php` | Validate phone rieng trong import. | Can so sanh voi validate form neu co. |
| Import transaction | `app/Controllers/ImportController.php` | Loi trong process thi rollback ca file. | Rule tot, can giu. |
| Quan he sau import | `app/Controllers/ImportController.php`, `app/Services/HouseholdRelationshipInferenceService.php` | Neu thieu quan he thi suy luan sau khi import thanh cong. | Can dua vao Import Policy goi Household Relation Policy. |

## 3. Duplication And Hard-code Findings

### 3.1 Age thresholds are scattered

Current values:

- Child: `< 16`.
- Student academic age: `<= 17`.
- Working age: `16-59` and `18-59` depending on chart/report.
- Elderly statistics: `>= 60`.
- BHYT review: `70`.
- Social support review: `75`.
- Upcoming policy lookahead: `90` days.
- Health insurance expiring: `30` days.

Risk: thay doi mot nguong tuoi se phai sua nhieu file, de lech Dashboard/Report/Warning/Import.

### 3.2 Active citizen and active household filters are repeated

Main source hien tai la `PopulationStatistics`, nhung `Dashboard`, `Report`, `Household`, `PolicyAlert` van co SQL rieng hoac goi fragment khac nhau.

Risk: mot module co the tinh nham nguoi da chet, da chuyen di, ho da ket thuc.

### 3.3 Household category rules are duplicated

`Household`, `Dashboard`, `Report`, `PopulationStatistics` deu xu ly:

- Ho ngheo.
- Ho can ngheo.
- Ho chinh sach.
- Ho co cong.
- Ho co nguoi khuyet tat.
- Ho binh thuong.

Risk: cung mot ho co the hien category khac nhau giua danh sach, dashboard va bao cao.

### 3.4 Policy alert labels/status are hard-coded

`config/policy_alerts.php` va `PolicyAlert` dang chua gom vao policy definition chuan. Review status cung hard-code.

Risk: kho mo rong them canh bao moi, kho test dong nhat.

### 3.5 Text normalization is duplicated

Normalization xuat hien o `HealthInsuranceDefaultService`, `HouseholdRelationshipInferenceService`, `Household`, `Dashboard`, `Report`, `ImportController`.

Risk: cung mot chuoi tieng Viet co dau/khong dau co the match khac nhau tuy module.

### 3.6 Encoding debt exists in policy-related files

Mot so file hien co chua mojibake trong string tieng Viet. Sprint nay khong sua, nhung day la technical debt can tach rieng vi co the anh huong hien thi policy labels.

## 4. Proposed Policy Engine Architecture

### 4.1 Design principle

Policy Engine khong thay the ngay logic hien co. Truoc tien no chay o che do "shadow/parity" de so sanh output voi logic cu. Chi khi parity pass moi migrate tung nhom rule.

Policy Engine phai:

- Khong phu thuoc UI.
- Khong hard-code tenant.
- Lam viec voi TenantResolver hien co.
- Co the goi tu Controller, Service, Import, Dashboard, Report, CLI/Cron sau nay.
- Ho tro ca PHP evaluation va SQL predicate de dung cho list/report/statistics.

### 4.2 Components

| Component | Responsibility |
| --- | --- |
| `PolicyEngine` | Orchestrates policy evaluation by group and subject. |
| `PolicyContext` | Holds tenant, current date, module/use-case, actor, locale, options. |
| `PolicyRuleInterface` | Contract for one rule: `code()`, `group()`, `evaluate()`, optional `toSqlPredicate()`. |
| `PolicyResult` | Standard output: matched, value, severity, reason, next action, metadata. |
| `PolicyDefinitionRepository` | Loads thresholds/statuses from config or database in future. |
| `PolicyQueryBuilder` | Provides reusable SQL predicates for age, active citizen, active household, BHYT, warning. |
| `PolicyRegistry` | Registers policy groups and rules. |
| `PolicyEvaluatorService` | Application service used by current modules. |

### 4.3 Policy groups

| Group | Initial rules to own |
| --- | --- |
| Age Policy | Age calculation, child/elderly/working-age bands, upcoming age threshold. |
| Insurance Policy | BHYT eligibility default, effective coverage, missing/expired/expiring. |
| Social Support Policy | Social assistance age, policy household, meritorious/disabled household. |
| Education Policy | Academic year, pupil/student defaults, not attending school flags. |
| Employment Policy | Working age, labor status grouping, student/labor boundary. |
| Household Relation Policy | Single household head, relationship inference, unknown/default relation. |
| Statistics Policy | Active citizen/household predicates, percentage calculation, aggregation rules. |
| Warning Policy | Policy alerts, warning severity, review statuses, next actions. |
| Import Policy | Import limits, required headers, duplicate strategy, row validation, import transaction policy. |

### 4.4 Suggested namespace

Future implementation can use:

```text
app/
  Policies/
    PolicyEngine.php
    PolicyContext.php
    PolicyResult.php
    PolicyRuleInterface.php
    PolicyRegistry.php
    PolicyDefinitionRepository.php
    PolicyQueryBuilder.php
    Age/
    Insurance/
    SocialSupport/
    Education/
    Employment/
    HouseholdRelation/
    Statistics/
    Warning/
    Import/
```

This is an implementation detail, not an Architecture Freeze change, as long as controllers still call services and data access still goes through models/repositories.

## 5. Conversion Roadmap

### Step 0 - Current sprint: survey only

- Create this design document.
- No runtime changes.
- No production logic changes.

Rollback: not needed, docs-only.

### Step 1 - Golden behavior tests

- Add tests around current behavior before refactor.
- Cover both `thon09` and `thon10`.
- Fixtures:
  - Age boundary: 15, 16, 17, 18, 59, 60, 69, 70, 74, 75.
  - Upcoming 70/75 within and outside 90 days.
  - BHYT missing/active/expired/expiring.
  - Alive/deceased/transferred/deleted.
  - Household category conflicts.
  - Import duplicate rows and missing required columns.

Rollback: tests only.

### Step 2 - Extract definitions without changing behavior

- Move thresholds/status names into policy definition classes/config.
- Keep old call sites and exact values.
- Do not change SQL output.

Rollback: revert definition extraction commit.

### Step 3 - Add Policy Engine in shadow mode

- Implement engine and evaluate in parallel in low-risk paths.
- Compare new policy result with legacy result.
- Log mismatch only in debug/staging.
- Do not use engine output for production decisions yet.

Rollback: disable shadow mode.

### Step 4 - Migrate Warning Policy first

- Move `config/policy_alerts.php` and `PolicyAlert::filterCondition()` logic into Warning/Age/SocialSupport policies.
- Keep API response shape unchanged.
- Keep old model as fallback until parity passes.

Rollback: feature flag to legacy `PolicyAlert`.

### Step 5 - Migrate Citizen defaults and Import Policy

- Route age, education, insurance, social-support defaults through Policy Engine.
- Import validation calls Import Policy and then existing `Citizen`/`Household` models.
- Preserve import transaction behavior.

Rollback: switch Citizen/Import services back to legacy helpers.

### Step 6 - Migrate Statistics/Dashboard/Report SQL predicates

- Replace duplicated SQL age/status/category predicates with `PolicyQueryBuilder`.
- Run snapshot comparison for Dashboard, Report, PopulationStatistics.

Rollback: revert predicate replacement commit by module.

### Step 7 - Tenant-configurable policies

- Only after stable parity.
- Optional future: allow tenant-specific threshold overrides via config table.
- Must keep safe defaults and audit policy changes.

Rollback: ignore tenant overrides and use default definitions.

## 6. Policy Test Suite

Policy Test Suite is the shared test foundation for every Policy Engine sprint. It is intentionally lightweight and uses plain PHP so policy tests can run without adding runtime dependencies or changing production logic.

### 6.1 Directory Structure

Policy tests live under `tests/policies/`:

```text
tests/policies/
  age/
  insurance/
  social-support/
  education/
  employment/
  household/
  warning/
  statistics/
  import/
  fixtures/
  helpers/
  bootstrap.php
  run.php
```

Each policy group owns one directory. New policy tests must be added to the matching directory instead of creating a separate test framework.

### 6.2 Test Runner

- `tests/policies/run.php` discovers files ending with `Test.php`.
- `tests/policies/bootstrap.php` provides the shared test registry and assertions.
- `tests/age-policy.test.php` remains as a compatibility wrapper and delegates to the shared runner.

Standard commands:

```bash
composer run test:policies
composer run test:policy-regression
php tests/policies/run.php
php tests/policies/run.php age
```

Every policy sprint must run:

- Unit Test.
- Policy Test Suite.
- Regression Test.
- Multi-tenant Test.

### 6.3 Shared Helpers

Shared fixtures live in `tests/policies/helpers/PolicyFixtures.php`.

Current helper factories:

- `createHousehold()`
- `createCitizen()`
- `createStudent()`
- `createSenior()`
- `createDisabled()`

Future policy groups should extend these shared helpers only when the same setup is useful across multiple policies. One-off setup should stay inside the specific policy test file.

### 6.4 Test Matrix

The canonical policy matrix lives in `tests/policies/fixtures/PolicyTestMatrix.php`.

Current age boundary set:

- 0
- 5
- 6
- 17
- 18
- 59
- 60
- 69
- 70
- 74
- 75
- 90

Future policy sprints may add rows to the matrix, but must not change existing expected values unless the Product Owner approves a deliberate behavior change.

### 6.5 Golden Dataset

The shared golden dataset lives in `tests/policies/fixtures/PolicyGoldenDataset.php`.

Purpose:

- Provide stable representative citizens for all policy groups.
- Detect unintended changes after each sprint.
- Make cross-policy behavior explicit before production acceptance.

Rules:

- Add new expected fields when a policy needs them.
- Do not remove or silently change existing expectations.
- If a policy intentionally changes output, document the reason in that sprint report and update the dataset in the same commit.

### 6.6 Adding A New Policy Test

To add a policy test:

1. Put the test file in the matching policy directory, for example `tests/policies/insurance/InsurancePolicyTest.php`.
2. Name the file with the suffix `Test.php`.
3. Register cases with `policy_test('case name', function (): void { ... });`.
4. Use `PolicyFixtures`, `PolicyTestMatrix`, and `PolicyGoldenDataset` when possible.
5. Run `composer run test:policies`.

Do not create a new runner, assertion library, fixture convention, or policy-specific framework unless there is a real limitation in the existing suite.

### 6.7 Regression Contract

Before any policy sprint is committed:

- Policy suite must pass.
- Existing automated regression must pass.
- TenantResolver/multi-tenant tests must pass.
- Production/Staging acceptance must pass on at least Thon 09 and Thon 10 when production behavior is affected.

## 7. Test Plan For Future Refactor

Minimum tests per policy group:

- Unit tests for pure PHP policy rules.
- SQL predicate tests against fixture database.
- Snapshot tests for Dashboard metrics and Report rows.
- Import preview/process tests with rollback.
- Multi-tenant tests on at least two tenant databases.
- Regression tests for UI screens that show policy warnings.
- Encoding tests for policy labels and descriptions.

Acceptance criteria before replacing legacy logic:

- New output equals legacy output for existing fixtures.
- No tenant data crossing.
- Dashboard, Report, Import, Export, PDF, Excel still pass smoke tests.
- No additional policy queries on modules that do not display policy warnings.

## 8. Production Risk

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Age/eligibility mismatch | Wrong warning or wrong statistics. | Golden tests with boundary ages before refactor. |
| SQL predicate mismatch | Dashboard/Report numbers change unexpectedly. | Snapshot compare old vs new SQL result. |
| Import behavior change | Imported citizens get wrong defaults. | Keep import transaction and compare preview/process outputs. |
| Tenant isolation regression | Data leakage across tenants. | Always use TenantResolver/TenantContext and two-tenant regression tests. |
| Encoding regression | Policy labels show mojibake. | Keep strings UTF-8 and add UI verification for policy components. |
| Performance regression | Dashboard/report slower. | Measure query count/time before and after predicate migration. |

## 9. Safe Rollback Strategy

For every future refactor step:

1. Keep legacy logic until parity passes.
2. Introduce Policy Engine behind a feature flag or service switch.
3. Migrate one policy group at a time.
4. Commit each group separately.
5. If mismatch or production risk appears, disable new policy path and fall back to legacy logic.
6. Do not remove legacy helper methods until after production acceptance.

## 10. Recommendation

Proceed with Policy Engine, but start with tests and shadow mode. Do not immediately rewrite Dashboard, Report, Citizen, Import, or PolicyAlert.

Recommended first implementation order after approval:

1. Golden behavior tests.
2. Policy definitions and query fragments.
3. Shadow-mode Policy Engine.
4. Warning Policy migration.
5. Citizen default and Import Policy migration.
6. Statistics/Dashboard/Report predicate migration.

This order reduces production risk and keeps current tenant behavior stable while moving duplicated business rules into a maintainable Policy Engine.
