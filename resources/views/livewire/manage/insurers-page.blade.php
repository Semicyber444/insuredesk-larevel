@php $input = 'w-full text-xs p-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none'; @endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2"><x-lucide-building-2 class="w-6 h-6 text-blue-600" /><span>จัดการบริษัทประกันภัย (Insurers)</span></h1>
            <p class="text-xs text-slate-500 mt-1">ข้อมูลบริษัทและช่องทางติดต่อเพื่อประสานงานเช็คเบี้ย</p>
        </div>
        <button type="button" wire:click="create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition cursor-pointer self-start sm:self-auto">
            <x-lucide-plus class="w-4 h-4" /><span>เพิ่มบริษัทประกัน</span>
        </button>
    </div>

    @if ($notice)
        <div role="status" class="bg-amber-50 border border-amber-200 text-amber-900 text-xs font-medium px-4 py-3 rounded-xl flex items-center justify-between">
            <span>{{ $notice }}</span>
            <button type="button" wire:click="$set('notice', null)" class="text-amber-800 hover:underline font-bold cursor-pointer">ปิด</button>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($insurers as $ins)
            <div wire:key="ins-{{ $ins->id }}" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-3 flex flex-col justify-between {{ $ins->is_active ? '' : 'opacity-65 bg-slate-50/60' }}">
                <div>
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="text-base font-bold text-slate-900">{{ $ins->name }}</h3>
                        @if ($ins->is_active)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">เปิดใช้งาน</span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-700 border border-slate-300">ปิดใช้งาน</span>
                        @endif
                    </div>
                    <div class="space-y-1.5 text-xs text-slate-600">
                        <div class="flex items-center gap-2"><x-lucide-phone class="w-3.5 h-3.5 text-slate-500 shrink-0" /><span>{{ $ins->contact_channels ?: 'ไม่มีข้อมูลติดต่อ' }}</span></div>
                        @if ($ins->website)
                            <div class="flex items-center gap-2">
                                <x-lucide-globe class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                                <a href="{{ $ins->website }}" target="_blank" rel="noreferrer" class="text-blue-600 hover:underline flex items-center gap-1 truncate"><span>{{ $ins->website }}</span><x-lucide-external-link class="w-3 h-3" /></a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2 text-xs">
                    <button type="button" wire:click="edit('{{ $ins->id }}')" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-semibold cursor-pointer">
                        <x-lucide-edit-3 class="w-3.5 h-3.5 text-blue-600" /><span>แก้ไข</span>
                    </button>
                    <button type="button" wire:click="delete('{{ $ins->id }}')" wire:confirm="คุณแน่ใจหรือไม่ว่าต้องการลบหรือปิดการใช้งานบริษัท &quot;{{ $ins->name }}&quot;?"
                            title="ลบหรือปิดการใช้งาน" aria-label="ลบหรือปิดการใช้งาน" class="flex items-center gap-1 px-2 py-1.5 rounded-lg border border-red-100 text-red-600 hover:bg-red-50 cursor-pointer">
                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    @if ($editing)
        <dialog wire:ignore.self x-data x-init="$el.showModal()" @cancel.prevent="$wire.set('editing', false)" aria-labelledby="insurer-modal-title"
                class="fixed inset-0 m-0 h-full w-full max-h-none max-w-none border-0 text-slate-900 backdrop:bg-transparent flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4">
            <form wire:submit="save" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <h3 id="insurer-modal-title" class="text-base font-bold text-slate-900">{{ $insurerId ? 'แก้ไขข้อมูลบริษัทประกันภัย' : 'เพิ่มบริษัทประกันภัยใหม่' }}</h3>
                @if ($error)
                    <div role="alert" class="p-2.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2"><x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0" /><span>{{ $error }}</span></div>
                @endif
                <div class="space-y-1">
                    <label for="insurer-name" class="text-xs font-semibold text-slate-700">ชื่อบริษัทประกัน *</label>
                    <input id="insurer-name" type="text" required wire:model="name" placeholder="เช่น สินมั่นคงประกันภัย" class="{{ $input }}">
                </div>
                <div class="space-y-1">
                    <label for="insurer-contact" class="text-xs font-semibold text-slate-700">ช่องทางติดต่อ / Call Center</label>
                    <input id="insurer-contact" type="text" wire:model="contact" placeholder="เช่น โทร 1596 หรือ 0-2xxx-xxxx" class="{{ $input }}">
                </div>
                <div class="space-y-1">
                    <label for="insurer-website" class="text-xs font-semibold text-slate-700">เว็บไซต์ทางการ</label>
                    <input id="insurer-website" type="url" wire:model="website" placeholder="https://www.example.com" class="{{ $input }}">
                </div>
                @if ($insurerId)
                    <label class="flex items-center gap-2 text-xs text-slate-700 font-semibold cursor-pointer pt-1">
                        <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"><span>เปิดใช้งานบริษัทประกันนี้ในระบบค้นหา</span>
                    </label>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('editing', false)" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xs transition cursor-pointer">บันทึก</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
