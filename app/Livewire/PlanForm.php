<?php

namespace App\Livewire;

use App\Actions\SavePlan;
use App\Exceptions\StoreException;
use App\Models\CoverageType;
use App\Models\Insurer;
use App\Models\Plan;
use App\Models\VehicleModel;
use App\Services\InsuranceStore;
use App\Support\DocumentTypes;
use App\Support\Format;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Create, edit or copy a plan in one modal with five tabs. Ported from PlanFormModal.tsx.
 * Opened by dispatching 'open-plan-form' {planId?, copy?}; announces 'plan-saved' {planId} when done.
 */
class PlanForm extends Component
{
    use WithFileUploads;

    public const MAX_UPLOAD_KB = 15 * 1024;

    

    public bool $open = false;

    public ?string $planId = null;

    public bool $copy = false;

    public string $sourceName = '';

    public string $activeTab = 'info';

    public string $error = '';

    public ?string $currentVersionId = null;

    public int $currentVersionNo = 1;

    public string $currentVersionStatus = 'draft';

    public string $saveMode = 'new_version';

    public string $insurerId = '';

    public string $name = '';

    public string $insuranceType = 'ชั้น 1';

    public string $workflowStatus = 'draft';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public string $notes = '';

    public string $scopeType = 'all_models';

    public string $vehicleModelId = '';

    public $minCarAge = 1;

    public $maxCarAge = 10;

    public string $usageType = 'private';

    public string $registrationZone = 'all';

    public bool $inspectionRequired = false;

    public string $ageBasis = 'registration_year';

    public array $tiers = [];

    public array $coverages = [];

    public string $documentTitle = '';

    public string $documentRef = '';

    public string $sourceType = 'rate_sheet';

    public ?string $documentStoragePath = null;

    public string $documentSourceUrl = '';

    public ?int $documentFileSize = null;

    public ?string $documentMimeType = null;

    public string $uploadedOriginalName = '';

    public $upload;

    #[On('open-plan-form')]
    public function openForm(?string $planId = null, bool $copy = false): void
    {
        $this->authorize('canEditDraft');
        $this->resetExcept();
        $this->resetErrorBag();
        $plan = $planId ? Plan::find($planId) : null;
        $canPublish = auth()->user()->can('canPublish');

        $this->planId = $plan?->id;
        $this->copy = $plan && $copy;
        $this->effectiveFrom = today()->toDateString();
        $this->effectiveTo = today()->addDays(365)->toDateString();
        $this->tiers = [self::tier(['label' => 'ซ่อมอู่ - ทุน 50,000', 'premiumTotal' => 5400, 'sumInsuredMax' => 50000])];
        $this->coverages = self::coveragePreset('ชั้น 1');

        if (! $plan) {
            $this->insurerId = (string) (Insurer::where('is_active', true)->orderByDesc('created_at')->value('id')
                ?? Insurer::orderByDesc('created_at')->value('id'));
            $this->workflowStatus = $canPublish ? 'published' : 'in_review';
            $this->open = true;

            return;
        }

        $this->fillFromPlan($plan, $canPublish);
        $this->open = true;
    }

