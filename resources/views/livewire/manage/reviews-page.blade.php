<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2"><x-lucide-file-check class="w-6 h-6 text-blue-600" /><span>คิวตรวจสอบและเผยแพร่ข้อมูล (Review Queue)</span></h1>
        <p class="text-xs text-slate-500 mt-1">สำหรับบทบาทผู้ตรวจสอบ (Reviewer / Admin) ในการตรวจเทียบข้อมูลกับเอกสารต้นทางก่อนเผยแพร่ให้ทีมใช้งาน</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        @forelse ($reviews as $version)
            @if ($loop->first)<div class="divide-y divide-slate-100">@endif
            <div wire:key="rev-{{ $version->id }}" class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/50 transition">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-blue-800 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">{{ $version->plan->insurer?->name ?: 'ไม่ระบุบริษัท' }}</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $version->plan->insurance_type }}</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1"><x-lucide-clock class="w-3 h-3" />รอตรวจสอบ (In Review)</span>
                    </div>
                    <div class="flex items-center gap-2 pt-0.5">
                        <h3 class="text-base font-bold text-slate-900">{{ $version->plan->name }}</h3>
                        <a href="{{ url("/plans/{$version->plan_id}") }}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:underline"><span>ตรวจดูรายละเอียด</span><x-lucide-external-link class="w-3 h-3" /></a>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 pt-1">
                        <span>รุ่นที่: <strong>{{ $version->version_no }}</strong></span>
                        <span>ตัวเลือกราคา: <strong>{{ $version->options_count }} รายการ</strong></span>
                        <span class="{{ $version->documents_count === 0 ? 'text-red-600 font-semibold' : '' }}">เอกสารแนบ: <strong>{{ $version->documents_count }} ฉบับ</strong></span>
                        @if ($version->notes)<span class="text-slate-600 italic">"{{ $version->notes }}"</span>@endif
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" wire:click="ask('{{ $version->id }}', 'reject')" class="px-3.5 py-2 text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-x class="w-3.5 h-3.5" /><span>ส่งกลับแก้ไข</span>
                    </button>
                    @can('canPublish')
                        <button type="button" wire:click="ask('{{ $version->id }}', 'publish')" class="px-4 py-2 text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                            <x-lucide-check class="w-3.5 h-3.5" /><span>อนุมัติและเผยแพร่</span>
                        </button>
                    @endcan
                </div>
            </div>
            @if ($loop->last)</div>@endif
        @empty
            <div class="p-12 text-center">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3"><x-lucide-check class="w-6 h-6" /></div>
                <h3 class="text-sm font-bold text-slate-800">ไม่มีรายการค้างตรวจในระบบ</h3>
                <p class="text-xs text-slate-500 mt-1">ข้อมูลแพ็กเกจทั้งหมดได้รับการตรวจสอบและเผยแพร่เรียบร้อยแล้ว</p>
            </div>
        @endforelse
    </div>

    @if ($target)
        @php $publish = $action === 'publish'; @endphp
        <dialog wire:ignore.self x-data x-init="$el.showModal()" @cancel.prevent="$wire.cancel()" aria-labelledby="review-modal-title"
                class="fixed inset-0 m-0 h-full w-full max-h-none max-w-none border-0 text-slate-900 backdrop:bg-transparent flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4">
            <form wire:submit="confirm" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $publish ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        @if ($publish) <x-lucide-check class="w-4 h-4" /> @else <x-lucide-x class="w-4 h-4" /> @endif
                    </div>
                    <h3 id="review-modal-title" class="text-base font-bold text-slate-900">{{ $publish ? 'ยืนยันการเผยแพร่ข้อมูล' : 'ส่งกลับให้ผู้กรอกแก้ไข' }}</h3>
                </div>
                @if ($error)
                    <div role="alert" class="p-2.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2"><x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" /><span>{{ $error }}</span></div>
                @endif
                <p class="text-xs text-slate-600">แพ็กเกจ: <strong>{{ $target->plan->name }}</strong></p>
                <div class="space-y-1.5">
                    <label for="review-reason" class="text-xs font-semibold text-slate-700">เหตุผลและบันทึกการตรวจสอบ (จะถูกบันทึกลงใน Audit Log):</label>
                    <textarea id="review-reason" rows="3" wire:model="reason"
                              placeholder="{{ $publish ? 'เช่น ตรวจสอบตารางเบี้ยและเงื่อนไขตรงตามเอกสารต้นทางครบถ้วน' : 'เช่น ข้อมูลทุนประกันไม่ตรงกับหน้า 3 ของเอกสาร กรุณาแก้ไข' }}"
                              class="w-full text-xs p-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="cancel" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer">ยกเลิก</button>
                    <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 text-xs font-bold text-white rounded-xl shadow-xs transition cursor-pointer {{ $publish ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-red-600 hover:bg-red-700' }} disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="confirm">ยืนยัน</span><span wire:loading wire:target="confirm">กำลังดำเนินการ...</span>
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</div>
