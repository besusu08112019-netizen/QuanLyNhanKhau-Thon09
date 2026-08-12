<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use App\Policies\InsurancePolicy;
use App\Services\StudentStatusService;

final class Report extends BaseModel
{
    private ?PopulationStatistics $statistics = null;

    private const MERITORIOUS_POLICY_COLUMNS = [
        'martyr_relative',
        'wounded_soldier',
        'sick_soldier',
        'chemical_warfare_victim',
        'imprisoned_resistance_activist',
        'youth_volunteer',
        'resistance_hero',
        'revolutionary_activist',
    ];

    private const CITIZEN_FLAG_COLUMNS = [
        'has_health_insurance',
        'party_member',
        'youth_union_member',
        'women_union_member',
        'farmers_union_member',
        'veterans_union_member',
        'elderly_union_member',
        'meritorious_person',
        'martyr_relative',
        'wounded_soldier',
        'sick_soldier',
        'chemical_warfare_victim',
        'imprisoned_resistance_activist',
        'youth_volunteer',
        'resistance_hero',
        'revolutionary_activist',
        'disabled_person',
        'social_assistance',
        'employed',
        'unemployed',
        'freelance_labor',
        'out_province_labor',
        'foreign_labor',
        'not_attending_school',
        'pupil',
        'student',
        'retired',
    ];

    public function build(string $type, array $filters = []): array
    {
        return match ($type) {
            'household', 'households' => $this->householdReport($filters),
            'household-business', 'household_business', 'business-households' => (new \App\Models\HouseholdBusiness())->report('all', $filters),
            'household-business-establishments' => (new \App\Models\HouseholdBusiness())->report('establishments', $filters),
            'household-business-households', 'household-business-by-household', 'business-households-by-household' => (new \App\Models\HouseholdBusiness())->report('household_summary', $filters),
            'household-business-production', 'production-households', 'business-production' => (new \App\Models\HouseholdBusiness())->report('production', $filters),
            'household-business-trade', 'business-trade', 'trade-households' => (new \App\Models\HouseholdBusiness())->report('business', $filters),
            'household-business-sector', 'business-sector' => (new \App\Models\HouseholdBusiness())->report('sector', $filters),
            'household-business-status', 'business-status' => (new \App\Models\HouseholdBusiness())->report('status', $filters),
            'household-business-gis', 'business-gis' => (new \App\Models\HouseholdBusiness())->report('gis', $filters),
            'household-business-ocop', 'business-ocop' => (new \App\Models\HouseholdBusiness())->report('ocop', $filters),
            'household-business-food-safety', 'business-food-safety' => (new \App\Models\HouseholdBusiness())->report('food_safety', $filters),
            'household-business-social-insurance', 'business-social-insurance' => (new \App\Models\HouseholdBusiness())->report('social_insurance', $filters),
            'household-business-economic-type', 'business-economic-type' => (new \App\Models\HouseholdBusiness())->report('economic_type', $filters),
            'household-business-scale', 'business-scale' => (new \App\Models\HouseholdBusiness())->report('scale', $filters),
            'household-business-product', 'business-product' => (new \App\Models\HouseholdBusiness())->report('product', $filters),
            'livestock', 'livestock-list' => (new \App\Models\Livestock())->report('all', $filters),
            'livestock-by-type', 'livestock-type' => (new \App\Models\Livestock())->report('by_type', $filters),
            'livestock-vaccinated' => (new \App\Models\Livestock())->report('vaccinated', $filters),
            'livestock-unvaccinated' => (new \App\Models\Livestock())->report('unvaccinated', $filters),
            'livestock-disease' => (new \App\Models\Livestock())->report('disease', $filters),
            'defense-security', 'defense_security', 'defense-security-summary' => (new \App\Models\DefenseSecurity())->report('summary', $filters),
            'defense-security-nvqs', 'nvqs', 'military-service' => (new \App\Models\DefenseSecurity())->report('nvqs', $filters),
            'defense-security-upcoming-registration' => (new \App\Models\DefenseSecurity())->report('upcoming_registration', $filters),
            'defense-security-registration-age' => (new \App\Models\DefenseSecurity())->report('registration_age', $filters),
            'defense-security-unregistered' => (new \App\Models\DefenseSecurity())->report('unregistered', $filters),
            'defense-security-preliminary' => (new \App\Models\DefenseSecurity())->report('preliminary', $filters),
            'defense-security-medical' => (new \App\Models\DefenseSecurity())->report('medical', $filters),
            'defense-security-eligible' => (new \App\Models\DefenseSecurity())->report('eligible', $filters),
            'defense-security-deferred' => (new \App\Models\DefenseSecurity())->report('deferred', $filters),
            'defense-security-exempt' => (new \App\Models\DefenseSecurity())->report('exempt', $filters),
            'defense-security-selected' => (new \App\Models\DefenseSecurity())->report('selected', $filters),
            'defense-security-enlisted' => (new \App\Models\DefenseSecurity())->report('enlisted', $filters),
            'defense-security-active-service' => (new \App\Models\DefenseSecurity())->report('active_service', $filters),
            'defense-security-discharged' => (new \App\Models\DefenseSecurity())->report('discharged', $filters),
            'defense-security-militia', 'militia' => (new \App\Models\DefenseSecurity())->report('militia', $filters),
            'defense-security-antt', 'defense-security-security-force', 'security-force' => (new \App\Models\DefenseSecurity())->report('security_force', $filters),
            'party-members', 'party-member-list' => (new \App\Models\PartyMember())->report('all', $filters),
            'party-members-branch' => (new \App\Models\PartyMember())->report('branch', $filters),
            'party-members-age' => (new \App\Models\PartyMember())->report('age', $filters),
            'party-members-gender' => (new \App\Models\PartyMember())->report('gender', $filters),
            'party-members-position' => (new \App\Models\PartyMember())->report('position', $filters),
            'party-members-official' => (new \App\Models\PartyMember())->report('official', $filters),
            'party-members-probationary' => (new \App\Models\PartyMember())->report('probationary', $filters),
            'party-members-status' => (new \App\Models\PartyMember())->report('status', $filters),
            'vehicles', 'vehicles-list', 'vehicle-list' => (new \App\Models\Vehicle())->report('all', $filters),
            'vehicles-by-type', 'vehicle-type' => (new \App\Models\Vehicle())->report('by_type', $filters),
            'vehicles-missing-plate', 'vehicle-missing-plate' => (new \App\Models\Vehicle())->report('missing_plate', $filters),
            'vehicles-expired-inspection', 'vehicle-expired-inspection' => (new \App\Models\Vehicle())->report('expired_inspection', $filters),
            'vehicles-expired-insurance', 'vehicle-expired-insurance' => (new \App\Models\Vehicle())->report('expired_insurance', $filters),
            'contributions', 'contribution-campaigns', 'household-contributions' => (new \App\Models\HouseholdContribution())->report('all', $filters),
            'contributions-summary' => (new \App\Models\HouseholdContribution())->report('summary', $filters),
            'contributions-list', 'contributions-household-list' => (new \App\Models\HouseholdContribution())->report('list', $filters),
            'contributions-households', 'contributions-by-household' => (new \App\Models\HouseholdContribution())->report('households', $filters),
            'contributions-collection', 'contributions-signature', 'contributions-signatures' => (new \App\Models\HouseholdContribution())->report('collection', $filters),
            'contributions-population' => (new \App\Models\HouseholdContribution())->report('population', $filters),
            'contributions-financial', 'contributions-finance' => (new \App\Models\HouseholdContribution())->report('finance', $filters),
            'contributions-exempt', 'contributions-exemptions' => (new \App\Models\HouseholdContribution())->report('exemptions', $filters),
            'contributions-detail', 'contributions-campaign-detail' => (new \App\Models\HouseholdContribution())->report('detail', $filters),
            'contributions-by-contribution', 'contributions-by-campaign' => (new \App\Models\HouseholdContribution())->report('by_contribution', $filters),
            'contributions-partial' => (new \App\Models\HouseholdContribution())->report('partial', $filters),
            'contributions-year-summary' => (new \App\Models\HouseholdContribution())->report('year-summary', $filters),
            'contributions-paid' => (new \App\Models\HouseholdContribution())->report('paid', $filters),
            'contributions-unpaid', 'contributions-debt' => (new \App\Models\HouseholdContribution())->report('unpaid', $filters),
            'contributions-unpaid-list' => (new \App\Models\HouseholdContribution())->report('unpaid-list', $filters),
            'agricultural-land', 'agricultural_land', 'agricultural-land-list' => (new \App\Models\AgriculturalLandZone())->report('all', $filters),
            'agricultural-land-village', 'agricultural-land-summary' => (new \App\Models\AgriculturalLandZone())->report('village', $filters),
            'agricultural-land-zone', 'agricultural-land-by-zone' => (new \App\Models\AgriculturalLandZone())->report('zone', $filters),
            'agricultural-land-year', 'agricultural-land-by-year' => (new \App\Models\AgriculturalLandZone())->report('year', $filters),
            'agricultural-land-year-compare', 'agricultural-land-year-comparison', 'agricultural-land-compare', 'agricultural-land-comparison' => (new \App\Models\AgriculturalLandZone())->report('year_compare', $filters),
            'agriculture', 'agriculture-list' => (new \App\Models\AgricultureProduction())->report('all', $filters),
            'agriculture-parcels' => (new \App\Models\AgricultureProduction())->report('all', $filters),
            'agriculture-producers' => (new \App\Models\AgricultureProduction())->report('producer', $filters),
            'agriculture-area' => (new \App\Models\AgricultureProduction())->report('area', $filters),
            'agriculture-crop' => (new \App\Models\AgricultureProduction())->report('crop', $filters),
            'agriculture-season' => (new \App\Models\AgricultureProduction())->report('season', $filters),
            'agriculture-production' => (new \App\Models\AgricultureProduction())->report('production', $filters),
            'agriculture-revenue' => (new \App\Models\AgricultureProduction())->report('revenue', $filters),
            'agriculture-damage' => (new \App\Models\AgricultureProduction())->report('damage', $filters),
            'houses', 'houses-list', 'house-list' => (new \App\Models\House())->report('all', $filters),
            'houses-degraded', 'house-degraded' => (new \App\Models\House())->report('degraded', $filters),
            'houses-temporary', 'house-temporary' => (new \App\Models\House())->report('temporary', $filters),
            'houses-fire-risk', 'house-fire-risk' => (new \App\Models\House())->report('high_fire_risk', $filters),
            'houses-missing-gps', 'house-missing-gps' => (new \App\Models\House())->report('missing_gps', $filters),
            'houses-business', 'house-business' => (new \App\Models\House())->report('business_usage', $filters),
            'house-structures', 'houses-structures' => (new \App\Models\House())->report('structures', $filters),
            'public-assets', 'public_assets', 'public-assets-list', 'public-asset-list' => (new \App\Models\PublicAsset())->report('all', $filters),
            'public-assets-located', 'public-assets-gps' => (new \App\Models\PublicAsset())->report('located', $filters),
            'public-assets-missing-gps', 'public-assets-unlocated' => (new \App\Models\PublicAsset())->report('missing_gps', $filters),
            'public-assets-inventory', 'public-asset-inventory' => (new \App\Models\PublicAsset())->report('inventory', $filters),
            'gis', 'gis-households' => $this->gisReport($filters),
            'gis-located' => $this->gisReport($filters, 'located'),
            'gis-unlocated', 'gis-missing' => $this->gisReport($filters, 'unlocated'),
            'digital-profile', 'digital-profiles' => $this->digitalProfileReport($filters),
            'profile-complete' => $this->digitalProfileReport($filters, 'complete'),
            'profile-missing-photo' => $this->digitalProfileReport($filters, 'missing_photo'),
            'profile-missing-documents' => $this->digitalProfileReport($filters, 'missing_documents'),
            'profile-incomplete' => $this->digitalProfileReport($filters, 'incomplete'),
            'population', 'citizen', 'citizens' => $this->populationReport($filters),
            'temporary-residence', 'temporary_residence', 'temporary' => $this->temporaryResidenceReport($filters),
            'temporary-absence', 'temporary_absence', 'absence' => $this->temporaryAbsenceReport($filters),
            'births', 'birth' => $this->birthReport($filters),
            'deaths', 'death' => $this->deathReport($filters),
            'migration', 'movement', 'movement-summary' => $this->migrationReport($filters),
            'gender' => $this->groupedCitizenReport($filters, 'gender', 'Giá»›i tÃ­nh'),
            'age' => $this->ageReport($filters),
            'residency' => $this->groupedCitizenReport($filters, 'residency_status', 'TÃ¬nh tráº¡ng cÆ° trÃº'),
            'health-insurance', 'health_insurance', 'has_health_insurance', 'bhyt', 'bao-hiem-y-te' => $this->healthInsuranceReport($filters),
            'health-insurance-missing', 'bhyt-missing', 'bhyt-chua-tham-gia' => $this->healthInsuranceListReport('missing', $filters),
            'health-insurance-expiring', 'bhyt-expiring', 'bhyt-sap-het-han' => $this->healthInsuranceListReport('expiring', $filters),
            'health-insurance-expired', 'bhyt-expired', 'bhyt-het-han' => $this->healthInsuranceListReport('expired', $filters),
            'health-insurance-household', 'bhyt-household' => $this->healthInsuranceHouseholdReport($filters),
            'health-insurance-area', 'bhyt-area' => $this->healthInsuranceAreaReport($filters),
            'party-members', 'party_members', 'party_member', 'party', 'dang-vien' => $this->flagCitizenReport('BÃ¡o cÃ¡o Äáº£ng viÃªn', 'party_member', 'Äáº£ng viÃªn', $filters),
            'youth-union', 'youth_union', 'youth_union_member', 'doan-vien' => $this->flagCitizenReport('BÃ¡o cÃ¡o ÄoÃ n viÃªn', 'youth_union_member', 'ÄoÃ n viÃªn', $filters),
            'meritorious-people', 'meritorious', 'meritorious_person', 'nguoi-co-cong' => $this->meritoriousCitizenReport($filters),
            'disabled-people', 'disabled', 'disabled_person', 'disability', 'nguoi-khuyet-tat' => $this->flagCitizenReport('BÃ¡o cÃ¡o NgÆ°á»i khuyáº¿t táº­t', 'disabled_person', 'NgÆ°á»i khuyáº¿t táº­t', $filters),
            'labor', 'labour', 'lao-dong' => $this->laborReport($filters),
            'elderly', 'nguoi-cao-tuoi' => $this->ageRangeReport('BÃ¡o cÃ¡o NgÆ°á»i cao tuá»•i', AgePolicy::STATISTICAL_ELDERLY_MIN_AGE, null, $filters),
            'children', 'tre-em' => $this->ageRangeReport('BÃ¡o cÃ¡o Tráº» em', null, AgePolicy::CHILD_MAX_INCLUSIVE_AGE, $filters),
            'poor-households', 'poor_households', 'poor', 'ho-ngheo' => $this->householdCategoryReport('BÃ¡o cÃ¡o Há»™ nghÃ¨o', 'poor', $filters),
            'near-poor-households', 'near_poor_households', 'near_poor', 'ho-can-ngheo' => $this->householdCategoryReport('BÃ¡o cÃ¡o Há»™ cáº­n nghÃ¨o', 'near_poor', $filters),
            'special' => $this->specialHouseholdReport($filters),
            default => $this->summaryReport($filters),
        };
    }

