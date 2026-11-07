<?php

namespace App\Livewire\Manage;

use App\Exceptions\StoreException;
use App\Models\Plan;
use App\Services\InsuranceStore;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('จัดการแพ็กเกจ')]
class PlansPage extends Component
{
    public bool $showArchived = false;

    public ?string $notice = null;

    public bool $copying = false;

    public function mount(): void
    {
        $this->authorize('canEditDraft');
    }

    public function submitForReview(string $planId): void
    {
        $this->authorize('canSubmitReview');
        $plan = Plan::findOrFail($planId);
        $version = $this->activeVersion($plan);
        if (! $version) {
            return;
        }
        try {
            InsuranceStore::submitForReview($version->id, "ส่งตรวจสอบแพ็กเกจ \"{$plan->name}\" รุ่นที่ {$version->version_no}", auth()->user());
            $this->notice = "ส่งแพ็กเกจ \"{$plan->name}\" เข้าสู่คิวตรวจสอบเรียบร้อยแล้ว";
        } catch (StoreException $e) {
            $this->notice = $e->getMessage();
        }
    }

    public function delete(string $planId): void
    {
        $this->authorize('canEditDraft');
        $name = Plan::whereKey($planId)->value('name');
        $mode = InsuranceStore::deletePlan($planId, auth()->user());
        $this->notice = $mode === 'archived'
            ? "จัดเก็บแพ็กเกจ \"{$name}\" เข้ากรุ (Archived) เรียบร้อยแล้ว"
            : "ลบแพ็กเกจร่าง \"{$name}\" เรียบร้อยแล้ว";
    }

    public function restore(string $planId): void
    {
        $this->authorize('canEditDraft');
        InsuranceStore::restorePlan($planId, auth()->user());
        $this->notice = 'กู้คืนแพ็กเกจ "'.Plan::whereKey($planId)->value('name').'" กลับมาใช้งานเรียบร้อยแล้ว';
    }

    public function openForm(?string $planId = null, bool $copy = false): void
    {
        $this->copying = $copy;
        $this->dispatch('open-plan-form', planId: $planId, copy: $copy);
    }

    #[On('plan-saved')]
    public function planSaved(): void
    {
        if ($this->copying) {
            $this->notice = 'คัดลอกและสร้างแพ็กเกจประกันภัยใหม่เรียบร้อยแล้ว';
        }
        $this->copying = false;
    }

    /** The published version, or the newest one when nothing is published. */
    private function activeVersion(Plan $plan)
    {
        $versions = $plan->versions;

        return $versions->firstWhere('workflow_status', 'published') ?? $versions->first();
    }

    public function render()
    {
        $plans = Plan::with(['insurer', 'versions'])
            ->when(! $this->showArchived, fn ($q) => $q->where('is_archived', false))
            ->orderByDesc('created_at')->orderBy('id')->get()
            ->map(function (Plan $plan) {
                $active = $this->activeVersion($plan);
                $plan->currentStatus = $active?->workflow_status ?? 'draft';
                $plan->latestVersionNo = $active?->version_no ?? 1;

                return $plan;
            });

        return view('livewire.manage.plans-page', ['plans' => $plans]);
    }
}