    /** Loads the plan's live version (or its latest) into the form, the way buildInitialModalState did. */
    private function fillFromPlan(Plan $plan, bool $canPublish): void
    {
        $versions = $plan->versions()->get();
        $latest = $versions->firstWhere('workflow_status', 'published') ?? $versions->first();
        $locked = in_array($latest?->workflow_status, ['published', 'archived'], true);
        $raw = $latest?->workflow_status ?? 'draft';

        $this->sourceName = $plan->name;
        $this->insurerId = $plan->insurer_id;
        $this->name = $this->copy ? "{$plan->name} (สำเนา)" : $plan->name;
        $this->insuranceType = $plan->insurance_type;
        $this->coverages = self::coveragePreset($plan->insurance_type);
        $this->currentVersionId = $this->copy ? null : $latest?->id;
        $this->currentVersionNo = $this->copy ? 1 : ($latest?->version_no ?? 1);
        $this->currentVersionStatus = $this->copy ? 'draft' : $raw;
        // Section 12.2: a published version defaults to saving a new version, keeping its history.
        $this->saveMode = $this->copy || $locked ? 'new_version' : 'update';
        $this->workflowStatus = $this->copy
            ? ($canPublish ? 'published' : 'in_review')
            : (! $canPublish ? ($raw === 'draft' ? 'draft' : 'in_review') : ($raw === 'archived' ? 'draft' : $raw));
        $this->effectiveFrom = $latest?->effective_from ?: $this->effectiveFrom;
        $this->effectiveTo = $latest?->effective_to ?: $this->effectiveTo;
        $this->notes = $this->copy ? "คัดลอกข้อมูลตั้งต้นจาก \"{$plan->name}\"" : ($latest?->notes ?? '');
        if (! $latest) {
            return;
        }

        $rule = $latest->rules()->orderBy('created_at')->orderBy('id')->first();
        if ($rule) {
            $this->scopeType = $rule->scope_type ?: 'all_models';
            $this->vehicleModelId = $rule->vehicle_model_id ?? '';
            $this->minCarAge = $rule->min_age ?? '';
            $this->maxCarAge = $rule->max_age ?? '';
            $this->usageType = $rule->usage_type ?: 'private';
            $this->registrationZone = $rule->registration_zone ?: 'all';
            $this->inspectionRequired = (bool) $rule->inspection_required;
            $this->ageBasis = $rule->age_basis ?: 'registration_year';
            $options = $rule->options()->get();
            if ($options->isNotEmpty()) {
                $this->tiers = $options->map(function ($o) use ($locked) {
                    $sum = $o->sum_insured_max ?? $o->sum_insured_min;

                    return self::tier([
                        'id' => $locked || $this->copy ? null : $o->id,
                        'label' => $o->label ?? '',
                        'repairType' => $o->repair_type,
                        'priceType' => $o->price_type,
                        'hasPrice' => $o->premium_total !== null,
                        'premiumTotal' => $o->premium_total ?? '',
                        'hasSumInsured' => $sum !== null,
                        'sumInsuredMax' => $sum ?? '',
                        'deductibleAmount' => $o->deductible_amount ?? 0,
                        'deductibleTerms' => $o->deductible_terms ?? '',
                        'compulsoryIncluded' => $o->compulsory_included ?: 'excluded',
                        'driverConditions' => $o->driver_conditions ?: 'ไม่จำกัดชื่อผู้ขับขี่',
                    ]);
                })->all();
            }
        }

        $saved = $latest->coverages()->get()->keyBy('coverage_type_id');
        if ($saved->isNotEmpty()) {
            $isType1 = $plan->insurance_type === 'ชั้น 1';
            $is2Plus = str_contains($plan->insurance_type, '2+');
            $this->coverages = CoverageType::orderBy('sort_order')->orderBy('id')->get()->map(function ($t) use ($saved, $isType1, $is2Plus) {
                $found = $saved[$t->id] ?? null;
                $row = ['code' => $t->code, 'name' => $t->name, 'category' => $t->category, 'status' => $found?->status ?? 'unknown',
                    'limitAmount' => $found?->limit_amount ?? '', 'limitBasis' => $found?->limit_basis ?? '',
                    'terms' => $found?->terms ?? '', 'exclusions' => $found?->exclusions ?? ''];
                // Types added after the version was saved get a sensible start instead of "unknown".
                if (! $found && $t->code === 'accessories') {
                    $row = ['status' => $isType1 || $is2Plus ? 'covered' : 'optional', 'limitAmount' => 20000,
                        'limitBasis' => 'ต่อครั้ง (ตามที่แจ้งไว้)', 'terms' => 'คุ้มครองอุปกรณ์ตกแต่งตามที่แจ้งไว้ไม่เกิน 20,000 บาท'] + $row;
                } elseif (! $found && $t->code === 'rollover_offroad' && $isType1) {
                    $row = ['status' => 'covered', 'limitBasis' => 'ตามทุนประกัน', 'terms' => 'คุ้มครองกรณีรถพลิกคว่ำหรือตกข้างทาง'] + $row;
                }

                return $row;
            })->all();
        }

        $doc = $latest->documents()->first();
        if ($doc) {
            $this->documentTitle = $doc->title;
            $this->documentRef = $doc->page_reference ?? '';
            $this->sourceType = $doc->source_type ?: 'rate_sheet';
            $this->documentStoragePath = $doc->storage_path;
            $this->documentSourceUrl = $doc->source_url ?? '';
            $this->documentFileSize = $doc->file_size;
            $this->documentMimeType = $doc->mime_type;
        }
    }

