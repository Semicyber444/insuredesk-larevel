<?php

namespace App\Livewire\Manage;

use App\Models\AuditLog;
use App\Services\InsuranceStore;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('ประวัติ (Audit)')]
class AuditPage extends Component
{
    /** How many of the newest entries to show; "load more" adds a page. The log grows forever, so never all of it. */
    public int $limit = InsuranceStore::AUDIT_PAGE_SIZE;

    public function mount(): void
    {
        $this->authorize('canViewAudit');
    }

    public function loadMore(): void
    {
        $this->limit += InsuranceStore::AUDIT_PAGE_SIZE;
    }

    public function render()
    {
        // One extra row tells the page whether there is more to load.
        $rows = AuditLog::orderByDesc('created_at')->orderBy('id')->limit($this->limit + 1)->get();

        return view('livewire.manage.audit-page', ['logs' => $rows->take($this->limit), 'hasMore' => $rows->count() > $this->limit]);
    }
}
