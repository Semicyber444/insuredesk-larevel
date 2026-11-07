<?php

namespace App\Livewire\Manage;

use App\Exceptions\StoreException;
use App\Models\PlanVersion;
use App\Services\InsuranceStore;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('คิวตรวจสอบ')]
class ReviewsPage extends Component
{
    /** The version being decided in the dialog, and 'publish' or 'reject'. */
    public ?string $versionId = null;

    public string $action = '';

    public string $reason = '';

    public string $error = '';

    public function mount(): void
    {
        $this->authorize('canReview');
    }

    public function ask(string $versionId, string $action): void
    {
        $this->versionId = $versionId;
        $this->action = $action === 'publish' ? 'publish' : 'reject';
        $this->reason = '';
        $this->error = '';
    }

    public function cancel(): void
    {
        $this->reset('versionId', 'action', 'reason', 'error');
    }

    public function confirm(): void
    {
        $reason = trim($this->reason);
        if ($reason === '' || mb_strlen($reason) > 2000 || ! $this->versionId) {
            $this->error = $reason === '' ? 'กรุณาระบุเหตุผลในการดำเนินการ' : 'เหตุผลยาวเกิน 2,000 ตัวอักษร';

            return;
        }
        $this->authorize($this->action === 'publish' ? 'canPublish' : 'canReview');
        try {
            $this->action === 'publish'
                ? InsuranceStore::publishPlanVersion($this->versionId, $reason, auth()->user())
                : InsuranceStore::rejectPlanVersion($this->versionId, $reason, auth()->user());
            $this->cancel();
        } catch (StoreException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.manage.reviews-page', [
            'reviews' => PlanVersion::with('plan.insurer')->withCount('documents')
                ->withCount(['rules as options_count' => fn ($q) => $q->join('price_options', 'price_options.eligibility_rule_id', '=', 'eligibility_rules.id')])
                ->where('workflow_status', 'in_review')
                ->whereHas('plan', fn ($q) => $q->where('is_archived', false))
                ->orderByDesc('created_at')->orderBy('id')->get(),
            'target' => $this->versionId ? PlanVersion::with('plan')->find($this->versionId) : null,
        ]);
    }
}