    public function close(): void
    {
        $this->open = false;
    }

    // Price tiers

    private static function tier(array $values = []): array
    {
        return $values + [
            'id' => null, 'label' => '', 'repairType' => 'garage', 'priceType' => 'fixed_for_conditions',
            'hasPrice' => true, 'premiumTotal' => '', 'hasSumInsured' => true, 'sumInsuredMax' => '',
            'deductibleAmount' => 0, 'deductibleTerms' => '', 'compulsoryIncluded' => 'excluded',
            'driverConditions' => 'ไม่จำกัดชื่อผู้ขับขี่',
        ];
    }

    private static function isAutoLabel(string $label): bool
    {
        return trim($label) === '' || preg_match('/^(ซ่อมห้าง|ซ่อมอู่)\s*-\s*(ทุน|ยังไม่ระบุทุน)/u', trim($label));
    }

    private static function autoLabel(string $repairType, bool $hasSum, $sum): string
    {
        $prefix = $repairType === 'dealer' ? 'ซ่อมห้าง' : 'ซ่อมอู่';

        return ! $hasSum || ! is_numeric($sum) || $sum <= 0 ? "{$prefix} - ยังไม่ระบุทุน" : "{$prefix} - ทุน ".Format::number($sum);
    }

    /** A tier whose label was generated follows its repair type and sum insured. */
    public function updated(string $property): void
    {
        if (preg_match('/^tiers\.(\d+)\.(sumInsuredMax|repairType|hasSumInsured)$/', $property, $m) && isset($this->tiers[$m[1]])) {
            $t = &$this->tiers[$m[1]];
            if (self::isAutoLabel((string) $t['label'])) {
                $t['label'] = self::autoLabel($t['repairType'], (bool) $t['hasSumInsured'], $t['sumInsuredMax']);
            }
        }
    }

    public function addTier(): void
    {
        $last = end($this->tiers) ?: null;
        $sum = $last && $last['hasSumInsured'] ? (float) ($last['sumInsuredMax'] ?: 0) + 100000 : 100000;
        $premium = $last && $last['hasPrice'] ? (float) ($last['premiumTotal'] ?: 0) + 1000 : 6500;
        $repair = $last['repairType'] ?? 'garage';
        $this->tiers[] = self::tier(['label' => self::autoLabel($repair, true, $sum), 'repairType' => $repair,
            'premiumTotal' => $premium, 'sumInsuredMax' => $sum]);
    }

    public function duplicateTier(int $index): void
    {
        $source = $this->tiers[$index] ?? null;
        if (! $source) {
            return;
        }
        $sum = $source['hasSumInsured'] ? (float) ($source['sumInsuredMax'] ?: 0) + 50000 : '';
        $copy = ['id' => null, 'label' => self::autoLabel($source['repairType'], (bool) $source['hasSumInsured'], $sum),
            'sumInsuredMax' => $sum, 'premiumTotal' => $source['hasPrice'] ? (float) ($source['premiumTotal'] ?: 0) + 800 : ''] + $source;
        array_splice($this->tiers, $index + 1, 0, [$copy]);
    }

    public function removeTier(int $index): void
    {
        if (count($this->tiers) <= 1) {
            $this->error = 'แพ็กเกจต้องมีตัวเลือกราคาอย่างน้อย 1 รายการ';

            return;
        }
        array_splice($this->tiers, $index, 1);
    }

