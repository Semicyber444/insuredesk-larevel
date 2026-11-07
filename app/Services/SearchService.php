<?php

namespace App\Services;

use App\Models\CoverageType;
use App\Models\EligibilityRule;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\PriceOption;
use App\Models\SourceDocument;
use App\Models\VersionCoverage;
use App\Support\Format;

/**
 * Search and comparison over published price options (Sections 10 and 11).
 * Ported from src/lib/queries/{search,compare,coverage}.ts. Items are plain arrays with the same
 * camelCase keys as the old InsuranceSearchResultItem.
 * shortcut: loads every published option into memory per request; fine for a team's few hundred
 * tiers, move the filters into SQL if the catalogue grows into the tens of thousands.
 */
class SearchService
{
    /** Every coverage type for a plan version, 'unknown' where the version doesn't set it. */
    public static function resolveCoverages(string $versionId, $types, $coveragesByVersion): array
    {
        $set = collect($coveragesByVersion[$versionId] ?? [])->keyBy('coverage_type_id');

        return $types->map(function (CoverageType $type) use ($set) {
            $base = $set[$type->id] ?? null;

            return [
                'coverageTypeId' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'category' => $type->category,
                'status' => $base?->status ?? 'unknown',
                'limitAmount' => $base?->limit_amount,
                'limitBasis' => $base?->limit_basis,
                'deductibleAmount' => $base?->deductible_amount,
                'terms' => $base?->terms,
                'exclusions' => $base?->exclusions,
            ];
        })->values()->all();
    }

    /** Price options of each plan's latest published version, for plans and insurers that are active. */
    public static function buildItems(): array
    {
        $versions = PlanVersion::where('workflow_status', 'published')
            ->whereHas('plan', fn ($q) => $q->where('is_archived', false)
                ->whereHas('insurer', fn ($i) => $i->where('is_active', true)))
            ->orderByDesc('version_no')->get()
            // Section 12.3: only the latest published version of a plan counts.
            ->unique('plan_id')->keyBy('id');
        if ($versions->isEmpty()) {
            return [];
        }

        $plans = Plan::with('insurer')->whereIn('id', $versions->pluck('plan_id'))->get()->keyBy('id');
        $rules = EligibilityRule::with('vehicleModel')->whereIn('plan_version_id', $versions->keys())
            ->orderBy('created_at')->orderBy('id')->get()->keyBy('id');
        $options = PriceOption::whereIn('eligibility_rule_id', $rules->keys())
            ->orderBy('eligibility_rule_id')->orderBy('sort_order')->orderBy('id')->get();
        $types = CoverageType::orderBy('sort_order')->orderBy('id')->get();
        $coverages = VersionCoverage::whereIn('plan_version_id', $versions->keys())->get()->groupBy('plan_version_id');
        $documents = SourceDocument::whereIn('plan_version_id', $versions->keys())
            ->orderByDesc('created_at')->orderBy('id')->get()->groupBy('plan_version_id');

        $items = [];
        foreach ($options as $option) {
            $rule = $rules[$option->eligibility_rule_id];
            $version = $versions[$rule->plan_version_id];
            $plan = $plans[$version->plan_id];
            $insurer = $plan->insurer;
            $vehicle = $rule->vehicleModel;

            $items[] = [
                'id' => $option->id,
                'priceOptionLabel' => $option->label,
                'repairType' => $option->repair_type,
                'priceType' => $option->price_type,
                'premiumTotal' => $option->premium_total,
                'netPremium' => $option->net_premium,
                'taxStamp' => $option->tax_stamp,
                'compulsoryIncluded' => $option->compulsory_included,
                'sumInsuredMin' => $option->sum_insured_min,
                'sumInsuredMax' => $option->sum_insured_max,
                'deductibleAmount' => $option->deductible_amount,
                'deductibleTerms' => $option->deductible_terms,
                'driverConditions' => $option->driver_conditions,
                'priceValidFrom' => $option->valid_from,
                'priceValidTo' => $option->valid_to,

                'planId' => $plan->id,
                'planName' => $plan->name,
                'insuranceType' => $plan->insurance_type,

                'insurerId' => $insurer->id,
                'insurerName' => $insurer->name,
                'insurerWebsite' => $insurer->website,

                'planVersionId' => $version->id,
                'versionNo' => $version->version_no,
                'workflowStatus' => $version->workflow_status,
                'versionEffectiveFrom' => $version->effective_from,
                'versionEffectiveTo' => $version->effective_to,
                'verifiedAt' => $version->verified_at,
                'reviewDueAt' => $version->review_due_at,

                'eligibilityRuleId' => $rule->id,
                'scopeType' => $rule->scope_type,
                'vehicleModelId' => $rule->vehicle_model_id,
                'vehicleBrand' => $vehicle?->brand,
                'vehicleModel' => $vehicle?->model,
                'vehicleVariant' => $vehicle?->variant,
                'minCarAge' => $rule->min_age,
                'maxCarAge' => $rule->max_age,
                'ageBasis' => $rule->age_basis,
                'usageType' => $rule->usage_type,
                'registrationZone' => $rule->registration_zone ?? 'all',
                'inspectionRequired' => $rule->inspection_required,

                'coverages' => self::resolveCoverages($version->id, $types, $coverages),
                'documents' => ($documents[$version->id] ?? collect())->map->toArray()->values()->all(),
            ];
        }

        return $items;
    }

