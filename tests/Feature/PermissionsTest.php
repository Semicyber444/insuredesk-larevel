<?php

namespace Tests\Feature;

use App\Actions\SavePlan;
use App\Exceptions\StoreException;
use App\Livewire\Manage\AuditPage;
use App\Livewire\Manage\PlansPage;
use App\Livewire\Manage\ReviewsPage;
use App\Livewire\PlanDetailPage;
use App\Livewire\SearchPage;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Services\InsuranceStore;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Ported from tests/unit/action_permissions.test.ts: every write checks the role on the server. */
class PermissionsTest extends TestCase
{
    private const PLAN_ID = '40000000-0000-0000-0000-000000000001';

    private const VERSION_ID = '50000000-0000-0000-0000-000000000001';

    private function draft(array $extra = []): array
    {
        return $extra + ['insurerId' => self::INSURER_ID, 'name' => 'แพ็กเกจทดสอบสิทธิ์', 'insuranceType' => 'ชั้น 1'];
    }

    private function refused(callable $fn): void
    {
        try {
            $fn();
            $this->fail('expected a refusal');
        } catch (StoreException|AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/search', '/compare', '/plans/'.self::PLAN_ID, '/manage/plans', '/manage/audit'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_viewer_can_search_but_not_write_or_read_audit(): void
    {
        $viewer = $this->demoUser('viewer');
        $this->actingAs($viewer);
        Livewire::test(SearchPage::class)->assertOk();

        $this->refused(fn () => SavePlan::handle($viewer, $this->draft()));
        foreach (['/manage/plans', '/manage/reviews', '/manage/insurers', '/manage/users', '/manage/audit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        Livewire::test(AuditPage::class)->assertForbidden();
    }

    public function test_editor_cannot_publish_or_change_a_published_version(): void
    {
        $editor = $this->demoUser('editor');
        $this->refused(fn () => SavePlan::handle($editor, $this->draft(['workflowStatus' => 'published'])));
        $this->actingAs($editor)->get('/manage/reviews')->assertForbidden();
        $this->refused(fn () => SavePlan::handle($editor, $this->draft(['planId' => self::PLAN_ID, 'versionId' => self::VERSION_ID, 'workflowStatus' => 'draft'])));

        ['version' => $v] = SavePlan::handle($editor, $this->draft(['workflowStatus' => 'draft']));
        $this->assertSame('draft', $v->workflow_status);
    }

    public function test_editor_cannot_rename_a_published_plan_or_edit_a_version_under_review(): void
    {
        $editor = $this->demoUser('editor');
        $live = Plan::findOrFail(self::PLAN_ID);
        $same = ['planId' => $live->id, 'insurerId' => $live->insurer_id, 'name' => $live->name, 'insuranceType' => $live->insurance_type];

        $this->refused(fn () => SavePlan::handle($editor, ['name' => "{$live->name} (แก้)", 'workflowStatus' => 'draft'] + $same));
        $this->assertSame($live->name, $live->fresh()->name);

        ['version' => $draft] = SavePlan::handle($editor, ['workflowStatus' => 'draft'] + $same);
        SavePlan::handle($editor, ['versionId' => $draft->id, 'workflowStatus' => 'draft'] + $same);

        // Once submitted, the reviewer must see what gets published, so the editor can't change it.
        InsuranceStore::submitForReview($draft->id, 'x', $editor);
        $this->refused(fn () => SavePlan::handle($editor, ['versionId' => $draft->id, 'workflowStatus' => 'in_review'] + $same));
    }

    public function test_drafts_and_audit_are_hidden_from_viewers_but_not_admins(): void
    {
        ['plan' => $plan] = SavePlan::handle($this->demoUser('admin'), $this->draft(['workflowStatus' => 'draft']));

        $this->actingAs($this->demoUser('admin'));
        Livewire::test(PlanDetailPage::class, ['id' => $plan->id])
            ->assertSee('รุ่นที่ 1')->assertSee('สร้างแพ็กเกจใหม่');

        $this->actingAs($this->demoUser('viewer'));
        Livewire::test(PlanDetailPage::class, ['id' => $plan->id])
            ->assertSee('แพ็กเกจนี้ยังไม่เปิดเผยแพร่สำหรับฝ่ายขาย')->assertDontSee($plan->name);
    }

    public function test_viewer_never_sees_audit_on_a_published_plan(): void
    {
        $this->actingAs($this->demoUser('viewer'));
        Livewire::test(PlanDetailPage::class, ['id' => self::PLAN_ID])->assertSee('ยังไม่มีประวัติบันทึก');
    }

    public function test_viewer_cannot_call_write_actions_on_components(): void
    {
        $this->actingAs($this->demoUser('viewer'));
        Livewire::test(PlanDetailPage::class, ['id' => self::PLAN_ID])->call('submitForReview', self::VERSION_ID)->assertForbidden();
    }

    public function test_publishing_without_a_document_gives_the_domain_message(): void
    {
        $admin = $this->demoUser('admin');
        ['version' => $v] = SavePlan::handle($admin, $this->draft(['workflowStatus' => 'draft']));
        InsuranceStore::submitForReview($v->id, 'x', $admin);

        $this->actingAs($admin);
        Livewire::test(ReviewsPage::class)->call('ask', $v->id, 'publish')->set('reason', 'x')->call('confirm')
            ->assertSet('error', InsuranceStore::NO_DOCUMENT_MESSAGE);
        $this->assertSame('in_review', $v->fresh()->workflow_status);
    }

    public function test_plans_page_archives_published_and_deletes_drafts(): void
    {
        $this->actingAs($admin = $this->demoUser('admin'));
        ['plan' => $draft] = SavePlan::handle($admin, $this->draft(['workflowStatus' => 'draft']));

        Livewire::test(PlansPage::class)->call('delete', self::PLAN_ID)->call('delete', $draft->id);
        $this->assertTrue(Plan::find(self::PLAN_ID)->is_archived);
        $this->assertNull(Plan::find($draft->id));
    }
}