    // Coverages

    public function applyCoveragePreset(string $type): void
    {
        if (in_array($type, Format::INSURANCE_TYPES, true)) {
            $this->coverages = self::coveragePreset($type);
        }
    }

    public function setCoverageStatus(int $index, string $status): void
    {
        if (isset($this->coverages[$index]) && in_array($status, ['covered', 'not_covered', 'optional', 'unknown'], true)) {
            $this->coverages[$index]['status'] = $status;
        }
    }

    /** Starting coverage per insurance class, as the old form's buildCoveragePreset. */
    public static function coveragePreset(string $type): array
    {
        $t1 = $type === 'ชั้น 1';
        $t2p = $type === 'ชั้น 2+';
        $t3p = $type === 'ชั้น 3+';
        $t2 = $type === 'ชั้น 2';

        return CoverageType::orderBy('sort_order')->orderBy('id')->get()->map(function ($ct) use ($t1, $t2p, $t3p, $t2) {
            [$status, $amount, $basis, $terms] = match ($ct->code) {
                'own_damage' => $t1 ? ['covered', '', 'ตามทุนประกัน', 'คุ้มครองอุบัติเหตุทุกกรณี']
                    : ($t2p || $t3p ? ['covered', '', 'ตามทุนประกัน (ชนกับยานพาหนะทางบก)', 'ต้องมีคู่กรณีเป็นยานพาหนะทางบก'] : ['not_covered', '', '', '']),
                'rollover_offroad' => $t1 ? ['covered', '', 'ตามทุนประกัน', 'คุ้มครองกรณีรถพลิกคว่ำหรือตกข้างทาง'] : ['not_covered', '', '', ''],
                'theft_fire' => $t1 || $t2p || $t2 ? ['covered', '', 'ตามทุนประกัน', ''] : ['not_covered', '', '', ''],
                'flood' => $t1 || $t2p ? ['covered', '', 'ตามทุนประกัน', ''] : ['not_covered', '', '', ''],
                'windshield' => $t1 ? ['covered', '', 'ตามทุนประกัน', ''] : ['not_covered', '', '', ''],
                'theft_personal_prop' => $t1 ? ['optional', 20000, 'ต่อครั้ง', ''] : ['not_covered', '', '', ''],
                'accessories' => [$t1 || $t2p ? 'covered' : 'optional', 20000, 'ต่อครั้ง (ตามที่แจ้งไว้)', 'คุ้มครองอุปกรณ์ตกแต่งตามที่แจ้งไว้ไม่เกิน 20,000 บาท'],
                'tp_life_person' => ['covered', 1000000, 'ต่อคน', ''],
                'tp_life_event' => ['covered', 10000000, 'ต่อครั้ง', ''],
                'tp_property' => ['covered', $t1 ? 2500000 : 1000000, 'ต่อครั้ง', ''],
                'pa_driver_passenger' => ['covered', 200000, 'ต่อคน (5 ที่นั่ง)', ''],
                'medical_expenses' => ['covered', 200000, 'ต่อคน', ''],
                'bail_bond' => ['covered', 300000, 'ต่อครั้ง', ''],
                'roadside_assist' => [$t1 || $t2p ? 'covered' : 'optional', '', 'ช่วยเหลือ 24 ชม.', ''],
                'towing' => [$t1 || $t2p ? 'covered' : 'optional', 1500, 'ไม่เกิน 50 กม.', ''],
                'loss_of_use' => ['optional', 500, 'ต่อวัน', 'ซื้อความคุ้มครองเสริม'],
                default => ['covered', '', '', ''],
            };

            return ['code' => $ct->code, 'name' => $ct->name, 'category' => $ct->category, 'status' => $status,
                'limitAmount' => $amount, 'limitBasis' => $basis, 'terms' => $terms, 'exclusions' => ''];
        })->all();
    }

    // Document upload