    /**
     * @param  array  $f  keys as in the old SearchFilters: query, insurerIds, insuranceTypes, repairTypes,
     *                    vehicleBrand, vehicleModelId, carAge, usageType, registrationPlate, minPremium, maxPremium,
     *                    minSumInsured, maxSumInsured, includeUnknownPrice, requiredCoverageCodes, sortBy, page, pageSize
     */
    public static function search(array $f = [], ?array $items = null): array
    {
        $today = today()->toDateString();
        $set = fn ($key) => isset($f[$key]) && $f[$key] !== '' && $f[$key] !== [];

        $filtered = array_values(array_filter($items ?? self::buildItems(), function ($item) use ($f, $set, $today) {
            // 1. Text search: insurer, plan, option label, vehicle and plate zone
            if ($set('query') && trim($f['query']) !== '') {
                $q = mb_strtolower(trim($f['query']));
                $haystacks = [
                    $item['insurerName'], $item['planName'], $item['priceOptionLabel'],
                    implode(' ', array_filter([$item['vehicleBrand'], $item['vehicleModel'], $item['vehicleVariant'],
                        Format::registrationZone($item['registrationZone'])])),
                ];
                if (! collect($haystacks)->contains(fn ($h) => str_contains(mb_strtolower($h), $q))) {
                    return false;
                }
            }

            // 2-4. Multi-select filters (OR within each)
            foreach (['insurerIds' => 'insurerId', 'insuranceTypes' => 'insuranceType', 'repairTypes' => 'repairType'] as $key => $field) {
                if ($set($key) && ! in_array($item[$field], $f[$key], true)) {
                    return false;
                }
            }

            // 5. Vehicle (Section 10.2: scope 'unknown' must NOT match as all_models)
            if ($set('vehicleBrand') && ($item['scopeType'] === 'unknown'
                || ($item['scopeType'] === 'specific_model' && $item['vehicleBrand'] !== $f['vehicleBrand']))) {
                return false;
            }
            if ($set('vehicleModelId') && ($item['scopeType'] === 'unknown'
                || ($item['scopeType'] === 'specific_model' && $item['vehicleModelId'] !== $f['vehicleModelId']))) {
                return false;
            }
            if ($set('carAge')) {
                $age = (float) $f['carAge'];
                if ($age < ($item['minCarAge'] ?? 0) || $age > ($item['maxCarAge'] ?? 999)) {
                    return false;
                }
            }

            // 6. Usage and plate zone
            if ($set('usageType') && $f['usageType'] !== 'all' && $item['usageType']
                && $item['usageType'] !== 'all' && $item['usageType'] !== $f['usageType']) {
                return false;
            }
            if ($set('registrationPlate')) {
                $zone = $item['registrationZone'] ?? 'all';
                $blocked = match ($f['registrationPlate']) {
                    'bangkok_only_strict' => $zone !== 'bangkok_only',
                    'bangkok' => $zone === 'upcountry_only',
                    'metro' => in_array($zone, ['bangkok_only', 'upcountry_only'], true),
                    'upcountry' => in_array($zone, ['bangkok_only', 'bangkok_metro'], true),
                    default => false,
                };
                if ($blocked) {
                    return false;
                }
            }

            // 7. Premium range (Section 10.2: an unknown price is excluded unless asked for)
            if ($item['premiumTotal'] === null) {
                if (empty($f['includeUnknownPrice']) && ($set('minPremium') || $set('maxPremium'))) {
                    return false;
                }
            } else {
                if ($set('minPremium') && $item['premiumTotal'] < $f['minPremium']) {
                    return false;
                }
                if ($set('maxPremium') && $item['premiumTotal'] > $f['maxPremium']) {
                    return false;
                }
            }

            // 8. Sum insured range (never treat an unknown sum insured as 0)
            if ($set('minSumInsured') || $set('maxSumInsured')) {
                if ($item['sumInsuredMax'] === null && $item['sumInsuredMin'] === null) {
                    return false;
                }
                $sumMax = $item['sumInsuredMax'] ?? $item['sumInsuredMin'];
                $sumMin = $item['sumInsuredMin'] ?? $item['sumInsuredMax'];
                if ($set('minSumInsured') && $sumMax < $f['minSumInsured']) {
                    return false;
                }
                if ($set('maxSumInsured') && $sumMin > $f['maxSumInsured']) {
                    return false;
                }
            }

            // 9. Required coverages (Section 10.2: all must be 'covered', not optional)
            if ($set('requiredCoverageCodes')) {
                $byCode = array_column($item['coverages'], 'status', 'code');
                foreach ($f['requiredCoverageCodes'] as $code) {
                    if (($byCode[$code] ?? null) !== 'covered') {
                        return false;
                    }
                }
            }

            // 10. Active date window (Asia/Bangkok dates)
            return ! (($item['priceValidTo'] && $item['priceValidTo'] < $today)
                || ($item['versionEffectiveTo'] && $item['versionEffectiveTo'] < $today)
                || ($item['priceValidFrom'] && $item['priceValidFrom'] > $today)
                || ($item['versionEffectiveFrom'] && $item['versionEffectiveFrom'] > $today));
        }));

        $collator = new \Collator('th_TH');
        usort($filtered, fn ($a, $b) => match ($f['sortBy'] ?? null) {
            'premium_asc' => ($a['premiumTotal'] ?? 999999999) <=> ($b['premiumTotal'] ?? 999999999),
            'premium_desc' => ($b['premiumTotal'] ?? 0) <=> ($a['premiumTotal'] ?? 0),
            'sum_insured_desc' => ($b['sumInsuredMax'] ?? 0) <=> ($a['sumInsuredMax'] ?? 0),
            default => $collator->compare($a['insurerName'], $b['insurerName']),
        });

        $page = max(1, (int) ($f['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($f['pageSize'] ?? 20)));
        $total = count($filtered);

        return [
            'items' => array_slice($filtered, ($page - 1) * $pageSize, $pageSize),
            'totalCount' => $total,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / $pageSize)),
            'pageSize' => $pageSize,
        ];
    }

    public static function comparisonWarnings(array $options): array
    {
        $warnings = [];
        if (count($options) < 2) {
            return $warnings;
        }
        $today = today()->toDateString();
        $warn = function ($type, $title, $message, $severity) use (&$warnings) {
            $warnings[] = compact('type', 'title', 'message', 'severity');
        };
        $nullSumMessage = 'มีทั้งตัวเลือกที่ระบุทุนประกันชัดเจนและตัวเลือกที่ยังไม่ระบุทุนประกัน กรุณาตรวจสอบตารางกรมธรรม์ก่อนตัดสินใจ';

        // 1. Sum insured (never map an unknown sum insured to 0)
        $knownSums = array_values(array_filter(array_map(fn ($o) => $o['sumInsuredMax'] ?? $o['sumInsuredMin'], $options),
            fn ($v) => $v !== null));
        $hasNullSum = count($knownSums) < count($options);
        if (count($knownSums) >= 2 && min($knownSums) != max($knownSums)) {
            $warn('sum_insured_diff', 'ทุนประกันภัยไม่เท่ากัน',
                'ตัวเลือกที่เปรียบเทียบมีทุนประกันต่างกัน (ต่ำสุด ฿'.Format::number(min($knownSums)).' ถึง สูงสุด ฿'.Format::number(max($knownSums)).')'
                .($hasNullSum ? ' และมีบางรายการที่ยังไม่ระบุทุนประกัน' : '').' กรุณาคำนึงถึงมูลค่ารถยนต์ขณะเปรียบเทียบเบี้ย', 'warning');
        } elseif ($hasNullSum && count($knownSums) >= 1) {
            $warn('sum_insured_diff', 'บางรายการยังไม่ระบุทุนประกันภัย', $nullSumMessage, 'warning');
        }

        $distinct = fn (callable $fn) => count(array_unique(array_map(fn ($o) => (string) $fn($o), $options)));

        // 2-4. Repair type, deductible, price type
        if ($distinct(fn ($o) => $o['repairType']) > 1) {
            $warn('repair_type_diff', 'เงื่อนไขการซ่อมต่างกัน (ซ่อมห้าง / ซ่อมอู่)',
                'มีทั้งตัวเลือกซ่อมห้างและซ่อมอู่ผสมกัน เบี้ยซ่อมห้างตามมาตรฐานจะสูงกว่าซ่อมอู่', 'warning');
        }
        if ($distinct(fn ($o) => $o['deductibleAmount']) > 1) {
            $warn('deductible_diff', 'ค่าเสียหายส่วนแรก (Deductible) ไม่เท่ากัน',
                'บางตัวเลือกมีเงื่อนไขให้ผู้เอาประกันร่วมจ่ายค่าเสียหายส่วนแรกเมื่อเกิดเหตุที่เป็นฝ่ายผิด ทำให้เบี้ยประกันถูกลง', 'caution');
        }
        if ($distinct(fn ($o) => $o['priceType']) > 1) {
            $warn('price_type_diff', 'ชนิดของราคาไม่เหมือนกัน',
                'ตัวเลือกบางรายการเป็นราคาเริ่มต้นหรือราคาประมาณการ ไม่ใช่ราคาตายตัว', 'info');
        }

        // 5. Driver and vehicle eligibility
        $hasSpecificDriver = collect($options)->contains(fn ($o) => str_contains((string) $o['driverConditions'], 'ระบุผู้ขับขี่'));
        $driverDiff = $distinct(fn ($o) => trim($o['driverConditions'] ?: 'ไม่จำกัดชื่อผู้ขับขี่')) > 1;
        $scopeDiff = $distinct(fn ($o) => "{$o['scopeType']}:{$o['vehicleModelId']}:{$o['minCarAge']}-{$o['maxCarAge']}:{$o['usageType']}") > 1;
        if ($hasSpecificDriver || $driverDiff || $scopeDiff) {
            $warn('driver_diff',
                $hasSpecificDriver ? 'มีเงื่อนไขระบุผู้ขับขี่เฉพาะเจาะจง' : 'เงื่อนไขผู้ขับขี่หรือคุณสมบัติรถที่รับประกันต่างกัน',
                $hasSpecificDriver
                    ? 'บางแพ็กเกจกำหนดอายุหรือชื่อผู้ขับขี่ หากผู้อื่นนำรถไปเกิดเหตุอาจมีเงื่อนไขค่าเสียหายส่วนแรกเพิ่ม'
                    : 'ตัวเลือกที่นำมาเทียบมีเงื่อนไขอายุรถ รุ่นรถ หรือเงื่อนไขผู้ขับขี่แตกต่างกัน กรุณาตรวจสอบว่ารถลูกค้าเข้าเกณฑ์ทุกตัวเลือก',
                'warning');
        }

        // 5b. Plate zone
        $zones = array_values(array_unique(array_map(fn ($o) => $o['registrationZone'] ?? 'all', $options)));
        if ($zones !== ['all']) {
            $warn('registration_zone_diff', 'มีเงื่อนไขจำกัดเขตป้ายทะเบียนรถยนต์',
                'แพ็กเกจที่เปรียบเทียบมีเงื่อนไขป้ายทะเบียน: '.implode(' / ', array_map([Format::class, 'registrationZone'], $zones))
                .' กรุณาตรวจสอบป้ายทะเบียนรถของลูกค้าก่อนเสนอขาย', 'warning');
        }

        // 6. Expired price or version
        if (collect($options)->contains(fn ($o) => ($o['priceValidTo'] && $o['priceValidTo'] < $today)
            || ($o['versionEffectiveTo'] && $o['versionEffectiveTo'] < $today))) {
            $warn('expired_price', 'พบตัวเลือกราคาที่หมดอายุการบังคับใช้',
                'มีตัวเลือกที่พ้นกำหนดวันสิ้นสุดอัตราเบี้ยแล้ว กรุณาตรวจสอบอัตราเบี้ยรุ่นล่าสุดกับบริษัทประกันภัยก่อนเสนอราคาจริง', 'warning');
        }

        // 7. Unknown data (Sections 9.3-9.5)
        $hasUnknownPrices = collect($options)->contains(fn ($o) => $o['premiumTotal'] === null);
        $hasUnknownCoverages = collect($options)->contains(fn ($o) => collect($o['coverages'])->contains('status', 'unknown'));
        if ($hasUnknownPrices || $hasNullSum || $hasUnknownCoverages) {
            $warn('unknown_data', 'มีข้อมูลสำคัญที่ยังไม่ทราบครบถ้วน', $hasUnknownPrices
                ? 'บางรายการไม่มีข้อมูลราคาที่แน่นอนในเอกสารต้นทาง ระบบแสดง "ยังไม่มีข้อมูลราคา" และไม่สามารถตัดสินความคุ้มค่าได้ทันที'
                : 'บางรายการยังไม่ระบุทุนประกันหรือมีหมวดความคุ้มครองที่ "ยังไม่ระบุ" ในเอกสารต้นทาง กรุณาตรวจสอบเอกสารแนบเพิ่มเติม',
                'caution');
        }

        return $warnings;
    }

    /** The selected options in the order given, their warnings, and a coverage table grouped by category. */
    public static function comparison(array $optionIds): array
    {
        $all = collect(self::buildItems())->keyBy('id');
        $options = collect($optionIds)->map(fn ($id) => $all[$id] ?? null)->filter()->values()->all();

        $categories = [];
        foreach ($options as $opt) {
            foreach ($opt['coverages'] as $cov) {
                $categories[$cov['category']][$cov['code']] = $cov['name'];
            }
        }

        $table = [];
        foreach ($categories as $category => $items) {
            $rows = [];
            foreach ($items as $code => $name) {
                $values = [];
                foreach ($options as $opt) {
                    $cov = collect($opt['coverages'])->firstWhere('code', $code);
                    $values[$opt['id']] = ['status' => $cov['status'] ?? 'unknown', 'text' => self::coverageText($cov)];
                }
                $rows[] = [
                    'code' => $code,
                    'name' => $name,
                    'values' => $values,
                    'hasDifference' => count(array_unique(array_column($values, 'text'))) > 1,
                ];
            }
            $table[] = ['name' => $category, 'items' => $rows];
        }

        return ['options' => $options, 'warnings' => self::comparisonWarnings($options), 'categories' => $table];
    }

    private static function coverageText(?array $cov): string
    {
        return match ($cov['status'] ?? 'unknown') {
            'not_covered' => 'ไม่คุ้มครอง',
            // Section 9.4: never guess unlimited or sum insured when the limit is omitted
            'covered' => $cov['limitAmount']
                ? '฿'.Format::number($cov['limitAmount']).($cov['limitBasis'] ? " ({$cov['limitBasis']})" : '')
                : ($cov['limitBasis'] ?: 'คุ้มครอง (ยังไม่ระบุวงเงิน)'),
            'optional' => 'ซื้อเพิ่มได้'.($cov['terms'] ? " ({$cov['terms']})" : ''),
            default => 'ยังไม่ระบุ',
        };
    }
}
