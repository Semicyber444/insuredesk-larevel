<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CoverageType;
use App\Models\Plan;
use App\Models\SourceDocument;
use App\Models\VersionCoverage;
use App\Services\InsuranceStore;
use App\Services\SearchService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Ported from the Next.js app's tests/unit/search_and_compare.test.ts. */
class SearchAndCompareTest extends TestCase
{
    private function plan(array $payload): array
    {
        return InsuranceStore::createOrUpdatePlan($payload + ['insurerId' => self::INSURER_ID], null);
    }

    private function search(array $filters = []): array
    {
        return SearchService::search($filters)['items'];
    }

    // 1. Search and filtering (Section 10)

    public function test_searches_by_thai_substring(): void
    {
        foreach (['วิริยะ', 'กรุงเทพ'] as $q) {
            $items = $this->search(['query' => $q]);
            $this->assertNotEmpty($items);
            foreach ($items as $i) {
                $this->assertStringContainsString($q, $i['insurerName']);
            }
        }
    }

    public function test_filters_by_insurance_and_repair_type(): void
    {
        $items = $this->search(['insuranceTypes' => ['ชั้น 1']]);
        $this->assertNotEmpty($items);
        $this->assertSame(['ชั้น 1'], array_values(array_unique(array_column($items, 'insuranceType'))));

        foreach (['dealer', 'garage'] as $repair) {
            $items = $this->search(['repairTypes' => [$repair]]);
            $this->assertNotEmpty($items);
            $this->assertSame([$repair], array_values(array_unique(array_column($items, 'repairType'))));
        }
    }

    public function test_required_coverage_must_be_covered(): void
    {
        foreach (['flood', 'rollover_offroad'] as $code) {
            $items = $this->search(['requiredCoverageCodes' => [$code]]);
            $this->assertNotEmpty($items);
            foreach ($items as $item) {
                $this->assertSame('covered', collect($item['coverages'])->firstWhere('code', $code)['status']);
            }
        }
    }

    public function test_filters_by_car_age(): void
    {
        foreach ($this->search(['carAge' => 12]) as $item) {
            $this->assertLessThanOrEqual(12, $item['minCarAge'] ?? 0);
            $this->assertGreaterThanOrEqual(12, $item['maxCarAge'] ?? 99);
        }
    }

    public function test_unknown_scope_never_matches_a_vehicle_filter(): void
    {
        $name = 'แผนทดสอบไม่ระบุขอบเขตรถ';
        $this->plan(['name' => $name, 'insuranceType' => 'ชั้น 1', 'workflowStatus' => 'published', 'scopeType' => 'unknown',
            'documentTitle' => 'เอกสาร', 'priceOptions' => [['repairType' => 'garage', 'premiumTotal' => 9900, 'sumInsuredMax' => 300000]]]);

        $this->assertCount(1, $this->search(['query' => $name]));
        $this->assertCount(0, $this->search(['query' => $name, 'vehicleBrand' => 'Toyota']));
        $this->assertCount(0, $this->search(['query' => $name, 'vehicleModelId' => '30000000-0000-0000-0000-000000000001']));
    }

    public function test_resolves_every_coverage_type_for_a_version(): void
    {
        $types = CoverageType::orderBy('sort_order')->get();
        $coverages = SearchService::resolveCoverages('50000000-0000-0000-0000-000000000001', $types,
            VersionCoverage::all()->groupBy('plan_version_id'));
        $this->assertCount(16, $coverages);
        $rollover = collect($coverages)->firstWhere('code', 'rollover_offroad');
        $this->assertSame(['รถพลิกคว่ำหรือตกทาง', 'ตัวรถยนต์', 'covered'],
            [$rollover['name'], $rollover['category'], $rollover['status']]);
    }

    public function test_filters_by_plate_zone_and_warns_on_zone_difference(): void
    {
        $name = 'แผนพิเศษเฉพาะรถป้าย กทม.';
        $this->plan(['name' => $name, 'insuranceType' => 'ชั้น 2+', 'workflowStatus' => 'published', 'registrationZone' => 'bangkok_only',
            'documentTitle' => 'เอกสาร', 'priceOptions' => [['repairType' => 'garage', 'premiumTotal' => 6200, 'sumInsuredMax' => 200000]]]);

        $strict = $this->search(['registrationPlate' => 'bangkok_only_strict']);
        $this->assertCount(1, $strict);
        $this->assertSame($name, $strict[0]['planName']);

        $has = fn (string $plate) => in_array($name, array_column($this->search(['registrationPlate' => $plate, 'pageSize' => 100]), 'planName'), true);
        $this->assertTrue($has('bangkok'));
        $this->assertFalse($has('metro'));
        $this->assertFalse($has('upcountry'));

        $upcountry = $this->search(['registrationPlate' => 'upcountry']);
        $types = array_column(SearchService::comparisonWarnings([$strict[0], $upcountry[0]]), 'type');
        $this->assertContains('registration_zone_diff', $types);
    }

    // 2. Comparison warnings (Sections 9 and 11)

