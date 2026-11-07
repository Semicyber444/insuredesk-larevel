<?php

namespace App\Livewire;

use App\Exceptions\StoreException;
use App\Livewire\Concerns\CompareBasket;
use App\Models\AuditLog;
use App\Models\CoverageType;
use App\Models\Plan;
use App\Models\VersionCoverage;
use App\Services\InsuranceStore;
use App\Services\SearchService;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class PlanDetailPage extends Component
{
    use CompareBasket;

    public string $planId;

    #[Url]
    public ?string $optionId = null;

    public ?string $versionId = null;

    public ?string $actionError = null;

    public function mount(string $id): void
    {
        $this->authorize('canViewPublished');
        $this->planId = $id;
    }

    private function canSeeUnpublished(): bool
    {
        $user = auth()->user();

        return $user->can('canEditDraft') || $user->can('canReview');
    }

    public function selectVersion(string $id): void
    {
        $this->versionId = $id;
        $this->optionId = null;
    }

    public function selectOption(string $id): void
    {
        $this->optionId = $id;
    }

    public function submitForReview(string $versionId): void
    {
        $this->authorize('canSubmitReview');
        $plan = Plan::findOrFail($this->planId);
        $version = $plan->versions()->whereKey($versionId)->firstOrFail();
        try {
            InsuranceStore::submitForReview($version->id, "ส่งตรวจสอบแพ็กเกจ \"{$plan->name}\" รุ่นที่ {$version->version_no}", auth()->user());
            $this->actionError = null;
        } catch (StoreException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    /** The plan form saved this plan, or a copy of it. */
    #[On('plan-saved')]
    public function planSaved(string $planId): void
    {
        if ($planId !== $this->planId) {
            $this->redirect("/plans/{$planId}");
        }
    }

    public function render()
    {
        $plan = Plan::with('insurer')->find($this->planId);
        if (! $plan) {
            return view('livewire.plan-missing');
        }

        // Sales viewers only ever see published data of plans that aren't archived.
        $restricted = ! $this->canSeeUnpublished();
        $versions = $plan->versions()->get();
        $published = $versions->where('workflow_status', 'published');
        if ($restricted && ($plan->is_archived || $published->isEmpty())) {
            return view('livewire.plan-restricted');
        }
        $visible = $restricted ? $published->values() : $versions;
        $active = $visible->firstWhere('id', $this->versionId) ?? $published->first() ?? $visible->first();

        $rules = $active ? $active->rules()->with('vehicleModel')->orderBy('created_at')->orderBy('id')->get() : collect();
        $options = $rules->flatMap(fn ($r) => $r->options)->values();
        $selected = $options->firstWhere('id', $this->optionId) ?? $options->first();
        $coverages = $active && $selected
            ? SearchService::resolveCoverages($active->id, CoverageType::orderBy('sort_order')->orderBy('id')->get(),
                VersionCoverage::where('plan_version_id', $active->id)->get()->groupBy('plan_version_id'))
            : [];

        return view('livewire.plan-detail-page', [
            'plan' => $plan,
            'versions' => $visible,
            'active' => $active,
            'rules' => $rules,
            'options' => $options,
            'selected' => $selected,
            'coverages' => $coverages,
            'documents' => $active ? $active->documents()->get() : collect(),
            'auditLogs' => auth()->user()->can('canViewAudit')
                ? AuditLog::whereIn('entity_id', [$plan->id, ...$versions->pluck('id')])->orderByDesc('created_at')->orderBy('id')->get()
                : collect(),
            'basket' => $this->basket(),
        ])->title($plan->name);
    }
}
