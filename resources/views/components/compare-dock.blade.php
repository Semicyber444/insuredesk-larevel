@props(['items'])
{{-- Sticky, not fixed: it rides the bottom of the screen and parks at the end of the results, so it never covers the footer. --}}
@if (count($items) > 0)
    <div class="sticky bottom-4 sm:bottom-6 z-40 mx-auto mt-6 w-full max-w-4xl">
        <div class="bg-slate-900 text-white rounded-2xl p-4 shadow-2xl border border-slate-700 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                <div class="flex items-center gap-2 pr-2 border-r border-slate-700 shrink-0">
                    <x-lucide-columns-3 class="w-5 h-5 text-blue-400" />
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-300">เลือกเปรียบเทียบ</span>
                        <span aria-live="polite" class="text-sm font-semibold text-white">{{ count($items) }}/4 รายการ</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @foreach ($items as $item)
                        <div wire:key="dock-{{ $item['id'] }}" class="flex items-center gap-1.5 bg-slate-800 text-slate-200 border border-slate-700 rounded-lg px-2.5 py-1 text-xs shrink-0">
                            <span class="font-semibold text-blue-300">{{ $item['insurerName'] }}</span>
                            <span class="truncate max-w-[120px]">{{ $item['planName'] }}</span>
                            <button type="button" wire:click="removeCompare('{{ $item['id'] }}')" title="นำออก" aria-label="นำ {{ $item['planName'] }} ออกจากการเปรียบเทียบ"
                                    class="hover:text-red-400 p-0.5 rounded transition cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-400">
                                <x-lucide-x class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0 w-full md:w-auto justify-end">
                <button type="button" wire:click="clearCompare" class="text-xs text-slate-300 hover:text-white px-3 py-2 rounded-lg transition cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-400">ล้างทั้งหมด</button>
                @if (count($items) >= 2)
                    <a href="{{ url('/compare?ids='.implode(',', array_column($items, 'id'))) }}"
                       class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-400">
                        <span>เปรียบเทียบ ({{ count($items) }})</span><x-lucide-arrow-right class="w-4 h-4" />
                    </a>
                @else
                    <button type="button" disabled class="bg-slate-800 text-slate-400 px-4 py-2 rounded-xl text-xs font-medium cursor-not-allowed">ต้องเลือก 2-4 รายการ</button>
                @endif
            </div>
        </div>
    </div>
@endif