    private function dummy(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'opt-1', 'priceOptionLabel' => 'Option 1', 'repairType' => 'dealer', 'priceType' => 'fixed_for_conditions',
            'premiumTotal' => 20000.0, 'compulsoryIncluded' => 'excluded', 'sumInsuredMin' => 500000.0, 'sumInsuredMax' => 500000.0,
            'deductibleAmount' => 0.0, 'driverConditions' => null, 'priceValidTo' => null, 'versionEffectiveTo' => null,
            'planId' => 'plan-1', 'planName' => 'Plan 1', 'insuranceType' => 'ชั้น 1', 'insurerId' => 'ins-1', 'insurerName' => 'Insurer A',
            'scopeType' => 'all_models', 'vehicleModelId' => null, 'minCarAge' => null, 'maxCarAge' => null, 'usageType' => null,
            'registrationZone' => 'all', 'coverages' => [], 'documents' => [],
        ];
    }

    public function test_warns_on_sum_insured_repair_and_deductible_differences(): void
    {
        $other = $this->dummy(['id' => 'opt-2', 'repairType' => 'garage', 'sumInsuredMin' => 400000.0, 'sumInsuredMax' => 400000.0, 'deductibleAmount' => 3000.0]);
        $types = array_column(SearchService::comparisonWarnings([$this->dummy(), $other]), 'type');
        foreach (['sum_insured_diff', 'repair_type_diff', 'deductible_diff'] as $t) {
            $this->assertContains($t, $types);
        }
    }

    public function test_warns_on_unknown_price(): void
    {
        $types = array_column(SearchService::comparisonWarnings([$this->dummy(), $this->dummy(['id' => 'opt-3', 'premiumTotal' => null])]), 'type');
        $this->assertContains('unknown_data', $types);
    }

    public function test_never_shows_unknown_sum_as_zero_and_flags_expired_price(): void
    {
        $expired = $this->dummy(['id' => 'opt-4', 'sumInsuredMin' => null, 'sumInsuredMax' => null, 'priceValidTo' => '2020-01-01']);
        $warnings = collect(SearchService::comparisonWarnings([$this->dummy(), $expired]))->keyBy('type');
        $this->assertArrayHasKey('sum_insured_diff', $warnings);
        $this->assertStringNotContainsString('฿0', $warnings['sum_insured_diff']['message']);
        $this->assertArrayHasKey('expired_price', $warnings);
    }

    public function test_covered_without_limit_reads_as_unspecified_limit(): void
    {
        ['plan' => $plan] = $this->plan(['name' => 'แผนทดสอบไม่ระบุวงเงิน', 'insuranceType' => 'ชั้น 1', 'workflowStatus' => 'published',
            'documentTitle' => 'เอกสารอ้างอิง',
            'priceOptions' => [
                ['label' => 'Tier A', 'repairType' => 'garage', 'premiumTotal' => 8000, 'sumInsuredMax' => 200000],
                ['label' => 'Tier B', 'repairType' => 'dealer', 'premiumTotal' => 11000, 'sumInsuredMax' => 200000],
            ],
            'coverages' => [['code' => 'own_damage', 'status' => 'covered', 'limitAmount' => null, 'limitBasis' => '']]]);

        $items = $this->search(['query' => $plan->name]);
        $this->assertCount(2, $items);
        $comparison = SearchService::comparison(array_column($items, 'id'));
        $row = collect(collect($comparison['categories'])->firstWhere('name', 'ตัวรถยนต์')['items'])->firstWhere('code', 'own_damage');
        $this->assertSame('คุ้มครอง (ยังไม่ระบุวงเงิน)', $row['values'][$items[0]['id']]['text']);
    }

    // 4. Versioning, workflow and archive rules (Sections 8.1 and 12)

    public function test_search_only_shows_the_latest_published_version_of_a_plan(): void
    {
        $byPlan = collect(SearchService::search(['pageSize' => 100])['items'])->groupBy('planId');
        foreach ($byPlan as $items) {
            $this->assertCount(1, $items->pluck('versionNo')->unique());
        }
    }

    public function test_workflow_persists_with_audit_and_archives_old_versions(): void
    {
        ['plan' => $plan, 'version' => $v1] = $this->plan(['name' => 'แผนทดสอบ Workflow', 'insuranceType' => 'ชั้น 2+',
            'workflowStatus' => 'published', 'documentTitle' => 'ตารางเบี้ยรุ่น 1']);
        ['version' => $v2] = $this->plan(['planId' => $plan->id, 'name' => 'แผนทดสอบ Workflow', 'insuranceType' => 'ชั้น 2+',
            'workflowStatus' => 'draft', 'documentTitle' => 'ตารางเบี้ยรุ่น 2']);

        InsuranceStore::submitForReview($v2->id, 'ส่งตรวจรุ่น 2', null);
        $this->assertSame('in_review', $v2->fresh()->workflow_status);
        InsuranceStore::rejectPlanVersion($v2->id, 'ขอแก้หมายเหตุ', null);
        $this->assertSame('draft', $v2->fresh()->workflow_status);
        InsuranceStore::submitForReview($v2->id, 'ส่งตรวจรอบสอง', null);
        InsuranceStore::publishPlanVersion($v2->id, 'อนุมัติรุ่น 2', null);

        $this->assertSame('published', $v2->fresh()->workflow_status);
        $this->assertSame('archived', $v1->fresh()->workflow_status);

        $this->assertSame('archived', InsuranceStore::deletePlan($plan->id, null));
        $this->assertTrue($plan->fresh()->is_archived);
        $this->assertTrue(AuditLog::where('entity_id', $v2->id)->where('action', 'publish')->exists());
        $this->assertTrue(AuditLog::where('entity_id', $plan->id)->where('action', 'archive')->exists());
    }

    public function test_persists_uploaded_document_metadata(): void
    {
        ['version' => $version] = $this->plan(['name' => 'Chubb 2+ Country Road', 'insuranceType' => 'ชั้น 2+', 'workflowStatus' => 'published',
            'sourceType' => 'rate_sheet', 'documentTitle' => 'Rate Sheet', 'documentRef' => 'หน้า 1',
            'documentStoragePath' => 'documents/1793498200-a1b2c3d4.pdf', 'documentSourceUrl' => '/documents/1793498200-a1b2c3d4.pdf',
            'documentFileSize' => 512000, 'documentMimeType' => 'application/pdf']);

        $docs = SourceDocument::where('plan_version_id', $version->id)->get();
        $this->assertCount(1, $docs);
        $this->assertSame(['/documents/1793498200-a1b2c3d4.pdf', 'documents/1793498200-a1b2c3d4.pdf', 512000, 'application/pdf'],
            [$docs[0]->source_url, $docs[0]->storage_path, $docs[0]->file_size, $docs[0]->mime_type]);
    }

    public function test_empty_max_car_age_means_no_limit(): void
    {
        ['plan' => $plan] = $this->plan(['name' => 'แผนไม่จำกัดอายุรถ', 'insuranceType' => 'ชั้น 3', 'workflowStatus' => 'published',
            'minCarAge' => 1, 'maxCarAge' => null, 'documentTitle' => 'เอกสาร',
            'priceOptions' => [['repairType' => 'garage', 'premiumTotal' => 2500, 'sumInsuredMax' => null]]]);

        $items = collect(SearchService::search(['carAge' => 30, 'pageSize' => 100])['items']);
        $this->assertTrue($items->contains(fn ($i) => $i['planId'] === $plan->id && $i['maxCarAge'] === null));
    }

    // Uploaded document files

    private function storeFiles(array $names): void
    {
        foreach ($names as $name) {
            Storage::disk('local')->put("documents/{$name}", 'x');
        }
    }

    public function test_deleting_a_draft_plan_removes_its_file(): void
    {
        Storage::fake('local');
        $this->storeFiles(['draft.pdf']);
        ['plan' => $plan] = $this->plan(['name' => 'แผนทดสอบไฟล์', 'insuranceType' => 'ชั้น 1', 'workflowStatus' => 'draft',
            'documentStoragePath' => 'documents/draft.pdf']);

        $this->assertSame('deleted', InsuranceStore::deletePlan($plan->id, null));
        Storage::disk('local')->assertMissing('documents/draft.pdf');
    }

    public function test_keeps_a_replaced_file_while_another_version_uses_it(): void
    {
        Storage::fake('local');
        $this->storeFiles(['a.pdf', 'b.pdf', 'c.pdf']);
        $base = ['name' => 'แผนทดสอบไฟล์', 'insuranceType' => 'ชั้น 1', 'workflowStatus' => 'draft'];
        ['plan' => $plan, 'version' => $v1] = $this->plan($base + ['documentStoragePath' => 'documents/a.pdf']);
        ['version' => $v2] = $this->plan($base + ['planId' => $plan->id, 'documentStoragePath' => 'documents/a.pdf']);

        $this->plan($base + ['planId' => $plan->id, 'versionId' => $v1->id, 'documentStoragePath' => 'documents/b.pdf']);
        Storage::disk('local')->assertExists('documents/a.pdf');

        $this->plan($base + ['planId' => $plan->id, 'versionId' => $v2->id, 'documentStoragePath' => 'documents/c.pdf']);
        Storage::disk('local')->assertMissing('documents/a.pdf');
        Storage::disk('local')->assertExists('documents/b.pdf');
    }

    public function test_sweeps_old_unreferenced_uploads_only(): void
    {
        Storage::fake('local');
        $this->storeFiles(['unsaved.pdf', 'saved.pdf', 'fresh.pdf']);
        $this->plan(['name' => 'แผนทดสอบไฟล์', 'insuranceType' => 'ชั้น 1', 'workflowStatus' => 'draft',
            'documentStoragePath' => 'documents/saved.pdf']);
        $root = Storage::disk('local')->path('documents');
        foreach (['unsaved.pdf', 'saved.pdf'] as $name) {
            touch("{$root}/{$name}", strtotime('2000-01-01'));
        }

        InsuranceStore::removeAbandonedUploads();
        Storage::disk('local')->assertMissing('documents/unsaved.pdf');
        Storage::disk('local')->assertExists('documents/saved.pdf');
        Storage::disk('local')->assertExists('documents/fresh.pdf');
    }
}
