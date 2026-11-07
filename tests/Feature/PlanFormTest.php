<?php

namespace Tests\Feature;

use App\Livewire\PlanForm;
use App\Models\Plan;
use App\Models\PriceOption;
use App\Models\SourceDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlanFormTest extends TestCase
{
    public function test_creates_a_plan_with_an_uploaded_document(): void
    {
        Storage::fake('local');
        $this->actingAs($this->demoUser('admin'));

        $form = Livewire::test(PlanForm::class)->call('openForm')
            ->set('name', 'แพ็กเกจจากฟอร์ม')
            ->set('upload', UploadedFile::fake()->create('rates.pdf', 100, 'application/pdf'))
            ->assertSet('documentTitle', 'rates')
            ->call('addTier')
            ->call('save')
            ->assertSet('error', '')
            ->assertDispatched('plan-saved');

        $plan = Plan::where('name', 'แพ็กเกจจากฟอร์ม')->firstOrFail();
        $version = $plan->versions()->first();
        $this->assertSame('published', $version->workflow_status);
        $this->assertSame(2, PriceOption::whereHas('rule', fn ($q) => $q->where('plan_version_id', $version->id))->count());
        $doc = SourceDocument::where('plan_version_id', $version->id)->firstOrFail();
        $this->assertSame($form->get('documentStoragePath'), $doc->storage_path);
        Storage::disk('local')->assertExists($doc->storage_path);
        $this->assertStringStartsWith('/documents/', $doc->source_url);
    }

    public function test_rejects_an_upload_of_the_wrong_type(): void
    {
        Storage::fake('local');
        $this->actingAs($this->demoUser('editor'));
        Livewire::test(PlanForm::class)->call('openForm')
            ->set('upload', UploadedFile::fake()->create('evil.php', 1))
            ->assertHasErrors('upload')
            ->assertSet('documentStoragePath', null);
    }

    public function test_editing_a_draft_in_place_keeps_its_price_option_ids(): void
    {
        $this->actingAs($this->demoUser('editor'));
        Livewire::test(PlanForm::class)->call('openForm')->set('name', 'ร่างทดสอบ')->set('workflowStatus', 'draft')->call('save');
        $plan = Plan::where('name', 'ร่างทดสอบ')->firstOrFail();
        $optionsOf = fn () => PriceOption::whereHas('rule.version', fn ($q) => $q->where('plan_id', $plan->id))->pluck('id')->all();
        $before = $optionsOf();

        Livewire::test(PlanForm::class)->call('openForm', $plan->id)
            ->assertSet('saveMode', 'update')
            ->set('tiers.0.premiumTotal', 9999)
            ->call('save')->assertSet('error', '');

        $this->assertSame(1, $plan->versions()->count());
        $this->assertSame($before, $optionsOf());
        $this->assertSame(9999.0, PriceOption::find($before[0])->premium_total);
    }

    public function test_a_generated_tier_label_follows_the_sum_insured(): void
    {
        $this->actingAs($this->demoUser('admin'));
        Livewire::test(PlanForm::class)->call('openForm')
            ->set('tiers.0.sumInsuredMax', 120000)->assertSet('tiers.0.label', 'ซ่อมอู่ - ทุน 120,000')
            ->set('tiers.0.label', 'ชื่อที่ตั้งเอง')->set('tiers.0.sumInsuredMax', 150000)->assertSet('tiers.0.label', 'ชื่อที่ตั้งเอง');
    }
}