    public function updatedUpload(): void
    {
        $this->error = '';
        $this->authorize('canEditDraft');
        $this->validate(['upload' => ['required', 'file', 'max:'.self::MAX_UPLOAD_KB, 'extensions:'.implode(',', array_keys(DocumentTypes::MIME))]], [
            'upload.max' => 'ขนาดไฟล์เกินกำหนด (สูงสุดไม่เกิน 15 MB)',
            'upload.extensions' => 'รองรับเฉพาะไฟล์ PDF, รูปภาพ (PNG, JPG, WEBP) หรือตาราง Excel/CSV เท่านั้น',
        ]);

        $ext = strtolower($this->upload->getClientOriginalExtension());
        $name = now()->getTimestampMs().'-'.Str::lower(Str::random(8)).".{$ext}";
        $path = $this->upload->storeAs(InsuranceStore::DOCUMENT_DIR, $name, 'local');
        $original = $this->upload->getClientOriginalName();

        $this->documentStoragePath = $path;
        $this->documentSourceUrl = "/documents/{$name}";
        $this->documentFileSize = $this->upload->getSize();
        $this->documentMimeType = DocumentTypes::MIME[$ext];
        $this->uploadedOriginalName = $original;
        if (trim($this->documentTitle) === '') {
            $this->documentTitle = pathinfo($original, PATHINFO_FILENAME);
        }
        $this->upload = null;
        // Each upload also clears files that older, never-saved uploads left behind.
        InsuranceStore::removeAbandonedUploads();
    }

    public function clearUpload(): void
    {
        $this->documentStoragePath = null;
        $this->documentSourceUrl = '';
        $this->documentFileSize = null;
        $this->documentMimeType = null;
        $this->uploadedOriginalName = '';
    }

    // Save

