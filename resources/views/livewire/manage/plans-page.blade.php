<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2"><x-lucide-file-text class="w-6 h-6 text-blue-600" /><span>จัดการแพ็กเกจและข้อมูลร่าง (Plans Management)</span></h1>
            <p class="text-xs text-slate-500 mt-1">บันทึก แก้ไขข้อมูลแพ็กเกจ รุ่นเงื่อนไข ราคา และเอกสารอ้างอิง</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-1.5 text-xs text-slate-600 font-medium cursor-pointer bg-white border border-slate-200 px-3 py-2 rounded-xl">
                <input type="checkbox" wire:model.live="showArchived" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"><span>แสดงแพ็กเกจในกรุ (Archived)</span>
            </label>
            <button type="button" wire:click="openForm" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition cursor-pointer">
                <x-lucide-plus class="w-4 h-4" /><span>สร้างแพ็กเกจใหม่</span>
            </button>
        </div>
    </div>

    @if ($notice)
        <div role="status" class="bg-blue-50 border border-blue-200 text-blue-900 text-xs font-medium px-4 py-3 rounded-xl flex items-center justify-between">
            <span>{{ $notice }}</span>
            <button type="button" wire:click="$set('notice', null)" class="text-blue-700 hover:underline font-bold cursor-pointer">ปิด</button>
        </div>
    @endif

    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden" wire:loading.class="opacity-60">
        @if ($plans->isEmpty())
            <div class="p-12 text-center text-sm text-slate-500">ไม่มีรายการแพ็กเกจในมุมมองนี้</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                            <th class="p-4">บริษัทประกัน</th><th class="p-4">ชื่อแพ็กเกจ</th><th class="p-4">ประเภท</th>
                            <th class="p-4">เวอร์ชันล่าสุด</th><th class="p-4">สถานะการทำงาน</th><th class="p-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($plans as $plan)
                            <tr wire:key="plan-{{ $plan->id }}" class="hover:bg-slate-50/60 transition {{ $plan->is_archived ? 'opacity-65 bg-slate-50/40' : '' }}">
                                <td class="p-4 font-bold text-slate-800">{{ $plan->insurer?->name ?? 'ไม่ระบุ' }}</td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-900">{{ $plan->name }}</div>
                                    <div class="text-[11px] text-slate-500">ID: {{ substr($plan->id, 0, 8) }}...</div>
                                </td>
                                <td class="p-4 font-semibold text-slate-700">{{ $plan->insurance_type }}</td>
                                <td class="p-4">
                                    <span class="font-semibold text-slate-800">รุ่นที่ {{ $plan->latestVersionNo }}</span>
                                    <span class="text-slate-500 text-[10px] block">({{ $plan->versions->count() }} รุ่น)</span>
                                </td>
                                <td class="p-4">
                                    @if ($plan->is_archived)
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-md font-bold bg-slate-200 text-slate-700"><x-lucide-archive class="w-3 h-3" />จัดเก็บเข้ากรุ (Archived)</span>
                                    @elseif ($plan->currentStatus === 'published')
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-md font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"><x-lucide-check-circle-2 class="w-3 h-3" />เผยแพร่แล้ว (Published)</span>
                                    @elseif ($plan->currentStatus === 'in_review')
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-md font-bold bg-amber-50 text-amber-800 border border-amber-200"><x-lucide-clock class="w-3 h-3" />รอตรวจสอบ (In Review)</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-md font-bold bg-slate-100 text-slate-700">ข้อมูลร่าง (Draft)</span>
                                    @endif
                                </td>
                                <td class="p-4 text-right space-x-1.5 whitespace-nowrap">
                                    @if (! $plan->is_archived && $plan->currentStatus === 'draft')
                                        <button type="button" wire:click="submitForReview('{{ $plan->id }}')" title="ส่งให้ผู้ตรวจสอบอนุมัติ"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 font-semibold cursor-pointer">
                                            <x-lucide-send class="w-3.5 h-3.5 text-amber-700" /><span>ส่งตรวจ</span>
                                        </button>
                                    @endif
                                    @unless ($plan->is_archived)
                                        <button type="button" wire:click="openForm('{{ $plan->id }}')" title="แก้ไขข้อมูลแพ็กเกจ"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-semibold cursor-pointer">
                                            <x-lucide-edit-3 class="w-3.5 h-3.5 text-blue-600" /><span>แก้ไข</span>
                                        </button>
                                    @endunless
                                    <button type="button" wire:click="openForm('{{ $plan->id }}', true)" title="คัดลอกแพ็กเกจนี้เพื่อสร้างแพ็กเกจใหม่"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50/70 text-emerald-800 hover:bg-emerald-100 font-semibold cursor-pointer">
                                        <x-lucide-copy class="w-3.5 h-3.5 text-emerald-600" /><span>คัดลอก</span>
                                    </button>
                                    <a href="{{ url("/plans/{$plan->id}") }}" title="ดูรายละเอียดฉบับเต็ม"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 text-blue-600 hover:bg-blue-50 font-semibold">
                                        <span>เปิดดู</span><x-lucide-external-link class="w-3.5 h-3.5" />
                                    </a>
                                    @if ($plan->is_archived)
                                        <button type="button" wire:click="restore('{{ $plan->id }}')" title="กู้คืนแพ็กเกจกลับมาใช้งาน"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 font-semibold cursor-pointer">
                                            <x-lucide-rotate-ccw class="w-3.5 h-3.5" /><span>กู้คืน</span>
                                        </button>
                                    @else
                                        @php $published = $plan->currentStatus === 'published'; @endphp
                                        <button type="button" wire:click="delete('{{ $plan->id }}')"
                                                wire:confirm="{{ $published ? "แพ็กเกจ \"{$plan->name}\" มีประวัติเผยแพร่แล้ว ระบบจะจัดเก็บเข้ากรุ (Archive) แทนการลบทิ้งตามกฎ Section 8.1 ต้องการดำเนินการหรือไม่?" : "คุณแน่ใจหรือไม่ว่าต้องการลบแพ็กเกจร่าง \"{$plan->name}\"?" }}"
                                                title="{{ $published ? 'จัดเก็บแพ็กเกจเข้ากรุ (Archive)' : 'ลบแพ็กเกจร่าง' }}" aria-label="{{ $published ? 'จัดเก็บแพ็กเกจเข้ากรุ (Archive)' : 'ลบแพ็กเกจร่าง' }}"
                                                class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg border border-red-100 text-red-600 hover:bg-red-50 cursor-pointer">
                                            <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <livewire:plan-form />
</div>
