<?php

namespace Tests\Feature;

use App\Actions\SavePlan;
use App\Exceptions\StoreException;
use App\Livewire\Login;
use App\Livewire\Manage\InsurersPage;
use App\Models\PlanVersion;
use App\Models\User;
use App\Services\InsuranceStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Ported from tests/unit/security_rules.test.ts. */
class SecurityTest extends TestCase
{
    private const PUBLISHED_VERSION = '50000000-0000-0000-0000-000000000001';

    private function plan(array $extra = []): array
    {
        return $extra + ['insurerId' => self::INSURER_ID, 'name' => 'แพ็กเกจทดสอบความปลอดภัย', 'insuranceType' => 'ชั้น 1'];
    }

    private function statusOf(string $versionId): string
    {
        return PlanVersion::findOrFail($versionId)->workflow_status;
    }

    // Workflow status guards

    public function test_a_published_version_cannot_be_pulled_back_through_submit_or_reject(): void
    {
        foreach (['submitForReview', 'rejectPlanVersion'] as $method) {
            try {
                InsuranceStore::$method(self::PUBLISHED_VERSION, 'x', null);
                $this->fail("{$method} should refuse");
            } catch (StoreException) {
            }
        }
        $this->assertSame('published', $this->statusOf(self::PUBLISHED_VERSION));
    }

    public function test_only_a_version_in_review_is_published(): void
    {
        ['version' => $v] = SavePlan::handle($this->demoUser('admin'), $this->plan(['workflowStatus' => 'draft', 'documentTitle' => 'เอกสาร']));
        $this->expectRefusal(fn () => InsuranceStore::publishPlanVersion($v->id, 'x', null));
        $this->assertSame('draft', $this->statusOf($v->id));

        InsuranceStore::submitForReview($v->id, 'x', null);
        InsuranceStore::publishPlanVersion($v->id, 'x', null);
        $this->assertSame('published', $this->statusOf($v->id));
    }

    public function test_the_form_refuses_to_publish_without_a_source_document(): void
    {
        $this->expectRefusal(fn () => SavePlan::handle($this->demoUser('admin'), $this->plan(['workflowStatus' => 'published'])), 'Section 8.4');
    }

    // Server-side input validation

    public function test_rejects_a_javascript_document_link_and_insurer_website(): void
    {
        $this->expectRefusal(fn () => SavePlan::handle($this->demoUser('admin'),
            $this->plan(['workflowStatus' => 'draft', 'documentSourceUrl' => 'javascript:alert(1)'])));

        $this->actingAs($this->demoUser('admin'));
        Livewire::test(InsurersPage::class)->call('create')->set('name', 'x')->set('website', 'javascript:alert(1)')
            ->call('save')->assertSet('error', 'รูปแบบ URL ไม่ถูกต้อง (ต้องขึ้นต้นด้วย http:// หรือ https://)');
    }

    public function test_rejects_bad_ranges_and_negative_amounts(): void
    {
        foreach ([
            ['effectiveFrom' => '2026-12-31', 'effectiveTo' => '2026-01-01'],
            ['minCarAge' => 5, 'maxCarAge' => 1],
            ['priceOptions' => [['repairType' => 'garage', 'premiumTotal' => -1]]],
            ['scopeType' => 'specific_model'],
            ['priceOptions' => [['repairType' => 'garage', 'sumInsuredMin' => 500000, 'sumInsuredMax' => 300000]]],
        ] as $extra) {
            $this->expectRefusal(fn () => SavePlan::handle($this->demoUser('admin'), $this->plan(['workflowStatus' => 'draft'] + $extra)));
        }
    }

    public function test_treats_non_string_ids_as_invalid(): void
    {
        $this->expectRefusal(fn () => SavePlan::handle($this->demoUser('admin'), $this->plan(['planId' => ['id' => 1]])));
        $this->expectRefusal(fn () => SavePlan::handle($this->demoUser('admin'), $this->plan(['insurerId' => "1' OR '1'='1"])));
    }

    // Document access

