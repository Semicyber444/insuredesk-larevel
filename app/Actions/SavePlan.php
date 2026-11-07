<?php

namespace App\Actions;

use App\Exceptions\StoreException;
use App\Models\SourceDocument;
use App\Models\User;
use App\Services\InsuranceStore;
use App\Support\Format;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The one way the plan form saves: validates the payload (the old SavePlanSchema), checks the user's
 * rights, then hands it to InsuranceStore. Fields the rules don't list are dropped.
 */
class SavePlan
{
    private const ID = 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const AMOUNT = ['nullable', 'numeric', 'min:0', 'max:9999999999.99'];

    public static function rules(): array
    {
        $date = ['nullable', 'date_format:Y-m-d'];
        $short = ['nullable', 'string', 'max:255'];
        $long = ['nullable', 'string', 'max:5000'];

        return [
            'planId' => ['nullable', 'string', self::ID],
            'versionId' => ['nullable', 'string', self::ID],
            'insurerId' => ['required', 'string', self::ID],
            'name' => ['required', 'string', 'max:255'],
            'insuranceType' => ['required', Rule::in(Format::INSURANCE_TYPES)],
            'workflowStatus' => ['nullable', Rule::in(['draft', 'in_review', 'published', 'archived'])],
            'effectiveFrom' => $date,
            'effectiveTo' => $date,
            'reviewDueAt' => $date,
            'notes' => $long,
            'scopeType' => ['nullable', Rule::in(['specific_model', 'all_models', 'unknown'])],
            'vehicleModelId' => ['nullable', 'string', self::ID, 'required_if:scopeType,specific_model'],
            'minCarAge' => ['nullable', 'integer', 'min:0', 'max:150'],
            'maxCarAge' => ['nullable', 'integer', 'min:0', 'max:150'],
            'ageBasis' => ['nullable', Rule::in(['registration_year', 'manufacture_year'])],
            'usageType' => ['nullable', Rule::in(['private', 'commercial', 'all'])],
            'registrationZone' => ['nullable', Rule::in(['all', 'bangkok_only', 'bangkok_metro', 'upcountry_only'])],
            'inspectionRequired' => ['nullable', 'boolean'],
            'priceOptions' => ['nullable', 'array', 'max:100'],
            // Client-side tier keys are allowed here: the store only honours ids already on this rule.
            'priceOptions.*.id' => ['nullable', 'string', 'max:64'],
            'priceOptions.*.label' => $short,
            'priceOptions.*.repairType' => ['required', Rule::in(['dealer', 'garage'])],
            'priceOptions.*.priceType' => ['nullable', Rule::in(['starting', 'indicative', 'fixed_for_conditions', 'unknown'])],
            'priceOptions.*.premiumTotal' => self::AMOUNT,
            'priceOptions.*.sumInsuredMin' => self::AMOUNT,
            'priceOptions.*.sumInsuredMax' => self::AMOUNT,
            'priceOptions.*.deductibleAmount' => self::AMOUNT,
            'priceOptions.*.deductibleTerms' => $long,
            'priceOptions.*.driverConditions' => $long,
            'priceOptions.*.compulsoryIncluded' => ['nullable', Rule::in(['included', 'excluded', 'unspecified'])],
            'priceOptions.*.validFrom' => $date,
            'priceOptions.*.validTo' => $date,
            'coverages' => ['nullable', 'array', 'max:100'],
            'coverages.*.code' => ['required', 'string', 'max:64'],
            'coverages.*.status' => ['required', Rule::in(['covered', 'not_covered', 'optional', 'unknown'])],
            'coverages.*.limitAmount' => self::AMOUNT,
            'coverages.*.limitBasis' => $short,
            'coverages.*.terms' => $long,
            'coverages.*.exclusions' => $long,
            'sourceType' => ['nullable', Rule::in(['policy_schedule', 'rate_sheet', 'brochure', 'endorsement', 'manual_note', 'other'])],
            'documentTitle' => $short,
            'documentRef' => $short,
            'documentStoragePath' => ['nullable', 'string', 'max:1024', 'regex:/^(?!\/)(?!.*\.\.)[\w.\/-]+$/'],
            // Rendered as a link on the plan page, so anything else (javascript:) must not get through.
            'documentSourceUrl' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/\S+|\/documents\/[\w.-]+)$/i'],
            'documentFileSize' => ['nullable', 'integer', 'min:0'],
            'documentMimeType' => $short,
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'กรุณากรอกชื่อแพ็กเกจประกัน',
            'insurerId.required' => 'กรุณาเลือกบริษัทประกันภัย',
            '*.regex' => 'รหัสอ้างอิงไม่ถูกต้อง',
            'documentStoragePath.regex' => 'ตำแหน่งไฟล์เอกสารไม่ถูกต้อง',
            'documentSourceUrl.regex' => 'รูปแบบ URL ไม่ถูกต้อง (ต้องขึ้นต้นด้วย http:// หรือ https://)',
            '*.min' => 'อายุรถต้องไม่ติดลบ',
            'vehicleModelId.required_if' => 'กรุณาเลือกรุ่นรถสำหรับเงื่อนไขระบุรุ่นเฉพาะ',
            'priceOptions.*.*.min' => 'จำนวนเงินต้องไม่ติดลบ',
            'priceOptions.*.*.max' => 'จำนวนเงินเกินกำหนด',
            'priceOptions.max' => 'ตัวเลือกราคามากเกินไป',
            'coverages.*.*.min' => 'จำนวนเงินต้องไม่ติดลบ',
            'coverages.max' => 'รายการความคุ้มครองมากเกินไป',
        ];
    }

    /** Pairs where the second bound must not be below the first; either side may be empty. */
    private static function rangeChecks(\Illuminate\Validation\Validator $validator): void
    {
        $data = $validator->getData();
        $below = fn ($low, $high) => $low !== null && $low !== '' && $high !== null && $high !== '' && $high < $low;
        if ($below($data['effectiveFrom'] ?? null, $data['effectiveTo'] ?? null)) {
            $validator->errors()->add('effectiveTo', 'วันสิ้นสุดผลบังคับใช้ต้องไม่ก่อนวันเริ่มต้น');
        }
        if ($below($data['minCarAge'] ?? null, $data['maxCarAge'] ?? null)) {
            $validator->errors()->add('maxCarAge', 'อายุรถสูงสุดต้องไม่น้อยกว่าอายุรถขั้นต่ำ');
        }
        foreach ((array) ($data['priceOptions'] ?? []) as $i => $po) {
            if ($below($po['sumInsuredMin'] ?? null, $po['sumInsuredMax'] ?? null)) {
                $validator->errors()->add("priceOptions.{$i}.sumInsuredMax", 'ทุนประกันสูงสุดต้องไม่น้อยกว่าทุนประกันขั้นต่ำ');
            }
            if ($below($po['validFrom'] ?? null, $po['validTo'] ?? null)) {
                $validator->errors()->add("priceOptions.{$i}.validTo", 'วันสิ้นสุดของราคาต้องไม่ก่อนวันเริ่มต้น');
            }
        }
    }

    /**
     * @return array{plan: \App\Models\Plan, version: \App\Models\PlanVersion}
     *
     * @throws StoreException with the first validation message or the refusal
     */
    public static function handle(User $user, array $input): array
    {
        Gate::forUser($user)->authorize('canEditDraft');

        $validator = Validator::make($input, self::rules(), self::messages())->after(self::rangeChecks(...));
        if ($validator->fails()) {
            throw new StoreException($validator->errors()->first());
        }
        // Keep only keys the caller sent: a missing key and an explicit null mean different things to the store.
        $payload = array_intersect_key($validator->validated(), $input);

        $canPublish = $user->can('canPublish');
        $status = $payload['workflowStatus'] ?? ($canPublish ? 'published' : 'draft');
        if (! $canPublish && ! in_array($status, ['draft', 'in_review'], true)) {
            throw new StoreException('คุณไม่มีสิทธิ์เผยแพร่แพ็กเกจ');
        }

        // Section 8.4: nothing is published without a source document.
        if ($status === 'published') {
            $hasDocument = trim((string) ($payload['documentTitle'] ?? '')) !== ''
                || ! empty($payload['documentStoragePath']) || ! empty($payload['documentSourceUrl'])
                || (! empty($payload['versionId']) && SourceDocument::where('plan_version_id', $payload['versionId'])->exists());
            if (! $hasDocument) {
                throw new StoreException(InsuranceStore::NO_DOCUMENT_MESSAGE);
            }
        }

        return InsuranceStore::createOrUpdatePlan(['workflowStatus' => $status] + $payload, $user, unpublishedOnly: ! $canPublish);
    }
}