    public function summaryReport(array $filters = []): array
    {
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN gender='Nam' THEN 1 ELSE 0 END),0) AS male, COALESCE(SUM(CASE WHEN gender='Ná»¯' THEN 1 ELSE 0 END),0) AS female, COALESCE(SUM(CASE WHEN residency_status='TEMPORARY' THEN 1 ELSE 0 END),0) AS temporary, COALESCE(SUM(CASE WHEN presence_status='AWAY' THEN 1 ELSE 0 END),0) AS away, COALESCE(SUM(CASE WHEN " . AgePolicy::childConditionSql('c') . " THEN 1 ELSE 0 END),0) AS children, COALESCE(SUM(CASE WHEN " . AgePolicy::statisticalElderlyConditionSql('c') . " THEN 1 ELSE 0 END),0) AS elderly" . $this->flagSelects('c') . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $citizenWhere", $citizenParams) ?: [];
        $meritoriousHouseholdExpr = $this->meritoriousHouseholdExists('h');
        $disabledHouseholdExpr = $this->disabledHouseholdExists('h');
        $policySubjectHouseholdExpr = $this->policySubjectHouseholdExists('h');
        $activePovertyTypeExpr = $this->activePovertyTypeExpr('h');
        $poorHouseholdExpr = '(h.poor_household=1 OR ' . $this->activePovertyRecordExists('h', 'POOR') . ')';
        $nearPoorHouseholdExpr = '(h.near_poor_household=1 OR ' . $this->activePovertyRecordExists('h', 'NEAR_POOR') . ')';
        $policyHouseholdExpr = '(' . $policySubjectHouseholdExpr . ' OR ' . $meritoriousHouseholdExpr . ' OR ' . $disabledHouseholdExpr . ')';
        $households = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN $meritoriousHouseholdExpr THEN 1 ELSE 0 END),0) AS meritorious, COALESCE(SUM(CASE WHEN $poorHouseholdExpr THEN 1 ELSE 0 END),0) AS poor, COALESCE(SUM(CASE WHEN $nearPoorHouseholdExpr THEN 1 ELSE 0 END),0) AS near_poor, COALESCE(SUM(CASE WHEN $disabledHouseholdExpr THEN 1 ELSE 0 END),0) AS disabled, COALESCE(SUM(CASE WHEN $policyHouseholdExpr THEN 1 ELSE 0 END),0) AS policy, COALESCE(SUM(CASE WHEN NOT $poorHouseholdExpr AND NOT $nearPoorHouseholdExpr AND NOT " . $this->activePovertyRecordExists('h') . " AND NOT $policyHouseholdExpr THEN 1 ELSE 0 END),0) AS normal FROM households h $householdWhere", $householdParams) ?: [];
        $total = max(1, (int) ($citizens['total'] ?? 0));
        $healthInsurance = (new Dashboard())->healthInsuranceStats($filters);
        $rows = [
            ['Tá»•ng sá»‘ há»™', (int) ($households['total'] ?? 0)],
            ['Tá»•ng sá»‘ nhÃ¢n kháº©u', (int) ($citizens['total'] ?? 0)],
            ['Nam', (int) ($citizens['male'] ?? 0)],
            ['Ná»¯', (int) ($citizens['female'] ?? 0)],
            ['Táº¡m trÃº', (int) ($citizens['temporary'] ?? 0)],
            ['Táº¡m váº¯ng', (int) ($citizens['away'] ?? 0)],
            ['CÃ³ BHYT', $this->healthInsuranceCoveredText($healthInsurance)],
            ['ChÆ°a cÃ³ BHYT', $healthInsurance['uninsured'] . ' nhÃ¢n kháº©u'],
            ['Tá»· lá»‡ bao phá»§ BHYT', $this->percentValue($healthInsurance['coverage_percent'])],
            ['Äáº£ng viÃªn', $this->countPercent($citizens, 'party_member', $total)],
            ['ÄoÃ n viÃªn', $this->countPercent($citizens, 'youth_union_member', $total)],
            ['Há»™i viÃªn Há»™i Phá»¥ ná»¯', $this->countPercent($citizens, 'women_union_member', $total)],
            ['Há»™i viÃªn Há»™i NÃ´ng dÃ¢n', $this->countPercent($citizens, 'farmers_union_member', $total)],
            ['Há»™i viÃªn Há»™i Cá»±u chiáº¿n binh', $this->countPercent($citizens, 'veterans_union_member', $total)],
            ['Há»™i viÃªn Há»™i NgÆ°á»i cao tuá»•i', $this->countPercent($citizens, 'elderly_union_member', $total)],
            ['NgÆ°á»i cÃ³ cÃ´ng', $this->countPercent($citizens, 'meritorious_person', $total)],
            ['ThÆ°Æ¡ng binh', $this->countPercent($citizens, 'wounded_soldier', $total)],
            ['Bá»‡nh binh', $this->countPercent($citizens, 'sick_soldier', $total)],
            ['ThÃ¢n nhÃ¢n liá»‡t sÄ©', $this->countPercent($citizens, 'martyr_relative', $total)],
            ['NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ nhiá»…m cháº¥t Ä‘á»™c hÃ³a há»c', $this->countPercent($citizens, 'chemical_warfare_victim', $total)],
            ['NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ Ä‘á»‹ch báº¯t tÃ¹, Ä‘Ã y', $this->countPercent($citizens, 'imprisoned_resistance_activist', $total)],
            ['Thanh niÃªn xung phong', $this->countPercent($citizens, 'youth_volunteer', $total)],
            ['Anh hÃ¹ng LLVTND / Anh hÃ¹ng Lao Ä‘á»™ng thá»i ká»³ khÃ¡ng chiáº¿n', $this->countPercent($citizens, 'resistance_hero', $total)],
            ['NgÆ°á»i hoáº¡t Ä‘á»™ng cÃ¡ch máº¡ng', $this->countPercent($citizens, 'revolutionary_activist', $total)],
            ['NgÆ°á»i khuyáº¿t táº­t', $this->countPercent($citizens, 'disabled_person', $total)],
            ['Äang hÆ°á»Ÿng trá»£ cáº¥p xÃ£ há»™i', $this->countPercent($citizens, 'social_assistance', $total)],
            ['CÃ³ viá»‡c lÃ m', $this->countPercent($citizens, 'employed', $total)],
            ['Tháº¥t nghiá»‡p', $this->countPercent($citizens, 'unemployed', $total)],
            ['Lao Ä‘á»™ng tá»± do', $this->countPercent($citizens, 'freelance_labor', $total)],
            ['Lao Ä‘á»™ng ngoÃ i tá»‰nh', $this->countPercent($citizens, 'out_province_labor', $total)],
            ['Lao Ä‘á»™ng nÆ°á»›c ngoÃ i', $this->countPercent($citizens, 'foreign_labor', $total)],
            ['Tráº» em', (int) ($citizens['children'] ?? 0) . ' (' . $this->percent((int) ($citizens['children'] ?? 0), $total) . ')'],
            ['NgÆ°á»i cao tuá»•i', (int) ($citizens['elderly'] ?? 0) . ' (' . $this->percent((int) ($citizens['elderly'] ?? 0), $total) . ')'],
            ['Há»™ nghÃ¨o', (int) ($households['poor'] ?? 0)],
            ['Há»™ cáº­n nghÃ¨o', (int) ($households['near_poor'] ?? 0)],
            ['Há»™ chÃ­nh sÃ¡ch', (int) ($households['policy'] ?? 0)],
            ['Há»™ cÃ³ cÃ´ng', (int) ($households['meritorious'] ?? 0)],
            ['Há»™ bÃ¬nh thÆ°á»ng', (int) ($households['normal'] ?? 0)],
            ['Há»™ cÃ³ ngÆ°á»i khuyáº¿t táº­t', (int) ($households['disabled'] ?? 0)],
        ];
        return $this->table('BÃ¡o cÃ¡o tá»•ng há»£p', ['Chá»‰ tiÃªu', 'Sá»‘ lÆ°á»£ng / Tá»· lá»‡'], $rows, $filters);
    }

    public function householdReport(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $meritoriousHouseholdExpr = $this->meritoriousHouseholdExists('h');
        $disabledHouseholdExpr = $this->disabledHouseholdExists('h');
        $policySubjectHouseholdExpr = $this->policySubjectHouseholdExists('h');
        $activePovertyTypeExpr = $this->activePovertyTypeExpr('h');
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.phone, COALESCE(v.total_members,0) AS members, COALESCE(v.at_home_count,0) AS at_home, COALESCE(v.away_count,0) AS away, $meritoriousHouseholdExpr AS meritorious_policy, $disabledHouseholdExpr AS disabled_policy, $policySubjectHouseholdExpr AS policy_subject_household, $activePovertyTypeExpr AS active_poverty_type, h.poor_household, h.near_poor_household, h.note FROM households h LEFT JOIN v_household_member_counts v ON v.household_id=h.id $where ORDER BY h.household_code", $params);
        return $this->table('Danh sÃ¡ch há»™ dÃ¢n', ['MÃ£ há»™','Chá»§ há»™','Äá»‹a chá»‰','Sá»‘ Ä‘iá»‡n thoáº¡i','NhÃ¢n kháº©u','á»ž nhÃ ','Äi váº¯ng','Diá»‡n há»™'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['phone'], (int) $r['members'], (int) $r['at_home'], (int) $r['away'], $this->householdCategories($r)], $rows), $filters);
    }

    public function populationReport(array $filters = []): array { return $this->citizenListReport('Danh sÃ¡ch nhÃ¢n kháº©u', $filters); }
    public function temporaryResidenceReport(array $filters = []): array { $filters['residencyStatus'] = 'TEMPORARY'; return $this->citizenListReport('Danh sÃ¡ch táº¡m trÃº', $filters); }
    public function temporaryAbsenceReport(array $filters = []): array { $filters['presenceStatus'] = 'AWAY'; return $this->citizenListReport('Danh sÃ¡ch táº¡m váº¯ng', $filters); }
    public function birthReport(array $filters = []): array { return $this->movementDetailReport('BÃ¡o cÃ¡o khai sinh', ['BIRTH'], $filters); }
    public function deathReport(array $filters = []): array { return $this->movementDetailReport('BÃ¡o cÃ¡o khai tá»­', ['DEATH'], $filters); }
    public function migrationReport(array $filters = []): array { return $this->movementDetailReport('BÃ¡o cÃ¡o biáº¿n Ä‘á»™ng dÃ¢n cÆ°', ['BIRTH', 'DEATH', 'MOVE_IN', 'MOVE_OUT', 'TEMPORARY_RESIDENCE', 'TEMPORARY_ABSENCE', 'OTHER'], $filters); }

    public function groupedCitizenReport(array $filters, string $field, string $label): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $fieldSql = $field === 'residency_status' ? "CASE c.residency_status WHEN 'TEMPORARY' THEN 'Táº¡m trÃº' ELSE 'ThÆ°á»ng trÃº' END" : "COALESCE(NULLIF(c.$field,''),'KhÃ¡c')";
        $rows = $this->fetchAll("SELECT $fieldSql AS label, COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY label", $params);
        return $this->table('BÃ¡o cÃ¡o theo ' . mb_strtolower($label), [$label, 'Sá»‘ lÆ°á»£ng'], array_map(fn($r) => [$r['label'], (int) $r['total']], $rows), $filters);
    }

    public function ageReport(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $ageSql = AgePolicy::ageSql('c');
        $rows = $this->fetchAll("SELECT CASE WHEN $ageSql <= " . AgePolicy::AGE_BAND_0_5_MAX . " THEN '0-5 tuá»•i' WHEN $ageSql <= " . AgePolicy::AGE_BAND_6_14_MAX . " THEN '6-14 tuá»•i' WHEN $ageSql <= " . AgePolicy::AGE_BAND_15_17_MAX . " THEN '15-17 tuá»•i' WHEN $ageSql <= " . AgePolicy::AGE_BAND_18_59_MAX . " THEN '18-59 tuá»•i' ELSE 'Tá»« 60 tuá»•i trá»Ÿ lÃªn' END AS label, COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY MIN($ageSql)", $params);
        return $this->table('BÃ¡o cÃ¡o theo Ä‘á»™ tuá»•i', ['Äá»™ tuá»•i', 'Sá»‘ lÆ°á»£ng'], array_map(fn($r) => [$r['label'], (int) $r['total']], $rows), $filters);
    }

    public function specialHouseholdReport(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $meritoriousHouseholdExpr = $this->meritoriousHouseholdExists('h');
        $disabledHouseholdExpr = $this->disabledHouseholdExists('h');
        $policySubjectHouseholdExpr = $this->policySubjectHouseholdExists('h');
        $activePovertyTypeExpr = $this->activePovertyTypeExpr('h');
        $where .= " AND ($meritoriousHouseholdExpr OR $disabledHouseholdExpr OR $policySubjectHouseholdExpr OR h.poor_household=1 OR h.near_poor_household=1)";
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.phone, $meritoriousHouseholdExpr AS meritorious_policy, $disabledHouseholdExpr AS disabled_policy, $policySubjectHouseholdExpr AS policy_subject_household, $activePovertyTypeExpr AS active_poverty_type, h.poor_household, h.near_poor_household, h.note FROM households h $where ORDER BY h.household_code", $params);
        return $this->table('Danh sÃ¡ch ngÆ°á»i cÃ³ cÃ´ng, há»™ nghÃ¨o, cáº­n nghÃ¨o, khuyáº¿t táº­t', ['MÃ£ há»™','Chá»§ há»™','Äá»‹a chá»‰','Sá»‘ Ä‘iá»‡n thoáº¡i','Diá»‡n há»™'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['phone'], $this->householdCategories($r)], $rows), $filters);
    }

    public function householdCategoryReport(string $title, string $category, array $filters = []): array
    {
        $filters['category'] = $category;
        return $this->householdReport($filters + ['reportTitle' => $title]);
    }

    public function flagCitizenReport(string $title, string $column, string $label, array $filters = []): array
    {
        if (!$this->columnExists('citizens', $column)) return $this->table($title, ['Chá»‰ tiÃªu', 'Sá»‘ lÆ°á»£ng'], [[$label, '0 (0%)']], $filters);
        [$where, $params] = $this->citizenWhere($filters);
        $total = (int) ($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where", $params)['total'] ?? 0);
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where AND c.$column=1 ORDER BY h.household_code, c.full_name", $params);
        $headers = ['MÃ£ há»™','MÃ£ nhÃ¢n kháº©u','Há» tÃªn','Giá»›i tÃ­nh','NgÃ y sinh','CCCD','Sá»‘ Ä‘iá»‡n thoáº¡i'];
        $body = [["Tá»•ng $label", count($rows) . ' / ' . $total . ' (' . $this->percent(count($rows), max(1, $total)) . ')']];
        foreach ($rows as $r) $body[] = [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['phone']];
        return $this->table($title, $headers, $body, $filters);
    }

    public function meritoriousCitizenReport(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $condition = $this->meritoriousCitizenExpression('c');
        $title = 'BÃ¡o cÃ¡o NgÆ°á»i cÃ³ cÃ´ng';
        if ($condition === '0=1') return $this->table($title, ['Chá»‰ tiÃªu', 'Sá»‘ lÆ°á»£ng'], [['NgÆ°á»i cÃ³ cÃ´ng', '0 (0%)']], $filters);
        $total = (int) ($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where", $params)['total'] ?? 0);
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where AND $condition ORDER BY h.household_code, c.full_name", $params);
        $headers = ['MÃ£ há»™','MÃ£ nhÃ¢n kháº©u','Há» tÃªn','Giá»›i tÃ­nh','NgÃ y sinh','CCCD','Sá»‘ Ä‘iá»‡n thoáº¡i'];
        $body = [['Tá»•ng NgÆ°á»i cÃ³ cÃ´ng', count($rows) . ' / ' . $total . ' (' . $this->percent(count($rows), max(1, $total)) . ')']];
        foreach ($rows as $r) $body[] = [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['phone']];
        return $this->table($title, $headers, $body, $filters);
    }

    public function healthInsuranceReport(array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        $stats = (new Dashboard())->healthInsuranceStats($filters);
        $rows = [
            ['Tá»•ng sá»‘ nhÃ¢n kháº©u', $stats['total']],
            ['CÃ³ BHYT', $this->healthInsuranceCoveredText($stats)],
            ['ChÆ°a cÃ³ BHYT', $stats['uninsured'] . ' nhÃ¢n kháº©u'],
            ['Tá»· lá»‡ bao phá»§', $this->percentValue($stats['coverage_percent'])],
        ];
        return $this->table('BÃ¡o cÃ¡o Báº£o hiá»ƒm y táº¿', ['Chá»‰ tiÃªu', 'Sá»‘ lÆ°á»£ng / Tá»· lá»‡'], $rows, $filters);
    }

    public function healthInsuranceListReport(string $mode, array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        [$where, $params] = $this->citizenWhere($filters);
        $hasColumn = $this->columnExists('citizens', 'has_health_insurance');
        $endColumn = $this->columnExists('citizens', 'health_insurance_end_date');
        if ($mode === 'missing') $where .= ' AND ' . InsurancePolicy::missingConditionSql('c', $hasColumn);
        if ($mode === 'expired') $where .= ' AND ' . InsurancePolicy::expiredConditionSql('c', $hasColumn, $endColumn);
        if ($mode === 'expiring') $where .= ' AND ' . InsurancePolicy::expiringConditionSql('c', $hasColumn, $endColumn);
        $rows = $this->fetchAll("SELECT h.household_code, h.area_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.health_insurance_number, c.health_insurance_group, c.health_insurance_end_date, c.health_insurance_facility FROM citizens c INNER JOIN households h ON h.id=c.household_id $where ORDER BY h.household_code, c.full_name", $params);
        $title = [
            'missing' => 'Danh sÃ¡ch chÆ°a tham gia BHYT',
            'expired' => 'Danh sÃ¡ch BHYT Ä‘Ã£ háº¿t háº¡n',
            'expiring' => 'Danh sÃ¡ch BHYT sáº¯p háº¿t háº¡n 30 ngÃ y',
        ][$mode] ?? 'Danh sÃ¡ch BHYT';
        return $this->table($title, ['MÃ£ há»™','Khu vá»±c','MÃ£ nhÃ¢n kháº©u','Há» tÃªn','Giá»›i tÃ­nh','NgÃ y sinh','CCCD','Sá»‘ BHYT','NhÃ³m Ä‘á»‘i tÆ°á»£ng','Háº¿t háº¡n','NÆ¡i KCB'], array_map(fn($r) => [$r['household_code'], $r['area_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['health_insurance_number'], $r['health_insurance_group'], $this->date($r['health_insurance_end_date']), $r['health_insurance_facility']], $rows), $filters);
    }

    public function healthInsuranceHouseholdReport(array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        [$where, $params] = $this->citizenWhere($filters);
        $enrolled = InsurancePolicy::enrolledConditionSql('c');
        $missing = InsurancePolicy::missingConditionSql('c');
        $effective = InsurancePolicy::effectiveConditionSql('c');
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.area_code, COUNT(c.id) AS total, SUM($enrolled) AS enrolled, SUM($missing) AS missing, SUM($effective) AS effective FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY h.id, h.household_code, h.head_citizen_name, h.area_code ORDER BY h.household_code", $params);
        return $this->table('Thá»‘ng kÃª BHYT theo há»™', ['MÃ£ há»™','Chá»§ há»™','Khu vá»±c','Tá»•ng nhÃ¢n kháº©u','CÃ³ BHYT','CÃ²n hiá»‡u lá»±c','ChÆ°a tham gia','Tá»· lá»‡ bao phá»§'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['area_code'], (int) $r['total'], (int) $r['enrolled'], (int) $r['effective'], (int) $r['missing'], $this->percent((int) $r['effective'], max(1, (int) $r['total']))], $rows), $filters);
    }

    public function healthInsuranceAreaReport(array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        [$where, $params] = $this->citizenWhere($filters);
        $enrolled = InsurancePolicy::enrolledConditionSql('c');
        $missing = InsurancePolicy::missingConditionSql('c');
        $effective = InsurancePolicy::effectiveConditionSql('c');
        $rows = $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'ChÆ°a phÃ¢n khu') AS area, COUNT(c.id) AS total, SUM($enrolled) AS enrolled, SUM($missing) AS missing, SUM($effective) AS effective FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY area ORDER BY area", $params);
        return $this->table('Thá»‘ng kÃª BHYT theo khu vá»±c', ['Khu vá»±c','Tá»•ng nhÃ¢n kháº©u','CÃ³ BHYT','CÃ²n hiá»‡u lá»±c','ChÆ°a tham gia','Tá»· lá»‡ bao phá»§'], array_map(fn($r) => [$r['area'], (int) $r['total'], (int) $r['enrolled'], (int) $r['effective'], (int) $r['missing'], $this->percent((int) $r['effective'], max(1, (int) $r['total']))], $rows), $filters);
    }

    public function laborReport(array $filters = []): array
    {
        $columns = ['employed' => 'CÃ³ viá»‡c lÃ m', 'unemployed' => 'ChÆ°a cÃ³ viá»‡c lÃ m', 'not_attending_school' => 'ChÆ°a Ä‘i há»c', 'pupil' => 'Há»c sinh', 'student' => 'Sinh viÃªn', 'retired' => 'Nghá»‰ hÆ°u', 'other' => 'KhÃ¡c'];
        [$where, $params] = $this->citizenWhere($filters);
        $selects = ['c.occupation'];
        foreach (['employed','unemployed','not_attending_school','student','retired'] as $column) $selects[] = ($this->columnExists('citizens', $column) ? "c.$column" : '0') . " AS $column";
        $selects[] = 'CASE WHEN ' . StudentStatusService::studentSql('c') . ' THEN 1 ELSE 0 END AS pupil';
        $rows = $this->fetchAll('SELECT ' . implode(',', $selects) . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $where", $params);
        $groups = array_fill_keys(array_keys($columns), 0);
        foreach ($rows as $row) $groups[$this->laborGroup($row)]++;
        $total = max(1, count($rows));
        $body = [];
        foreach ($columns as $column => $label) $body[] = [$label, ((int) ($groups[$column] ?? 0)) . ' (' . $this->percent((int) ($groups[$column] ?? 0), $total) . ')'];
        return $this->table('BÃ¡o cÃ¡o Lao Ä‘á»™ng', ['NhÃ³m lao Ä‘á»™ng','Sá»‘ lÆ°á»£ng / Tá»· lá»‡'], $body, $filters);
    }
    public function ageRangeReport(string $title, ?int $from, ?int $to, array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        if ($from !== null) { $where .= ' AND ' . AgePolicy::ageSql('c') . ' >= :age_from_report'; $params['age_from_report'] = $from; }
        if ($to !== null) { $where .= ' AND ' . AgePolicy::ageSql('c') . ' <= :age_to_report'; $params['age_to_report'] = $to; }
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where ORDER BY c.date_of_birth, c.full_name", $params);
        return $this->table($title, ['MÃ£ há»™','MÃ£ nhÃ¢n kháº©u','Há» tÃªn','Giá»›i tÃ­nh','NgÃ y sinh','CCCD','Sá»‘ Ä‘iá»‡n thoáº¡i'], array_map(fn($r) => [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['phone']], $rows), $filters);
    }

    public function movementReport(array $filters = []): array { return $this->migrationReport($filters); }

    private function citizenListReport(string $title, array $filters): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.relationship, c.father_name, c.mother_name, c.residency_status, c.presence_status, c.life_status, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where ORDER BY h.household_code, CASE WHEN c.relationship='Chá»§ há»™' THEN 0 ELSE 1 END, c.full_name", $params);
        return $this->table($title, ['MÃ£ há»™','MÃ£ nhÃ¢n kháº©u','Há» tÃªn','Giá»›i tÃ­nh','NgÃ y sinh','CCCD','Quan há»‡','Há» tÃªn bá»‘','Há» tÃªn máº¹','CÆ° trÃº','Hiá»‡n táº¡i','Tráº¡ng thÃ¡i','Sá»‘ Ä‘iá»‡n thoáº¡i'], array_map(fn($r) => [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['relationship'], $r['father_name'] ?? '', $r['mother_name'] ?? '', $this->residency($r['residency_status']), $this->presence($r['presence_status']), $this->life($r['life_status']), $r['phone']], $rows), $filters);
    }

    private function movementDetailReport(string $title, array $types, array $filters): array
    {
        $where = ['m.status <> "DELETED"']; $params = [];
        if ($types) { $placeholders = []; foreach ($types as $index => $type) { $key = 'type_' . $index; $placeholders[] = ':' . $key; $params[$key] = $type; } $where[] = 'm.type IN (' . implode(',', $placeholders) . ')'; }
        $dateFrom = trim((string) ($filters['dateFrom'] ?? '')); $dateTo = trim((string) ($filters['dateTo'] ?? ''));
        if ($dateFrom) { $where[] = 'DATE(m.effective_date) >= :date_from'; $params['date_from'] = $dateFrom; }
        if ($dateTo) { $where[] = 'DATE(m.effective_date) <= :date_to'; $params['date_to'] = $dateTo; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $rows = $this->fetchAll("SELECT m.type, m.effective_date, m.from_address, m.to_address, m.reason, m.document_number, c.full_name, c.identity_number, c.citizen_code, h.household_code FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id $sqlWhere ORDER BY m.effective_date DESC, m.id DESC", $params);
        return $this->table($title, ['Loáº¡i','NgÃ y','MÃ£ há»™','MÃ£ nhÃ¢n kháº©u','Há» tÃªn','CCCD','Tá»« nÆ¡i','Äáº¿n nÆ¡i','LÃ½ do','Sá»‘ giáº¥y tá»'], array_map(fn($r) => [$this->movement($r['type']), $this->date($r['effective_date']), $r['household_code'], $r['citizen_code'], $r['full_name'], $r['identity_number'], $r['from_address'], $r['to_address'], $r['reason'], $r['document_number']], $rows), $filters);
    }

    private function householdWhere(array $filters): array
    {
        $where = [$this->activeHouseholdCondition('h')]; $params = [];
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(h.created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(h.created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['householdStatus'])) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        $category = $this->categoryKey($filters['household_type'] ?? $filters['householdType'] ?? $filters['category'] ?? '');
        if ($category) $this->addCategoryWhere($where, $params, $category);
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function citizenWhere(array $filters): array
    {
        $where = [$this->activeCitizenCondition('c'), $this->activeHouseholdCondition('h')]; $params = [];
        if (!empty($filters['householdStatus'])) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(c.created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(c.created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['residencyStatus'])) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if (!empty($filters['presenceStatus'])) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if (!empty($filters['lifeStatus'])) { $where[] = 'c.life_status = :life_status'; $params['life_status'] = $filters['lifeStatus']; }
        $category = $this->categoryKey($filters['household_type'] ?? $filters['householdType'] ?? $filters['category'] ?? '');
        if ($category) $this->addCategoryWhere($where, $params, $category);
        if (!empty($filters['gender'])) { $where[] = 'c.gender = :gender'; $params['gender'] = $filters['gender']; }
        if (!empty($filters['ageFrom'])) { $where[] = AgePolicy::ageSql('c') . ' >= :age_from'; $params['age_from'] = (int) $filters['ageFrom']; }
        if (!empty($filters['ageTo'])) { $where[] = AgePolicy::ageSql('c') . ' <= :age_to'; $params['age_to'] = (int) $filters['ageTo']; }
        if (!empty($filters['ethnicity'])) { $where[] = 'c.ethnicity LIKE :ethnicity'; $params['ethnicity'] = '%' . $filters['ethnicity'] . '%'; }
        if (!empty($filters['religion'])) { $where[] = 'c.religion LIKE :religion'; $params['religion'] = '%' . $filters['religion'] . '%'; }
        if (!empty($filters['occupation'])) { $where[] = 'c.occupation LIKE :occupation'; $params['occupation'] = '%' . $filters['occupation'] . '%'; }
        foreach (self::CITIZEN_FLAG_COLUMNS as $column) {
            if ($column === 'meritorious_person' && ($filters[$column] ?? null) !== null && $filters[$column] !== '') {
                $where[] = $this->meritoriousCitizenExpression('c', (int) $filters[$column] === 1);
            } elseif ($column === 'pupil' && ($filters[$column] ?? null) !== null && $filters[$column] !== '') {
                $where[] = ((int) $filters[$column] === 1 ? '' : 'NOT ') . StudentStatusService::studentSql('c');
            } elseif (($filters[$column] ?? null) !== null && $filters[$column] !== '' && $this->columnExists('citizens', $column)) {
                $where[] = 'c.' . $column . ' = :' . $column; $params[$column] = (int) $filters[$column];
            }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        $poor = '(h.poor_household = 1 OR ' . $this->activePovertyRecordExists('h', 'POOR') . ')';
        $nearPoor = '(h.near_poor_household = 1 OR ' . $this->activePovertyRecordExists('h', 'NEAR_POOR') . ')';
        $average = $this->activePovertyRecordExists('h', 'AVERAGE');
        $policy = '(' . $this->policySubjectHouseholdExists('h') . ' OR ' . $this->meritoriousHouseholdExists('h') . ' OR ' . $this->disabledHouseholdExists('h') . ')';
        match ($category) {
            'poor' => $where[] = $poor,
            'near_poor' => $where[] = $nearPoor,
            'average', 'medium' => $where[] = $average,
            'meritorious', 'policy' => $where[] = $policy,
            'normal' => $where[] = 'NOT ' . $poor . ' AND NOT ' . $nearPoor . ' AND NOT ' . $average . ' AND NOT ' . $policy,
            'other' => $where[] = $this->disabledHouseholdExists('h'),
            'escaped_poverty' => $this->addTextCategoryWhere($where, $params, $category),
            default => null,
        };
    }
    private function activeHouseholdCondition(string $alias): string
    {
        return $this->statistics()->householdCondition($alias);
    }

    private function activeCitizenCondition(string $alias): string
    {
        return $this->statistics()->citizenCondition($alias);
    }

    private function statistics(): PopulationStatistics
    {
        return $this->statistics ??= new PopulationStatistics();
    }
    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'Há»™ má»›i thoÃ¡t nghÃ¨o', 'policy' => 'Há»™ chÃ­nh sÃ¡ch'][$category] ?? $category;
        $where[] = '(h.note LIKE :category_label OR h.note LIKE :category_key)';
        $params['category_label'] = '%' . $label . '%';
        $params['category_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function categoryKey(mixed $value): string
    {
        $text = $this->normalize((string) $value);
        if ($text === '') return '';
        return match (true) {
            str_contains($text, 'can ngheo') || str_contains($text, 'near poor') => 'near_poor',
            str_contains($text, 'moi thoat ngheo') || str_contains($text, 'thoat ngheo') || str_contains($text, 'escaped poverty') => 'escaped_poverty',
            str_contains($text, 'trung binh') || str_contains($text, 'average') || str_contains($text, 'medium') => 'average',
            str_contains($text, 'chinh sach') || str_contains($text, 'policy') => 'policy',
            str_contains($text, 'co cong') || str_contains($text, 'gia dinh co cong') || str_contains($text, 'meritorious') => 'meritorious',
            str_contains($text, 'binh thuong') || str_contains($text, 'normal') || $text === 'khong' => 'normal',
            str_contains($text, 'khac') || str_contains($text, 'tan tat') || str_contains($text, 'khuyet tat') || str_contains($text, 'other') => 'other',
            str_contains($text, 'ngheo') || str_contains($text, 'poor') => 'poor',
            default => '',
        };
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function laborGroup(array $row): string
    {
        $occupation = $this->normalize((string) ($row['occupation'] ?? ''));
        if ((int) ($row['not_attending_school'] ?? 0) === 1 || str_contains($occupation, 'chua di hoc')) return 'not_attending_school';
        if ((int) ($row['pupil'] ?? 0) === 1) return 'pupil';
        if ((int) ($row['student'] ?? 0) === 1 || str_contains($occupation, 'sinh vien')) return 'student';
        if ((int) ($row['retired'] ?? 0) === 1 || str_contains($occupation, 'nghi huu') || str_contains($occupation, 'huu tri')) return 'retired';
        if ((int) ($row['unemployed'] ?? 0) === 1 || str_contains($occupation, 'that nghiep') || str_contains($occupation, 'chua co viec') || str_contains($occupation, 'khong co viec')) return 'unemployed';
        if ((int) ($row['employed'] ?? 0) === 1) return 'employed';
        if ($occupation === '' || str_contains($occupation, 'khac') || str_contains($occupation, 'noi tro')) return 'other';
        return 'employed';
    }

    private function ensureReportTemplatesTable(): void
    {
        $this->execute('CREATE TABLE IF NOT EXISTS report_templates (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(80) NOT NULL, filters_json JSON NULL, is_default TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT "ACTIVE", created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, INDEX idx_report_templates_user (user_id, status), INDEX idx_report_templates_default (user_id, is_default)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function flagSelects(string $alias): string
    {
        $parts = [];
        foreach (self::CITIZEN_FLAG_COLUMNS as $column) {
            if ($column === 'meritorious_person') {
                $parts[] = ', SUM(' . $this->meritoriousCitizenExpression($alias) . ") AS $column";
            } elseif ($column === 'pupil') {
                $parts[] = ', SUM(' . StudentStatusService::studentSql($alias) . ") AS $column";
            } else {
                $parts[] = ', ' . ($this->columnExists('citizens', $column) ? "SUM($alias.$column=1)" : '0') . " AS $column";
            }
        }
        return implode('', $parts);
    }

    private function meritoriousCitizenExpression(string $alias, bool $positive = true): string
    {
        $parts = [];
        foreach (self::MERITORIOUS_POLICY_COLUMNS as $column) {
            if ($this->columnExists('citizens', $column)) $parts[] = $alias . '.' . $column . '=1';
        }
        if (!$parts) return $positive ? '0=1' : '1=1';
        $expression = '(' . implode(' OR ', $parts) . ')';
        return $positive ? $expression : 'NOT ' . $expression;
    }

    private function meritoriousHouseholdExists(string $alias): string
    {
        $citizenPolicy = $this->meritoriousCitizenExpression('mhc');
        if ($citizenPolicy === '0=1') return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens mhc WHERE mhc.household_id=' . $alias . '.id AND ' . $this->activeCitizenCondition('mhc') . ' AND ' . $citizenPolicy . ')';
    }

    private function disabledHouseholdExists(string $alias): string
    {
        if (!$this->columnExists('citizens', 'disabled_person')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens dhc WHERE dhc.household_id=' . $alias . '.id AND ' . $this->activeCitizenCondition('dhc') . ' AND dhc.disabled_person=1)';
    }

    private function activePovertyTypeExpr(string $householdAlias): string
    {
        if (!$this->tableExists('household_poverty_records')) return 'NULL';
        return '(SELECT hpr.poverty_type FROM household_poverty_records hpr WHERE hpr.household_id=' . $householdAlias . '.id AND hpr.status="ACTIVE" AND hpr.deleted_at IS NULL AND ' . $this->tenantWhere('hpr', 'household_poverty_records') . ' ORDER BY hpr.effective_from DESC, hpr.id DESC LIMIT 1)';
    }
    private function activePovertyRecordExists(string $householdAlias, ?string $type = null): string
    {
        if (!$this->tableExists('household_poverty_records')) return '0=1';
        if ($type === 'AVERAGE') {
            $typeClause = ' AND hpr.poverty_type IN ("NONE","MEDIUM")';
        } else {
            $typeClause = $type !== null ? ' AND hpr.poverty_type="' . $type . '"' : '';
        }
        return 'EXISTS (SELECT 1 FROM household_poverty_records hpr WHERE hpr.household_id=' . $householdAlias . '.id AND hpr.status="ACTIVE" AND hpr.deleted_at IS NULL' . $typeClause . ' AND ' . $this->tenantWhere('hpr', 'household_poverty_records') . ' LIMIT 1)';
    }

    private function policySubjectHouseholdExists(string $householdAlias): string
    {
        if (!$this->tableExists('citizen_policy_records') || !$this->tableExists('policy_subject_types') || !$this->tableExists('citizens')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id=cpr.policy_type_id INNER JOIN citizens pc ON pc.id=cpr.citizen_id WHERE pc.household_id=' . $householdAlias . '.id AND cpr.status IN ("ACTIVE","PAUSED") AND cpr.deleted_at IS NULL AND pst.deleted_at IS NULL AND COALESCE(pst.is_active,1)=1 AND ' . $this->activeCitizenCondition('pc') . ' AND ' . $this->tenantWhere('cpr', 'citizen_policy_records') . ' AND ' . $this->tenantWhere('pst', 'policy_subject_types') . ' AND ' . $this->tenantWhere('pc', 'citizens') . ' LIMIT 1)';
    }
    private function countPercent(array $row, string $key, int $total): string { $count = (int) ($row[$key] ?? 0); return $count . ' (' . $this->percent($count, $total) . ')'; }
    private function healthInsuranceCoveredText(array $stats): string { return $stats['insured'] . '/' . $stats['total'] . ' nhÃ¢n kháº©u'; }
    private function percentValue(float|int $value): string { return number_format((float) $value, 2, '.', '') . '%'; }
    private function percent(int $count, int $total): string { return number_format($total > 0 ? ($count * 100 / $total) : 0, 2, ',', '.') . '%'; }

    private function table(string $title, array $headers, array $rows, array $filters): array { return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')]; }
    private function householdCategories(array $row): string
    {
        $labels = [];
        $activePovertyType = strtoupper((string) ($row['active_poverty_type'] ?? ''));
        if ($activePovertyType === 'POOR' || (int) ($row['poor_household'] ?? 0) === 1) $labels[] = 'Há»™ nghÃ¨o';
        if ($activePovertyType === 'NEAR_POOR' || (int) ($row['near_poor_household'] ?? 0) === 1) $labels[] = 'Há»™ cáº­n nghÃ¨o';
        if ($activePovertyType === 'NONE' || $activePovertyType === 'MEDIUM') $labels[] = 'Há»™ trung bÃ¬nh';
        if ((int) ($row['policy_subject_household'] ?? 0) === 1 || (int) ($row['meritorious_policy'] ?? 0) === 1 || (int) ($row['disabled_policy'] ?? 0) === 1) $labels[] = 'Há»™ chÃ­nh sÃ¡ch';
        $noteKey = $this->categoryKey((string) ($row['note'] ?? ''));
        if ($noteKey === 'escaped_poverty') $labels[] = 'Há»™ má»›i thoÃ¡t nghÃ¨o';
        return $labels ? implode(', ', array_values(array_unique($labels))) : 'Há»™ bÃ¬nh thÆ°á»ng';
    }
    private function date(?string $value): string { if (!$value) return ''; [$y, $m, $d] = explode('-', substr($value, 0, 10)); return "$d/$m/$y"; }
    private function residency(?string $value): string { return $value === 'TEMPORARY' ? 'Táº¡m trÃº' : 'ThÆ°á»ng trÃº'; }
    private function presence(?string $value): string { return $value === 'AWAY' ? 'Äi váº¯ng' : 'á»ž nhÃ '; }
    private function life(?string $value): string { return $value === 'DECEASED' ? 'ÄÃ£ cháº¿t' : 'CÃ²n sá»‘ng'; }
    private function movement(?string $value): string { return ['BIRTH' => 'Sinh', 'DEATH' => 'Tá»­', 'MOVE_IN' => 'Chuyá»ƒn Ä‘áº¿n', 'MOVE_OUT' => 'Chuyá»ƒn Ä‘i', 'TEMPORARY_RESIDENCE' => 'Táº¡m trÃº', 'TEMPORARY_ABSENCE' => 'Táº¡m váº¯ng', 'OTHER' => 'KhÃ¡c'][$value] ?? (string) $value; }

    public function center(): array
    {
        return [
            'groups' => [
                ['key' => 'population', 'title' => 'Bao cao dan cu', 'icon' => 'fa-users', 'description' => 'Nhan khau, gioi tinh, do tuoi, nghe nghiep, BHYT, Dang vien, Doan vien.', 'types' => ['population','health_insurance','health-insurance-missing','health-insurance-expiring','health-insurance-expired','health-insurance-household','health-insurance-area','children','elderly','labor','party_member','youth_union','gender','age']],
                ['key' => 'household', 'title' => 'Bao cao ho gia dinh', 'icon' => 'fa-house-chimney', 'description' => 'Danh sach ho, chu ho, khu vuc, ho ngheo va ho can ngheo.', 'types' => ['household','poor-households','near-poor-households','special']],
                ['key' => 'contributions', 'title' => 'Bao cao dong gop ho', 'icon' => 'fa-hand-holding-dollar', 'description' => 'Danh sach thu, ky nhan, mien giam, cong no va tong hop dong gop theo dot/nam.', 'types' => ['contributions-list','contributions-collection','contributions-unpaid-list','contributions-partial','contributions-exempt','contributions-summary','contributions-year-summary','contributions-by-contribution']],
                ['key' => 'household_business', 'title' => 'Bao cao ho san xuat va kinh doanh', 'icon' => 'fa-store', 'description' => 'Danh sach ho san xuat, ho kinh doanh, nganh nghe, trang thai va khu vuc GIS.', 'types' => ['household-business-production','household-business-trade','household-business-sector','household-business-status','household-business-gis','household-business-ocop','household-business-food-safety','household-business-social-insurance','household-business-economic-type','household-business-scale','household-business-product']],
                ['key' => 'agricultural_land', 'title' => 'Bao cao quy dat nong nghiep', 'icon' => 'fa-map', 'description' => 'Tong hop dien tich dat nong nghiep theo tung khu doc lap voi ho dan va san xuat.', 'types' => ['agricultural-land','agricultural-land-village','agricultural-land-zone','agricultural-land-year','agricultural-land-year-compare']],
                ['key' => 'agriculture', 'title' => 'Bao cao san xuat nong nghiep', 'icon' => 'fa-seedling', 'description' => 'Danh sach thua dat, chu the san xuat, dien tich, cay trong, mua vu, san luong va thiet hai.', 'types' => ['agriculture','agriculture-producers','agriculture-area','agriculture-crop','agriculture-season','agriculture-production','agriculture-revenue','agriculture-damage']],
                ['key' => 'livestock', 'title' => 'Bao cao vat nuoi', 'icon' => 'fa-paw', 'description' => 'Danh sach vat nuoi, thong ke theo loai, tiem phong va dich benh.', 'types' => ['livestock','livestock-by-type','livestock-vaccinated','livestock-unvaccinated','livestock-disease']],
                ['key' => 'party_members', 'title' => 'Bao cao Dang vien', 'icon' => 'fa-flag', 'description' => 'Danh sach Dang vien theo chi bo, do tuoi, gioi tinh, chuc vu, loai va tinh trang sinh hoat.', 'types' => ['party-members','party-members-branch','party-members-age','party-members-gender','party-members-position','party-members-official','party-members-probationary','party-members-status']],
                ['key' => 'vehicles', 'title' => 'Bao cao xe co', 'icon' => 'fa-car', 'description' => 'Danh sach phuong tien, phan loai, bien so, dang kiem va bao hiem.', 'types' => ['vehicles','vehicles-by-type','vehicles-missing-plate','vehicles-expired-inspection','vehicles-expired-insurance']],
                ['key' => 'houses', 'title' => 'Bao cao nha o va cong trinh', 'icon' => 'fa-building-user', 'description' => 'Danh sach nha o, nha xuong cap, PCCC, GPS va cong trinh phu.', 'types' => ['houses','houses-degraded','houses-temporary','houses-fire-risk','houses-missing-gps','houses-business','house-structures']],
                ['key' => 'public_assets', 'title' => 'Bao cao cong trinh cong cong', 'icon' => 'fa-building-columns', 'description' => 'Danh sach cong trinh cong cong, GPS, khu vuc, don vi quan ly va kiem ke tai san.', 'types' => ['public-assets','public-assets-located','public-assets-missing-gps','public-assets-inventory']],
                ['key' => 'movement', 'title' => 'Bao cao bien dong', 'icon' => 'fa-right-left', 'description' => 'Khai sinh, khai tu, chuyen di, chuyen den, tam tru, tam vang.', 'types' => ['migration','temporary_residence','temporary_absence','births','deaths']],
                ['key' => 'gis', 'title' => 'Bao cao GIS', 'icon' => 'fa-map-location-dot', 'description' => 'Ho da dinh vi, chua dinh vi, ty le hoan thanh GPS theo khu vuc va thoi gian.', 'types' => ['gis','gis-located','gis-unlocated']],
                ['key' => 'digital_profile', 'title' => 'Bao cao Ho so so', 'icon' => 'fa-folder-open', 'description' => 'Ho so hoan chinh, thieu anh, thieu giay to va chua hoan thien.', 'types' => ['digital-profile','profile-complete','profile-missing-photo','profile-missing-documents','profile-incomplete']],
                ['key' => 'operation', 'title' => 'Bao cao dieu hanh', 'icon' => 'fa-tower-broadcast', 'description' => 'Chi tieu nhanh phuc vu dieu hanh va theo doi tien do.', 'types' => ['summary']],
                ['key' => 'summary', 'title' => 'Bao cao tong hop', 'icon' => 'fa-chart-pie', 'description' => 'Tong hop toan he thong theo nhieu dieu kien loc.', 'types' => ['summary']],
            ],
            'templates' => [
                ['key' => 'household-form', 'title' => 'Phieu quan ly ho gia dinh', 'type' => 'household'],
                ['key' => 'household-list', 'title' => 'Danh sach ho', 'type' => 'household'],
                ['key' => 'citizen-list', 'title' => 'Danh sach nhan khau', 'type' => 'population'],
                ['key' => 'children-list', 'title' => 'Danh sach tre em', 'type' => 'children'],
                ['key' => 'elderly-list', 'title' => 'Danh sach nguoi cao tuoi', 'type' => 'elderly'],
                ['key' => 'labor-list', 'title' => 'Danh sach lao dong', 'type' => 'labor'],
                ['key' => 'health-insurance-summary', 'title' => 'Thá»‘ng kÃª Báº£o hiá»ƒm y táº¿', 'type' => 'health_insurance'],
                ['key' => 'party-list', 'title' => 'Danh sach Dang vien', 'type' => 'party_member'],
                ['key' => 'poor-list', 'title' => 'Danh sach ho ngheo', 'type' => 'poor-households'],
                ['key' => 'near-poor-list', 'title' => 'Danh sach ho can ngheo', 'type' => 'near-poor-households'],
                ['key' => 'contribution-household-list', 'title' => 'Danh sach ho dong gop', 'type' => 'contributions-list'],
                ['key' => 'contribution-collection-list', 'title' => 'Danh sach thu theo tung dot', 'type' => 'contributions-collection'],
                ['key' => 'contribution-signature-list', 'title' => 'Danh sach ky nhan dong gop', 'type' => 'contributions-signature'],
                ['key' => 'contribution-unpaid-list', 'title' => 'Danh sach ho chua hoan thanh nghia vu', 'type' => 'contributions-unpaid-list'],
                ['key' => 'contribution-exempt-list', 'title' => 'Danh sach ho duoc mien', 'type' => 'contributions-exempt'],
                ['key' => 'contribution-summary-campaign', 'title' => 'Bao cao tong hop cuoi dot', 'type' => 'contributions-summary'],
                ['key' => 'contribution-summary-year', 'title' => 'Bao cao tong hop theo nam', 'type' => 'contributions-year-summary'],
                ['key' => 'household-business-production', 'title' => 'Danh sach ho san xuat', 'type' => 'household-business-production'],
                ['key' => 'household-business-trade', 'title' => 'Danh sach ho kinh doanh', 'type' => 'household-business-trade'],
                ['key' => 'agricultural-land-list', 'title' => 'Danh sach khu dat nong nghiep', 'type' => 'agricultural-land'],
                ['key' => 'agricultural-land-village', 'title' => 'Bao cao quy dat toan thon', 'type' => 'agricultural-land-village'],
                ['key' => 'agricultural-land-zone', 'title' => 'Bao cao quy dat theo khu', 'type' => 'agricultural-land-zone'],
                ['key' => 'agricultural-land-year', 'title' => 'Bao cao quy dat theo nam', 'type' => 'agricultural-land-year'],
                ['key' => 'agricultural-land-year-compare', 'title' => 'So sanh quy dat giua cac nam', 'type' => 'agricultural-land-year-compare'],
                ['key' => 'agriculture-list', 'title' => 'Danh sach thua san xuat nong nghiep', 'type' => 'agriculture'],
                ['key' => 'agriculture-damage', 'title' => 'Bao cao thiet hai san xuat nong nghiep', 'type' => 'agriculture-damage'],
                ['key' => 'livestock-list', 'title' => 'Danh sach vat nuoi', 'type' => 'livestock'],
                ['key' => 'party-members-list', 'title' => 'Danh sach Dang vien', 'type' => 'party-members'],
                ['key' => 'livestock-disease', 'title' => 'Danh sach ho co dich benh vat nuoi', 'type' => 'livestock-disease'],
                ['key' => 'vehicles-list', 'title' => 'Danh sach phuong tien', 'type' => 'vehicles'],
                ['key' => 'vehicles-expired-inspection', 'title' => 'Phuong tien het han kiem dinh', 'type' => 'vehicles-expired-inspection'],
                ['key' => 'public-assets-list', 'title' => 'Danh sach cong trinh cong cong', 'type' => 'public-assets'],
                ['key' => 'public-assets-missing-gps', 'title' => 'Cong trinh chua co GPS', 'type' => 'public-assets-missing-gps'],
                ['key' => 'public-assets-inventory', 'title' => 'Kiem ke tai san cong trinh', 'type' => 'public-assets-inventory'],
                ['key' => 'temporary-residence-list', 'title' => 'Danh sach tam tru', 'type' => 'temporary_residence'],
                ['key' => 'temporary-absence-list', 'title' => 'Danh sach tam vang', 'type' => 'temporary_absence'],
            ],
            'filters' => ['dateFrom','dateTo','area','householdCode','headName','householdId','citizen','gender','ageFrom','ageTo','occupation','health_insurance','has_health_insurance','party_member','youth_union_member','category','residencyStatus','presenceStatus','gpsStatus','digitalProfileStatus'],
            'exports' => ['preview','print','pdf','excel','word'],
            'scheduler' => ['ready' => true, 'enabled' => false, 'message' => 'Da chuan bi cau truc lap lich, chua bat gui tu dong.'],
        ];
    }

    public function biDashboard(array $filters = []): array
    {
        $dashboard = new \App\Models\Dashboard();
        $operation = new \App\Models\OperationCenter();
        $summary = $dashboard->summary($filters);
        $progress = $operation->progress($filters)['data']['items'] ?? [];
        return [
            'metrics' => $summary['metrics'] ?? [],
            'charts' => [
                'population' => $summary['charts']['population'] ?? [],
                'age' => $summary['charts']['ages'] ?? [],
                'gender' => $summary['charts']['population'] ?? [],
                'occupation' => $summary['charts']['occupations'] ?? [],
                'partyMembers' => $summary['charts']['partyMembers'] ?? [],
                'labor' => $summary['charts']['labor'] ?? [],
                'poverty' => $summary['charts']['poverty'] ?? [],
                'gpsProgress' => $summary['charts']['gpsProgress'] ?? [],
                'profileProgress' => $summary['charts']['profileProgress'] ?? [],
                'healthInsurance' => $summary['charts']['healthInsurance'] ?? [],
                'monthlyMovements' => $summary['charts']['monthlyChanges'] ?? [],
            ],
            'progress' => $progress,
            'filters' => $filters,
            'generatedAt' => date('c'),
        ];
    }

    public function gisReport(array $filters = [], string $mode = 'all'): array
    {
        $filters['gpsStatus'] = $mode === 'located' ? 'located' : ($mode === 'unlocated' ? 'missing' : ($filters['gpsStatus'] ?? null));
        [$where, $params] = $this->householdWhere($filters);
        $lat = $this->columnExists('households', 'latitude') ? 'h.latitude' : 'NULL';
        $lng = $this->columnExists('households', 'longitude') ? 'h.longitude' : 'NULL';
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.area_code, $lat AS latitude, $lng AS longitude, h.location_updated_at FROM households h $where ORDER BY h.area_code, h.household_code", $params);
        $body = array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['area_code'], $r['latitude'], $r['longitude'], $this->date($r['location_updated_at'] ?? '')], $rows);
        $title = $mode === 'located' ? 'Bao cao ho da dinh vi GPS' : ($mode === 'unlocated' ? 'Bao cao ho chua dinh vi GPS' : 'Bao cao GIS ho gia dinh');
        return $this->table($title, ['Ma ho','Chu ho','Dia chi','Khu vuc','Vi do','Kinh do','Ngay cap nhat GPS'], $body, $filters);
    }

    public function digitalProfileReport(array $filters = [], string $mode = 'all'): array
    {
        $filters['digitalProfileStatus'] = $mode === 'complete' ? 'complete' : ($mode === 'incomplete' ? 'incomplete' : ($filters['digitalProfileStatus'] ?? null));
        [$where, $params] = $this->householdWhere($filters);
        $hasFiles = $this->tableExists('file_attachments');
        $fileModuleWhere = 'f.module=\'household\'';
        if ($hasFiles && $this->columnExists('file_attachments', 'entity_type')) $fileModuleWhere = '(' . $fileModuleWhere . ' OR f.entity_type=\'household\')';
        $fileStatusWhere = $hasFiles && $this->columnExists('file_attachments', 'status') ? ' AND f.status=\'ACTIVE\'' : '';
        $photoParts = [];
        if ($hasFiles && $this->columnExists('file_attachments', 'file_type')) $photoParts[] = "f.file_type IN ('PHOTO','image','image/jpeg','image/png')";
        if ($hasFiles && $this->columnExists('file_attachments', 'mime_type')) $photoParts[] = "f.mime_type LIKE 'image/%'";
        if ($hasFiles && $this->columnExists('file_attachments', 'profile_section')) $photoParts[] = "f.profile_section LIKE '%photo%'";
        $photoWhere = $photoParts ? ' AND (' . implode(' OR ', $photoParts) . ')' : '';
        $photoSql = $hasFiles ? "(SELECT COUNT(*) FROM file_attachments f WHERE f.entity_id=h.id AND $fileModuleWhere$fileStatusWhere$photoWhere)" : '0';
        $docSql = $hasFiles ? "(SELECT COUNT(*) FROM file_attachments f WHERE f.entity_id=h.id AND $fileModuleWhere$fileStatusWhere)" : '0';
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.area_code, $photoSql AS photo_count, $docSql AS document_count FROM households h $where ORDER BY h.household_code", $params);
        if ($mode === 'complete') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['photo_count'] ?? 0) > 0 && (int) ($r['document_count'] ?? 0) > 0));
        if ($mode === 'incomplete') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['photo_count'] ?? 0) === 0 || (int) ($r['document_count'] ?? 0) === 0));
        if ($mode === 'missing_photo') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['photo_count'] ?? 0) === 0));
        if ($mode === 'missing_documents') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['document_count'] ?? 0) === 0));
        $title = ['complete' => 'Bao cao ho so so hoan chinh', 'missing_photo' => 'Bao cao ho so thieu anh', 'missing_documents' => 'Bao cao ho so thieu giay to', 'incomplete' => 'Bao cao ho so chua hoan thien'][$mode] ?? 'Bao cao Ho so so';
        return $this->table($title, ['Ma ho','Chu ho','Dia chi','Khu vuc','So anh','So giay to','Trang thai'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['area_code'], (int) $r['photo_count'], (int) $r['document_count'], ((int) $r['photo_count'] > 0 && (int) $r['document_count'] > 0) ? 'Hoan chinh' : 'Chua hoan thien'], $rows), $filters);
    }

    public function templates(int $userId): array
    {
        $this->ensureReportTemplatesTable();
        return $this->fetchAll('SELECT id, name, type, filters_json, is_default, created_at, updated_at FROM report_templates WHERE user_id=:user_id AND status="ACTIVE" ORDER BY is_default DESC, updated_at DESC, id DESC', ['user_id' => $userId]);
    }

    public function saveTemplate(int $userId, array $input): array
    {
        $this->ensureReportTemplatesTable();
        $name = trim((string) ($input['name'] ?? '')) ?: 'Mau bao cao';
        $type = trim((string) ($input['type'] ?? 'summary')) ?: 'summary';
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
        $isDefault = !empty($input['isDefault']) ? 1 : 0;
        if ($isDefault) $this->execute('UPDATE report_templates SET is_default=0 WHERE user_id=:user_id', ['user_id' => $userId]);
        $id = $this->insert('INSERT INTO report_templates (user_id, name, type, filters_json, is_default, status, created_at, updated_at) VALUES (:user_id,:name,:type,:filters,:is_default,"ACTIVE",NOW(),NOW())', ['user_id' => $userId, 'name' => $name, 'type' => $type, 'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'is_default' => $isDefault]);
        return $this->fetchOne('SELECT id, name, type, filters_json, is_default, created_at, updated_at FROM report_templates WHERE id=:id', ['id' => $id]) ?: ['id' => $id, 'name' => $name, 'type' => $type, 'filters_json' => json_encode($filters), 'is_default' => $isDefault];
    }

    public function deleteTemplate(int $userId, int $id): void
    {
        $this->ensureReportTemplatesTable();
        $this->execute('UPDATE report_templates SET status="DELETED", updated_at=NOW() WHERE id=:id AND user_id=:user_id', ['id' => $id, 'user_id' => $userId]);
    }

    public function setDefaultTemplate(int $userId, int $id): void
    {
        $this->ensureReportTemplatesTable();
        $this->execute('UPDATE report_templates SET is_default=0 WHERE user_id=:user_id', ['user_id' => $userId]);
        $this->execute('UPDATE report_templates SET is_default=1, updated_at=NOW() WHERE id=:id AND user_id=:user_id AND status="ACTIVE"', ['id' => $id, 'user_id' => $userId]);
    }

}