    public function test_draft_documents_are_hidden_from_viewers_but_not_editors(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/draft.pdf', 'draft');
        Storage::disk('local')->put('documents/published.pdf', 'published');
        InsuranceStore::createOrUpdatePlan($this->plan(['workflowStatus' => 'draft', 'documentStoragePath' => 'documents/draft.pdf']), null);
        InsuranceStore::createOrUpdatePlan($this->plan(['workflowStatus' => 'published', 'documentStoragePath' => 'documents/published.pdf']), null);

        $this->get('/documents/draft.pdf')->assertRedirect('/login');

        $this->actingAs($this->demoUser('viewer'));
        $this->get('/documents/draft.pdf')->assertNotFound();
        $this->get('/documents/published.pdf')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/documents/..%2F.env')->assertStatus(404);

        $this->actingAs($this->demoUser('editor'));
        $this->get('/documents/draft.pdf')->assertOk();
    }

    // Login

    private const EMAIL = 'locked.user@example.com';

    private const PASSWORD = 'correct-password-1';

    private function createUser(): User
    {
        $user = User::create(['email' => self::EMAIL, 'name' => 'ผู้ทดสอบ', 'password' => self::PASSWORD]);
        $user->roles()->create(['role' => 'viewer']);

        return $user->fresh();
    }

    private function login(string $email, string $password)
    {
        Auth::logout();

        return Livewire::test(Login::class)->set('email', $email)->set('password', $password)->call('login');
    }

    public function test_locks_after_repeated_failures_even_for_the_right_password(): void
    {
        $this->createUser();
        for ($i = 0; $i < 10; $i++) {
            $this->login(self::EMAIL, 'wrong')->assertSet('error', Login::INVALID);
        }
        $this->assertStringContainsString('หลายครั้งเกินไป', $this->login(self::EMAIL, self::PASSWORD)->get('error'));
        $this->assertGuest();
    }

    public function test_locks_unknown_emails_the_same_way(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->login('nobody@example.com', 'wrong');
        }
        $this->assertStringContainsString('หลายครั้งเกินไป', $this->login('nobody@example.com', 'wrong')->get('error'));
    }

    public function test_a_successful_login_clears_the_count(): void
    {
        $this->createUser();
        for ($round = 0; $round < 2; $round++) {
            for ($i = 0; $i < 9; $i++) {
                $this->login(self::EMAIL, 'wrong');
            }
            $this->login(self::EMAIL, self::PASSWORD)->assertRedirect('/search');
        }
    }

    public function test_failures_older_than_the_window_are_ignored(): void
    {
        $this->createUser();
        for ($i = 0; $i < 10; $i++) {
            $this->login(self::EMAIL, 'wrong');
        }
        $this->travel(16)->minutes();
        $this->login(self::EMAIL, self::PASSWORD)->assertRedirect('/search');
    }

    public function test_inactive_users_cannot_sign_in_or_keep_a_session(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->get('/search')->assertOk();
        $user->update(['is_active' => false]);
        $this->get('/search')->assertRedirect('/login');
        $this->login(self::EMAIL, self::PASSWORD)->assertSet('error', Login::INVALID);
    }

    public function test_demo_accounts_work_only_while_demo_auth_is_on(): void
    {
        config(['insuredesk.demo_auth' => true]);
        Livewire::test(Login::class)->call('demoLogin', 'constructor')->assertSet('error', 'ไม่ได้เปิดใช้งานบัญชีจำลอง');
        $this->assertGuest();
        Livewire::test(Login::class)->call('demoLogin', 'viewer')->assertRedirect('/search');

        config(['insuredesk.demo_auth' => false]);
        $this->actingAs($this->demoUser('admin'))->get('/search')->assertRedirect('/login');
        $this->login('admin@insuredesk.team', 'anything')->assertSet('error', Login::INVALID);
    }

    private function expectRefusal(callable $fn, ?string $contains = null): void
    {
        try {
            $fn();
            $this->fail('expected a refusal');
        } catch (StoreException $e) {
            if ($contains) {
                $this->assertStringContainsString($contains, $e->getMessage());
            }
            $this->addToAssertionCount(1);
        }
    }
}
