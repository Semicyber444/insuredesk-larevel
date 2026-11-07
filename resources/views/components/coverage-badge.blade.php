@props(['label', 'status' => 'unknown', 'covered' => 'bg-emerald-50 text-emerald-800 border border-emerald-200'])
{{-- Section 9.5: never render 'optional' or 'unknown' as 'ไม่คุ้มครอง' --}}
@switch($status ?? 'unknown')
    @case('covered')
        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded font-medium {{ $covered }}"><x-lucide-check-circle-2 class="w-3 h-3" />{{ $label }}: คุ้มครอง</span>
        @break
    @case('optional')
        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded font-medium bg-amber-50 text-amber-800 border border-amber-200"><x-lucide-plus class="w-3 h-3" />{{ $label }}: ซื้อเพิ่มได้</span>
        @break
    @case('not_covered')
        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded font-medium bg-slate-100 text-slate-600"><x-lucide-minus-circle class="w-3 h-3" />{{ $label }}: ไม่คุ้มครอง</span>
        @break
    @default
        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded font-medium bg-slate-50 text-slate-600 border border-slate-200"><x-lucide-help-circle class="w-3 h-3" />{{ $label }}: ยังไม่ระบุ</span>
@endswitch