    public function save(): void
    {
        $this->error = '';
        $num = fn ($v) => $v === '' || $v === null ? null : (is_numeric($v) ? $v + 0 : $v);
        $editing = $this->planId && ! $this->copy;
        $newVersion = ! $editing || $this->saveMode === 'new_version';

        // Quick checks with the tab to send the user to; SavePlan repeats all of them on the server side.
        $tab = match (true) {
            trim($this->name) === '' || $this->insurerId === '' => ['info', 'กรุณากรอกชื่อแพ็กเกจประกัน'],
            $this->effectiveFrom && $this->effectiveTo && $this->effectiveTo < $this->effectiveFrom => ['info', 'วันสิ้นสุดผลบังคับใช้ต้องไม่ก่อนวันเริ่มต้น'],
            $this->scopeType === 'specific_model' && ! $this->vehicleModelId => ['vehicle', 'กรุณาเลือกรุ่นรถสำหรับเงื่อนไขระบุรุ่นเฉพาะ'],
            $num($this->minCarAge) !== null && $num($this->maxCarAge) !== null && $num($this->maxCarAge) < $num($this->minCarAge) => ['vehicle', 'อายุรถสูงสุดต้องไม่น้อยกว่าอายุรถขั้นต่ำ'],
            count($this->tiers) === 0 => ['price', 'กรุณาระบุตัวเลือกราคาและทุนประกันอย่างน้อย 1 รายการ'],
            $this->documentSourceUrl !== '' && ! str_starts_with(trim($this->documentSourceUrl), '/documents/')
                && ! preg_match('#^https?://.+#i', trim($this->documentSourceUrl)) => ['document', 'ลิงก์เอกสารภายนอกต้องขึ้นต้นด้วย http:// หรือ https://'],
            $this->workflowStatus === 'published' && auth()->user()->can('canPublish') && trim($this->documentTitle) === '' && trim($this->documentSourceUrl) === ''
                => ['document', 'ตามกฎ Section 8.4 ต้องระบุชื่อเอกสารหรืออัปโหลดไฟล์เอกสารต้นทางอย่างน้อย 1 รายการก่อนเผยแพร่'],
            default => null,
        };
        if ($tab) {
            [$this->activeTab, $this->error] = $tab;

            return;
        }

        $payload = [
            'planId' => $editing ? $this->planId : null,
            'versionId' => $editing && $this->saveMode === 'update' ? $this->currentVersionId : null,
            'insurerId' => $this->insurerId,
            'name' => $this->name,
            'insuranceType' => $this->insuranceType,
            'workflowStatus' => ! auth()->user()->can('canPublish') && $this->workflowStatus === 'published' ? 'in_review' : $this->workflowStatus,
            'effectiveFrom' => $this->effectiveFrom ?: null,
            'effectiveTo' => $this->effectiveTo ?: null,
            'notes' => $this->saveMode === 'new_version' && $editing ? ($this->notes ?: "ปรับปรุงข้อมูลจากรุ่นที่ {$this->currentVersionNo}") : $this->notes,
            'scopeType' => $this->scopeType,
            'vehicleModelId' => $this->scopeType === 'specific_model' ? $this->vehicleModelId : null,
            'minCarAge' => $num($this->minCarAge),
            'maxCarAge' => $num($this->maxCarAge),
            'ageBasis' => $this->ageBasis,
            'usageType' => $this->usageType,
            'registrationZone' => $this->registrationZone,
            'inspectionRequired' => $this->inspectionRequired,
            'priceOptions' => array_map(function ($t) use ($num, $newVersion) {
                $premium = $t['hasPrice'] ? $num($t['premiumTotal']) : null;
                $sum = $t['hasSumInsured'] ? $num($t['sumInsuredMax']) : null;

                return [
                    'id' => $newVersion ? null : $t['id'],
                    'label' => trim((string) $t['label']) ?: self::autoLabel($t['repairType'], (bool) $t['hasSumInsured'], $t['sumInsuredMax']),
                    'repairType' => $t['repairType'],
                    'priceType' => $premium !== null ? $t['priceType'] : 'unknown',
                    'premiumTotal' => $premium,
                    'sumInsuredMin' => $sum,
                    'sumInsuredMax' => $sum,
                    'deductibleAmount' => $num($t['deductibleAmount']) ?? 0,
                    'deductibleTerms' => $t['deductibleTerms'] ?: null,
                    'compulsoryIncluded' => $t['compulsoryIncluded'],
                    'driverConditions' => $t['driverConditions'] ?: 'ไม่จำกัดชื่อผู้ขับขี่',
                    'validFrom' => $this->effectiveFrom ?: null,
                    'validTo' => $this->effectiveTo ?: null,
                ];
            }, array_values($this->tiers)),
            'coverages' => array_map(fn ($c) => [
                'code' => $c['code'],
                'status' => $c['status'],
                'limitAmount' => $num($c['limitAmount']),
                'limitBasis' => $c['limitBasis'] ?: null,
                'terms' => $c['terms'] ?: null,
                'exclusions' => $c['exclusions'] ?: null,
            ], $this->coverages),
            'sourceType' => $this->sourceType,
            'documentTitle' => $this->documentTitle ?: null,
            'documentRef' => $this->documentRef ?: null,
            'documentStoragePath' => $this->documentStoragePath,
            'documentSourceUrl' => trim($this->documentSourceUrl) ?: null,
            'documentFileSize' => $this->documentFileSize,
            'documentMimeType' => $this->documentMimeType,
        ];
        // An empty id means "new"; SavePlan treats a present key as a value.
        foreach (['planId', 'versionId'] as $key) {
            if ($payload[$key] === null) {
                unset($payload[$key]);
            }
        }

        try {
            ['plan' => $plan] = SavePlan::handle(auth()->user(), $payload);
        } catch (StoreException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->open = false;
        $this->dispatch('plan-saved', planId: $plan->id);
    }

    public function render()
    {
        return view('livewire.plan-form', [
            'insurers' => $this->open ? Insurer::orderByDesc('created_at')->orderBy('id')->get() : collect(),
            'vehicles' => $this->open ? VehicleModel::orderBy('created_at')->orderBy('id')->get() : collect(),
        ]);
    }
}
