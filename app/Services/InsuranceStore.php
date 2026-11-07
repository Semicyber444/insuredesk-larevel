<?php

namespace App\Services;

use App\Exceptions\StoreException;
use App\Models\AuditLog;
use App\Models\CoverageType;
use App\Models\EligibilityRule;
use App\Models\Insurer;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\PriceOption;
use App\Models\SourceDocument;
use App\Models\User;
use App\Models\VersionCoverage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Every write to plan, insurer and workflow data, each in one transaction with its audit entry.
 * Ported from the Next.js app's src/lib/store/insuranceStore.ts; the rules and messages are the same.
 */
class InsuranceStore
{
    /** Uploaded files live on the local disk under this folder; source_documents.storage_path holds "documents/<name>". */
    public const DOCUMENT_DIR = 'documents';

    /** How long an uploaded file may sit unused before it counts as left behind by a cancelled form. */
    public const ABANDONED_UPLOAD_DAYS = 7;

    public const AUDIT_PAGE_SIZE = 200;

    public const NO_DOCUMENT_MESSAGE = 'ไม่สามารถเผยแพร่ได้: ต้องแนบหรือระบุเอกสารอ้างอิงต้นทางอย่างน้อย 1 รายการ (Section 8.4)';

    // 1. Insurers

    public static function saveInsurer(array $data, ?User $actor): Insurer
    {
        $name = trim($data['name']);
        $contact = trim((string) ($data['contact_channels'] ?? '')) ?: null;
        $website = trim((string) ($data['website'] ?? '')) ?: null;

        return DB::transaction(function () use ($data, $actor, $name, $contact, $website) {
            if (! empty($data['id'])) {
                $insurer = Insurer::lockForUpdate()->find($data['id']);
                if (! $insurer) {
                    throw new StoreException('ไม่พบบริษัทประกันภัยที่ระบุ');
                }
                $before = ['name' => $insurer->name, 'is_active' => $insurer->is_active];
                $insurer->update([
                    'name' => $name,
                    'contact_channels' => $contact,
                    'website' => $website,
                    'is_active' => $data['is_active'] ?? $insurer->is_active,
                    'updated_by' => $actor?->id,
                ]);
                self::audit($actor, 'update', 'insurer', $insurer->id, $before,
                    ['name' => $name, 'is_active' => $insurer->is_active], "แก้ไขข้อมูลบริษัทประกันภัย \"{$name}\"");

                return $insurer;
            }

            $insurer = Insurer::create([
                'name' => $name,
                'contact_channels' => $contact,
                'website' => $website,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
            self::audit($actor, 'create', 'insurer', $insurer->id, null, ['name' => $name], "เพิ่มบริษัทประกันภัย \"{$name}\"");

            return $insurer;
        });
    }

    /** Section 8.1: never hard-delete an insurer referenced by plans; deactivate it instead. */
    public static function deleteInsurer(string $id, ?User $actor): string
    {
        return DB::transaction(function () use ($id, $actor) {
            $insurer = Insurer::lockForUpdate()->find($id);
            if (! $insurer) {
                return 'deleted';
            }

            if (Plan::where('insurer_id', $id)->exists()) {
                $insurer->update(['is_active' => false, 'updated_by' => $actor?->id]);
                self::audit($actor, 'archive', 'insurer', $id, ['is_active' => true], ['is_active' => false],
                    "ปิดการใช้งานบริษัท \"{$insurer->name}\" แทนการลบ เนื่องจากมีแพ็กเกจผูกอยู่");

                return 'deactivated';
            }

            $insurer->delete();
            self::audit($actor, 'delete', 'insurer', $id, ['name' => $insurer->name], null,
                "ลบบริษัทประกันภัย \"{$insurer->name}\" ที่ยังไม่มีแพ็กเกจผูกอยู่");

            return 'deleted';
        });
    }

    // 2. Workflow (Section 12.2 & 12.3)

    public static function submitForReview(string $versionId, string $reason, ?User $actor): void
    {
        DB::transaction(function () use ($versionId, $reason, $actor) {
            $version = self::lockVersionInStatus($versionId, 'draft', 'ส่งตรวจได้เฉพาะรุ่นที่เป็นฉบับร่างเท่านั้น');
            $version->update(['workflow_status' => 'in_review', 'updated_by' => $actor?->id]);
            self::audit($actor, 'submit_review', 'plan_version', $versionId,
                ['workflow_status' => 'draft'], ['workflow_status' => 'in_review'], $reason);
        });
    }

    public static function publishPlanVersion(string $versionId, string $reason, ?User $actor): void
    {
        DB::transaction(function () use ($versionId, $reason, $actor) {
            $version = self::lockVersionInStatus($versionId, 'in_review', 'เผยแพร่ได้เฉพาะรุ่นที่อยู่ระหว่างตรวจสอบเท่านั้น');
            if (! SourceDocument::where('plan_version_id', $versionId)->exists()) {
                throw new StoreException(self::NO_DOCUMENT_MESSAGE);
            }

            $now = now();
            $version->update([
                'workflow_status' => 'published',
                'verified_at' => $now,
                'verified_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
            self::archiveOtherPublishedVersions($version->plan_id, $versionId);
            self::audit($actor, 'publish', 'plan_version', $versionId, ['workflow_status' => 'in_review'],
                ['workflow_status' => 'published', 'verified_at' => $now->toIso8601String()], $reason);
        });
    }

    public static function rejectPlanVersion(string $versionId, string $reason, ?User $actor): void
    {
        DB::transaction(function () use ($versionId, $reason, $actor) {
            $version = self::lockVersionInStatus($versionId, 'in_review', 'ตีกลับได้เฉพาะรุ่นที่อยู่ระหว่างตรวจสอบเท่านั้น');
            $version->update(['workflow_status' => 'draft', 'updated_by' => $actor?->id]);
            self::audit($actor, 'reject', 'plan_version', $versionId,
                ['workflow_status' => 'in_review'], ['workflow_status' => 'draft'], $reason);
        });
    }

    // 3. Plans

    /**
     * Creates or edits a plan with one version, its single eligibility rule, its price tiers, coverages
     * and source document. $unpublishedOnly is for users who can't publish: only drafts may be edited,
     * and a published plan keeps its name, insurer and type.
     *
     * @return array{plan: Plan, version: PlanVersion}
     */
    public static function createOrUpdatePlan(array $p, ?User $actor, bool $unpublishedOnly = false): array
    {
        $replacedFile = null;
        $saved = DB::transaction(function () use ($p, $actor, $unpublishedOnly, &$replacedFile) {
            $today = today()->toDateString();
            $nextYear = today()->addDays(365)->toDateString();
            $name = trim($p['name']);
            $targetStatus = $p['workflowStatus'] ?? 'published';
            $isEdit = ! empty($p['planId']);
            $has = fn (string $key) => array_key_exists($key, $p);

            // 1. Plan
            if ($isEdit) {
                $plan = self::lockPlan($p['planId']);
                if (! $plan) {
                    throw new StoreException('ไม่พบแพ็กเกจที่ระบุ');
                }
                // These fields live on the plan, so changing them changes the published version too.
                $changesLiveFields = $plan->name !== $name || $plan->insurer_id !== $p['insurerId']
                    || $plan->insurance_type !== $p['insuranceType'];
                if ($unpublishedOnly && $changesLiveFields
                    && $plan->versions()->where('workflow_status', 'published')->exists()) {
                    throw new StoreException('ชื่อแพ็กเกจ บริษัทประกัน และชั้นประกันของแพ็กเกจที่เผยแพร่แล้ว เปลี่ยนได้เฉพาะผู้มีสิทธิ์เผยแพร่');
                }
                $plan->update([
                    'insurer_id' => $p['insurerId'],
                    'name' => $name,
                    'insurance_type' => $p['insuranceType'],
                    'updated_by' => $actor?->id,
                ]);
            } else {
                $plan = Plan::create([
                    'insurer_id' => $p['insurerId'],
                    'name' => $name,
                    'insurance_type' => $p['insuranceType'],
                    'is_archived' => false,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);
            }

            // 2. Version
            if (! empty($p['versionId'])) {
                $version = PlanVersion::where('id', $p['versionId'])->where('plan_id', $plan->id)->first();
                if (! $version) {
                    throw new StoreException('ไม่พบรุ่นแพ็กเกจที่ระบุ');
                }
                // Checked under the plan lock, so a publish that lands mid-save can't be overwritten.
                // Drafts only: editing an in-review version would let a reviewer publish changes they never saw.
                if ($unpublishedOnly && $version->workflow_status !== 'draft') {
                    throw new StoreException('แก้ไขได้เฉพาะรุ่นฉบับร่าง รุ่นที่ส่งตรวจแล้วต้องให้ผู้ตรวจสอบตีกลับก่อน หรือสร้างรุ่นใหม่แทน');
                }
                $published = $targetStatus === 'published';
                $version->update([
                    'workflow_status' => $targetStatus,
                    'effective_from' => $has('effectiveFrom') ? $p['effectiveFrom'] : $version->effective_from,
                    'effective_to' => $has('effectiveTo') ? $p['effectiveTo'] : $version->effective_to,
                    'review_due_at' => $has('reviewDueAt') ? $p['reviewDueAt'] : $version->review_due_at,
                    'verified_at' => $published ? ($version->verified_at ?: now()) : $version->verified_at,
                    'verified_by' => $published ? ($version->verified_by ?: $actor?->id) : $version->verified_by,
                    'notes' => $has('notes') ? $p['notes'] : $version->notes,
                    'updated_by' => $actor?->id,
                ]);
            } else {
                $published = $targetStatus === 'published';
                $version = PlanVersion::create([
                    'plan_id' => $plan->id,
                    'version_no' => (int) PlanVersion::where('plan_id', $plan->id)->max('version_no') + 1,
                    'workflow_status' => $targetStatus,
                    'effective_from' => $p['effectiveFrom'] ?? $today,
                    'effective_to' => $p['effectiveTo'] ?? $nextYear,
                    'verified_at' => $published ? now() : null,
                    'verified_by' => $published ? $actor?->id : null,
                    'review_due_at' => $p['reviewDueAt'] ?? today()->addDays(335)->toDateString(),
                    'notes' => ($p['notes'] ?? '') ?: 'บันทึกข้อมูลแพ็กเกจใหม่',
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);
            }

            // Section 12.3: at most one published version per plan.
            if ($targetStatus === 'published') {
                self::archiveOtherPublishedVersions($plan->id, $version->id);
            }

            // 3. Eligibility rule (a version keeps a single rule; extras would be unreachable from the form)
            $rules = $version->rules()->orderBy('created_at')->orderBy('id')->get();
            $rule = $rules->first();
            $scope = ($p['scopeType'] ?? null) ?: 'all_models';
            $ruleFields = [
                'scope_type' => $scope,
                'vehicle_model_id' => $scope === 'specific_model' ? (($p['vehicleModelId'] ?? null) ?: null) : null,
                // null means "no limit"; only a missing value falls back to the default.
                'min_age' => $has('minCarAge') ? $p['minCarAge'] : 1,
                'max_age' => $has('maxCarAge') ? $p['maxCarAge'] : 10,
                'age_basis' => $has('ageBasis') ? $p['ageBasis'] : ($rule?->age_basis ?? 'registration_year'),
                'usage_type' => ($p['usageType'] ?? null) ?: 'private',
                'registration_zone' => $has('registrationZone') ? ($p['registrationZone'] ?? 'all') : ($rule?->registration_zone ?? 'all'),
                'inspection_required' => $p['inspectionRequired'] ?? false,
                'notes' => match ($scope) {
                    'specific_model' => 'เงื่อนไขเฉพาะรุ่นรถที่ระบุ',
                    'unknown' => 'ยังไม่ทราบขอบเขตรุ่นรถ ต้องตรวจสอบเอกสารเพิ่มเติม',
                    default => 'รถยนต์ทั่วไปตามเงื่อนไขอายุรถ',
                },
                'updated_by' => $actor?->id,
            ];
            if ($rule) {
                $rule->update($ruleFields);
                EligibilityRule::whereIn('id', $rules->slice(1)->pluck('id'))->delete();
            } else {
                $rule = $version->rules()->create($ruleFields + ['created_by' => $actor?->id]);
            }

            // 4. Price options
            $from = $version->effective_from ?: $today;
            $to = $version->effective_to ?: $nextYear;
            $optionInputs = [];
            if (! empty($p['priceOptions'])) {
                foreach (array_values($p['priceOptions']) as $i => $po) {
                    $sumMax = array_key_exists('sumInsuredMax', $po) ? $po['sumInsuredMax'] : null;
                    $sumMin = array_key_exists('sumInsuredMin', $po) ? $po['sumInsuredMin'] : $sumMax;
                    $deductible = $po['deductibleAmount'] ?? 0;
                    $optionInputs[] = ['id' => $po['id'] ?? null, 'fields' => [
                        'label' => trim((string) ($po['label'] ?? ''))
                            ?: self::repairLabel($po['repairType']).' - '.($sumMax ? 'ทุน '.number_format($sumMax).' บาท' : 'ยังไม่ระบุทุน'),
                        'sort_order' => $i,
                        'repair_type' => $po['repairType'],
                        'price_type' => ($po['priceType'] ?? null) ?: (($po['premiumTotal'] ?? null) !== null ? 'fixed_for_conditions' : 'unknown'),
                        'premium_total' => $po['premiumTotal'] ?? null,
                        'sum_insured_min' => $sumMin,
                        'sum_insured_max' => $sumMax,
                        'deductible_amount' => $deductible,
                        'deductible_terms' => trim((string) ($po['deductibleTerms'] ?? ''))
                            ?: ($deductible ? 'มีค่าเสียหายส่วนแรก ฿'.number_format($deductible).' กรณีเป็นฝ่ายผิด' : null),
                        'driver_conditions' => trim((string) ($po['driverConditions'] ?? '')) ?: 'ไม่จำกัดชื่อผู้ขับขี่',
                        'compulsory_included' => ($po['compulsoryIncluded'] ?? null) ?: 'excluded',
                        'valid_from' => $po['validFrom'] ?? $from,
                        'valid_to' => $po['validTo'] ?? $to,
                    ]];
                }
            } else {
                $sumMax = $has('sumInsuredMax') ? $p['sumInsuredMax'] : 500000;
                $sumMin = $has('sumInsuredMin') ? $p['sumInsuredMin'] : $sumMax;
                $deductible = $p['deductibleAmount'] ?? 0;
                $repair = ($p['repairType'] ?? null) ?: 'garage';
                $optionInputs[] = ['id' => null, 'fields' => [
                    'label' => trim((string) ($p['priceOptionLabel'] ?? ''))
                        ?: self::repairLabel($repair).' - '.($sumMax ? 'ทุน '.number_format($sumMax) : 'ยังไม่ระบุทุน'),
                    'sort_order' => 0,
                    'repair_type' => $repair,
                    'price_type' => ($p['priceType'] ?? null) ?: 'fixed_for_conditions',
                    'premium_total' => $p['premiumTotal'] ?? null,
                    'sum_insured_min' => $sumMin,
                    'sum_insured_max' => $sumMax,
                    'deductible_amount' => $deductible,
                    'deductible_terms' => ($p['deductibleTerms'] ?? null)
                        ?: ($deductible ? 'มีค่าเสียหายส่วนแรก ฿'.number_format($deductible).' กรณีเป็นฝ่ายผิด' : null),
                    'driver_conditions' => ($p['driverConditions'] ?? null) ?: 'ไม่จำกัดชื่อผู้ขับขี่',
                    'compulsory_included' => ($p['compulsoryIncluded'] ?? null) ?: 'excluded',
                    'valid_from' => $from,
                    'valid_to' => $to,
                ]];
            }

            // Update tiers in place so their ids survive an edit (compare baskets and links point at them).
            // An id is only honoured when it already belongs to this rule, so a request can't pull in another plan's option.
            $existingIds = $rule->options()->pluck('id')->all();
            $kept = [];
            foreach ($optionInputs as ['id' => $id, 'fields' => $fields]) {
                if ($id && in_array($id, $existingIds, true)) {
                    PriceOption::whereKey($id)->update($fields + ['updated_by' => $actor?->id, 'updated_at' => now()]);
                    $kept[] = $id;
                } else {
                    $rule->options()->create($fields + ['created_by' => $actor?->id, 'updated_by' => $actor?->id]);
                }
            }
            PriceOption::whereIn('id', array_diff($existingIds, $kept))->delete();

            // 5. Coverages (Section 9.4: a missing limit basis stays null, never defaults to "ตามทุนประกัน")
            $types = CoverageType::orderBy('sort_order')->orderBy('id')->get();
            $previous = $version->coverages()->get()->keyBy('coverage_type_id');
            $version->coverages()->delete();
            if (! empty($p['coverages'])) {
                $byCode = $types->keyBy('code');
                foreach ($p['coverages'] as $cov) {
                    $type = $byCode[$cov['code']] ?? null;
                    if (! $type) {
                        continue;
                    }
                    $prev = $previous[$type->id] ?? null;
                    VersionCoverage::create([
                        'id' => $prev?->id,
                        'plan_version_id' => $version->id,
                        'coverage_type_id' => $type->id,
                        'status' => $cov['status'],
                        'limit_amount' => $cov['limitAmount'] ?? null,
                        'limit_basis' => trim((string) ($cov['limitBasis'] ?? '')) ?: null,
                        'deductible_amount' => $prev?->deductible_amount ?? 0,
                        'terms' => trim((string) ($cov['terms'] ?? '')) ?: null,
                        'exclusions' => array_key_exists('exclusions', $cov) ? (trim((string) $cov['exclusions']) ?: null) : $prev?->exclusions,
                        'created_at' => $prev?->created_at ?? now(),
                    ]);
                }
            } else {
                foreach ($types as $type) {
                    VersionCoverage::create([
                        'plan_version_id' => $version->id,
                        'coverage_type_id' => $type->id,
                        'deductible_amount' => 0,
                    ] + self::defaultCoverage($type->code, $p['insuranceType']));
                }
            }

            // 6. Source document (update this version's document rather than adding another)
            $docTitle = trim((string) ($p['documentTitle'] ?? ''));
            if ($docTitle !== '' || ! empty($p['documentStoragePath']) || trim((string) ($p['documentSourceUrl'] ?? '')) !== '') {
                $doc = $version->documents()->first();
                $title = $docTitle ?: "{$plan->name} (เอกสารอ้างอิง)";
                $placeholder = "documents/{$plan->id}/{$version->id}.pdf";
                if ($doc) {
                    if ($has('documentStoragePath') && $p['documentStoragePath'] !== $doc->storage_path) {
                        $replacedFile = $doc->storage_path;
                    }
                    $doc->update([
                        'source_type' => ($p['sourceType'] ?? null) ?: $doc->source_type,
                        'title' => $title,
                        'page_reference' => $has('documentRef') ? (trim((string) $p['documentRef']) ?: null) : $doc->page_reference,
                        'storage_path' => $has('documentStoragePath') ? (($p['documentStoragePath'] ?? null) ?: $placeholder) : $doc->storage_path,
                        'source_url' => $has('documentSourceUrl') ? (trim((string) $p['documentSourceUrl']) ?: null) : $doc->source_url,
                        'file_size' => $has('documentFileSize') ? $p['documentFileSize'] : $doc->file_size,
                        'mime_type' => $has('documentMimeType') ? $p['documentMimeType'] : $doc->mime_type,
                    ]);
                } else {
                    $version->documents()->create([
                        'source_type' => ($p['sourceType'] ?? null) ?: 'rate_sheet',
                        'storage_path' => ($p['documentStoragePath'] ?? null) ?: $placeholder,
                        'source_url' => trim((string) ($p['documentSourceUrl'] ?? '')) ?: null,
                        'title' => $title,
                        'file_size' => $p['documentFileSize'] ?? null,
                        'mime_type' => $p['documentMimeType'] ?? null,
                        'page_reference' => trim((string) ($p['documentRef'] ?? '')) ?: null,
                        'received_at' => $today,
                        'created_by' => $actor?->id,
                    ]);
                }
            }

            // 7. Audit
            self::audit($actor, $isEdit ? 'update' : 'create', 'plan_version', $version->id,
                $isEdit ? ['plan_id' => $plan->id] : null,
                ['plan_name' => $plan->name, 'version_no' => $version->version_no, 'status' => $targetStatus],
                $isEdit
                    ? "บันทึกการแก้ไขข้อมูลแพ็กเกจ \"{$plan->name}\" (รุ่นที่ {$version->version_no})"
                    : "สร้างแพ็กเกจใหม่ \"{$plan->name}\" (สถานะ: {$targetStatus})");

            return ['plan' => $plan, 'version' => $version];
        });
        self::removeUnreferencedFiles([$replacedFile]);

        return $saved;
    }

    /** Section 8.1: a plan that was ever published is archived; a draft-only plan is deleted with its files. */
    public static function deletePlan(string $planId, ?User $actor): string
    {
        $files = [];
        $mode = DB::transaction(function () use ($planId, $actor, &$files) {
            $plan = self::lockPlan($planId);
            if (! $plan) {
                return 'deleted';
            }

            if ($plan->versions()->whereIn('workflow_status', ['published', 'archived'])->exists()) {
                $plan->update(['is_archived' => true, 'updated_by' => $actor?->id]);
                self::audit($actor, 'archive', 'plan', $planId, ['is_archived' => false], ['is_archived' => true],
                    "จัดเก็บแพ็กเกจ \"{$plan->name}\" เข้ากรุ (Archive) เพื่อรักษาประวัติรุ่นที่เคยเผยแพร่");

                return 'archived';
            }

            // Foreign keys cascade to versions, rules, options, coverages and documents.
            $files = SourceDocument::whereIn('plan_version_id', $plan->versions()->select('id'))->pluck('storage_path')->all();
            $plan->delete();
            self::audit($actor, 'delete', 'plan', $planId, ['name' => $plan->name], null,
                "ลบแพ็กเกจร่าง \"{$plan->name}\" ที่ยังไม่เคยเผยแพร่");

            return 'deleted';
        });
        self::removeUnreferencedFiles($files);

        return $mode;
    }

    public static function restorePlan(string $planId, ?User $actor): void
    {
        DB::transaction(function () use ($planId, $actor) {
            $plan = self::lockPlan($planId);
            if (! $plan) {
                return;
            }
            $plan->update(['is_archived' => false, 'updated_by' => $actor?->id]);
            self::audit($actor, 'restore', 'plan', $planId, ['is_archived' => true], ['is_archived' => false],
                "กู้คืนแพ็กเกจ \"{$plan->name}\" กลับมาใช้งาน");
        });
    }

    // 4. Documents

    /** True when the stored file belongs to a published version of a plan that isn't archived. */
    public static function isPublishedDocument(string $storagePath): bool
    {
        return SourceDocument::where('storage_path', $storagePath)
            ->whereHas('version', fn ($v) => $v->where('workflow_status', 'published')
                ->whereHas('plan', fn ($p) => $p->where('is_archived', false)))
            ->exists();
    }

    /**
     * The upload writes its file before the form is saved, so a cancelled form leaves the file behind.
     * Deletes such files once they are older than ABANDONED_UPLOAD_DAYS and no document points at them.
     * shortcut: a form left open longer than 7 days and then saved points at a deleted file; widen
     * ABANDONED_UPLOAD_DAYS if editors keep forms open that long.
     */
    public static function removeAbandonedUploads(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays(self::ABANDONED_UPLOAD_DAYS)->getTimestamp();
        $old = array_filter($disk->files(self::DOCUMENT_DIR), fn ($path) => $disk->lastModified($path) < $cutoff);
        self::removeUnreferencedFiles($old);
    }

    // Helpers

    /**
     * Deletes uploaded files no source document points at any more. Runs after the transaction commits,
     * so a rollback never loses a file. Paths outside the upload folder (title-only placeholders) are never
     * touched, and a file another version still uses (a new version keeps the same file) stays.
     */
    private static function removeUnreferencedFiles(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            if (! preg_match('#^'.self::DOCUMENT_DIR.'/[A-Za-z0-9_-][A-Za-z0-9._-]*$#', $path) || str_contains($path, '..')) {
                continue;
            }
            if (! SourceDocument::where('storage_path', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private static function audit(?User $actor, string $action, string $entityType, string $entityId,
        ?array $before, ?array $after, ?string $reason): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'ระบบ',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $before,
            'after_data' => $after,
            'reason' => $reason,
        ]);
    }

    /** Locks the plan so concurrent publishes and version numbering stay consistent. */
    private static function lockPlan(string $planId): ?Plan
    {
        return Plan::lockForUpdate()->find($planId);
    }

    /** Locks the version's plan, so concurrent workflow changes can't both pass the status check. */
    private static function lockVersionInStatus(string $versionId, string $allowed, string $message): PlanVersion
    {
        $planId = PlanVersion::whereKey($versionId)->value('plan_id');
        if (! $planId) {
            throw new StoreException('ไม่พบรุ่นแพ็กเกจที่ระบุ');
        }
        self::lockPlan($planId);
        $version = PlanVersion::findOrFail($versionId);
        if ($version->workflow_status !== $allowed) {
            throw new StoreException($message);
        }

        return $version;
    }

    private static function archiveOtherPublishedVersions(string $planId, string $keepVersionId): void
    {
        PlanVersion::where('plan_id', $planId)->where('id', '<>', $keepVersionId)
            ->where('workflow_status', 'published')->update(['workflow_status' => 'archived', 'updated_at' => now()]);
    }

    private static function repairLabel(string $repairType): string
    {
        return $repairType === 'dealer' ? 'ซ่อมห้าง' : 'ซ่อมอู่';
    }

    /** Starting coverage for a new plan saved without a coverage list, by insurance class. */
    private static function defaultCoverage(string $code, string $type): array
    {
        $isType1 = $type === 'ชั้น 1';
        $hasOwnDamage = $isType1 || str_contains($type, '2+');
        $status = 'covered';
        $limitAmount = null;
        $limitBasis = null;

        switch ($code) {
            case 'flood':
            case 'theft_fire':
            case 'own_damage':
                $status = $hasOwnDamage ? 'covered' : 'not_covered';
                $limitBasis = $hasOwnDamage ? 'ตามทุนประกัน' : null;
                break;
            case 'rollover_offroad':
            case 'windshield':
                $status = $isType1 ? 'covered' : 'not_covered';
                $limitBasis = $isType1 ? 'ตามทุนประกัน' : null;
                break;
            case 'theft_personal_prop':
                $status = $isType1 ? 'optional' : 'not_covered';
                [$limitAmount, $limitBasis] = $isType1 ? [20000, 'ต่อครั้ง'] : [null, null];
                break;
            case 'tp_life_person':
                [$limitAmount, $limitBasis] = [1000000, 'ต่อคน'];
                break;
            case 'tp_life_event':
                [$limitAmount, $limitBasis] = [10000000, 'ต่อครั้ง'];
                break;
            case 'tp_property':
                [$limitAmount, $limitBasis] = [2500000, 'ต่อครั้ง'];
                break;
            case 'pa_driver_passenger':
            case 'medical_expenses':
                [$limitAmount, $limitBasis] = [200000, 'ต่อคน'];
                break;
            case 'bail_bond':
                [$limitAmount, $limitBasis] = [300000, 'ต่อครั้ง'];
                break;
            case 'roadside_assist':
                $status = $isType1 ? 'covered' : 'optional';
                $limitBasis = '24 ชม.';
                break;
            case 'towing':
                $status = $isType1 ? 'covered' : 'optional';
                $limitBasis = 'ตามเงื่อนไขบริษัท';
                break;
            case 'loss_of_use':
                $status = 'optional';
                $limitBasis = 'ซื้อความคุ้มครองเสริม';
                break;
            case 'accessories':
                $status = $hasOwnDamage ? 'covered' : 'optional';
                [$limitAmount, $limitBasis] = [20000, 'ต่อครั้ง (ตามที่แจ้งไว้)'];
                break;
        }

        return ['status' => $status, 'limit_amount' => $limitAmount, 'limit_basis' => $limitBasis];
    }
}
