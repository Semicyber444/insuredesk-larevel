<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2"><x-lucide-history class="w-6 h-6 text-blue-600" /><span>บันทึกประวัติการเปลี่ยนแปลง (Audit Logs)</span></h1>
        <p class="text-xs text-slate-500 mt-1">บันทึกการส่งตรวจ การเผยแพร่ การจัดเก็บเข้ากรุ และการแก้ไขทุกรายการเพื่อความโปร่งใสและตรวจสอบย้อนหลังได้ (เรียงจากล่าสุด ประวัติของแต่ละแพ็กเกจดูได้ในหน้าแพ็กเกจนั้นด้วย)</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        @if ($logs->isEmpty())
            <div class="p-12 text-center text-sm text-slate-500">ยังไม่มีประวัติการเปลี่ยนแปลง</div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($logs as $log)
                    <div wire:key="log-{{ $log->id }}" class="p-5 space-y-2 hover:bg-slate-50/50 transition">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs text-slate-500">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-800">{{ $log->actor_name ?: 'ระบบอัตโนมัติ' }}</span><span>•</span>
                                <span class="text-[11px] font-mono text-slate-500">ID: {{ $log->id }}</span>
                            </div>
                            <div class="flex items-center gap-1"><x-lucide-clock class="w-3.5 h-3.5" /><span>{{ App\Support\Format::thaiDateTime($log->created_at) }}</span></div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="font-bold px-2 py-0.5 rounded text-[11px] uppercase {{ match ($log->action) {
                                'publish', 'restore' => 'bg-emerald-100 text-emerald-800',
                                'reject', 'delete' => 'bg-red-100 text-red-800',
                                'archive', 'submit_review' => 'bg-amber-100 text-amber-900',
                                default => 'bg-blue-100 text-blue-800',
                            } }}">{{ $log->action }}</span>
                            <span class="font-semibold text-slate-700">เป้าหมาย: {{ $log->entity_type }} ({{ substr($log->entity_id, 0, 12) }}...)</span>
                        </div>
                        @if ($log->reason)
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-xs text-slate-700"><strong>เหตุผล:</strong> {{ $log->reason }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @if ($hasMore)
        <div class="flex justify-center">
            <button type="button" wire:click="loadMore" wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition cursor-pointer disabled:cursor-wait disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                <span wire:loading.remove wire:target="loadMore">โหลดเพิ่ม</span><span wire:loading wire:target="loadMore">กำลังโหลด...</span>
            </button>
        </div>
    @endif
</div>
