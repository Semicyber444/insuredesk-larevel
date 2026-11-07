@props(['item', 'selected' => false, 'canSelectMore' => true])
@php
    use App\Support\Format;
    $compulsory = match ($item['compulsoryIncluded']) { 'included' => 'รวม พ.ร.บ.', 'unspecified' => 'ยังไม่ระบุ พ.ร.บ.', default => 'ไม่รวม พ.ร.บ.' };
    $cov = collect($item['coverages'])->keyBy('code');
    $blocked = ! $selected && ! $canSelectMore;
@endphp
<div class="relative bg-white border rounded-xl p-5 shadow-xs transition hover:shadow-md {{ $selected ? 'border-blue-600 ring-2 ring-blue-100' : 'border-slate-200 hover:border-slate-300' }}">
    <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-blue-900 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">{{ $item['insurerName'] }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $item['insuranceType'] }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $item['repairType'] === 'dealer' ? 'bg-purple-50 text-purple-700 border border-purple-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100' }}">
                    {{ $item['repairType'] === 'dealer' ? 'ซ่อมห้าง (ศูนย์)' : 'ซ่อมอู่' }}
                </span>
                @if (($item['registrationZone'] ?? 'all') !== 'all')
                    <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-300">
                        <x-lucide-map-pin class="w-3 h-3 text-amber-700" />{{ Format::registrationZone($item['registrationZone'], true) }}
                    </span>
                @endif
            </div>
            <h3 class="text-base font-bold text-slate-900 mt-1.5 line-clamp-2 sm:line-clamp-1">{{ $item['planName'] }}</h3>
            <p class="text-xs text-slate-500 line-clamp-1">{{ $item['priceOptionLabel'] }}</p>
        </div>

        <button type="button" wire:click="toggleCompare('{{ $item['id'] }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                @disabled($blocked) @if ($blocked) title="เลือกครบ 4 รายการแล้ว" @endif
                class="shrink-0 whitespace-nowrap flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 {{ $selected ? 'bg-blue-600 text-white shadow-xs' : ($blocked ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : 'bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-700') }}">
            @if ($selected)
                <x-lucide-check class="w-3.5 h-3.5" /><span>เลือกเปรียบเทียบแล้ว</span>
            @else
                <x-lucide-plus class="w-3.5 h-3.5" /><span>เปรียบเทียบ</span>
            @endif
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 py-4 border-b border-slate-100">
        <div>
            <span class="text-xs font-semibold text-slate-500 block mb-1">เบี้ยประกันรวม</span>
            @if ($item['premiumTotal'] === null)
                <div class="flex flex-col">
                    <span class="text-sm font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 w-fit">ยังไม่มีข้อมูลราคา</span>
                    <span class="text-[11px] text-slate-500 mt-0.5">ต้องติดต่อบริษัทเพื่อเช็คเบี้ย • {{ $compulsory }}</span>
                </div>
            @else
                <div class="flex flex-col">
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-black text-slate-900 tracking-tight">฿{{ Format::number($item['premiumTotal']) }}</span>
                        <span class="text-xs text-slate-500 font-medium">/ปี</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] text-slate-500 mt-0.5">
                        @if ($item['priceType'] === 'starting')<span class="bg-slate-100 text-slate-600 px-1.5 rounded font-medium">ราคาเริ่มต้น</span>@endif
                        @if ($item['priceType'] === 'indicative')<span class="bg-slate-100 text-slate-600 px-1.5 rounded font-medium">ราคาประมาณการ</span>@endif
                        <span>{{ $compulsory }}</span>
                    </div>
                </div>
            @endif
        </div>

        <div>
            <span class="text-xs font-semibold text-slate-500 block mb-1">ทุนประกันภัย</span>
            <div class="text-lg font-bold text-slate-800">
                @if ($item['sumInsuredMax'] ?: $item['sumInsuredMin'])
                    ฿{{ Format::number($item['sumInsuredMax'] ?: $item['sumInsuredMin']) }}
                @else
                    <span class="text-slate-500 text-sm">ยังไม่ระบุทุน</span>
                @endif
            </div>
            <span class="text-[11px] text-slate-500">
                @if ($item['scopeType'] === 'specific_model')
                    เฉพาะ {{ trim(($item['vehicleBrand'] ?? '').' '.($item['vehicleModel'] ?? '')) }}
                @elseif ($item['scopeType'] === 'unknown')
                    ยังไม่ระบุขอบเขตรุ่นรถ
                @else
                    รองรับรถอายุ {{ Format::carAgeRange($item['minCarAge'], $item['maxCarAge']) }}
                @endif
            </span>
        </div>

        <div>
            <span class="text-xs font-semibold text-slate-500 block mb-1">ค่าเสียหายส่วนแรก (Deductible)</span>
            <div class="text-lg font-bold text-slate-800">
                @if ($item['deductibleAmount'] > 0)
                    <span class="text-amber-700">฿{{ Format::number($item['deductibleAmount']) }}</span>
                @else
                    <span class="text-emerald-700">ไม่มี Deductible</span>
                @endif
            </div>
            <span class="text-[11px] text-slate-500 line-clamp-1">{{ $item['deductibleTerms'] ?: 'กรณีเกิดเหตุเป็นฝ่ายถูกไม่ต้องจ่าย' }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 pt-3">
        <span class="text-[11px] text-slate-500 font-medium mr-1">ความคุ้มครองหลัก:</span>
        <x-coverage-badge label="ชนตัวรถ" :status="$cov['own_damage']['status'] ?? null" />
        <x-coverage-badge label="พลิกคว่ำ/ตกทาง" :status="$cov['rollover_offroad']['status'] ?? null" />
        <x-coverage-badge label="รถหาย/ไฟไหม้" :status="$cov['theft_fire']['status'] ?? null" />
        <x-coverage-badge label="น้ำท่วม" :status="$cov['flood']['status'] ?? null" covered="bg-blue-50 text-blue-800 border border-blue-200" />
    </div>

    <div class="flex items-center justify-between pt-3 mt-3 border-t border-slate-100 text-xs">
        <div class="flex items-center gap-2 text-slate-500">
            <x-lucide-file-text class="w-3.5 h-3.5 text-slate-500" />
            @if (count($item['documents']) > 0)
                <span class="font-medium text-slate-600">มีเอกสารอ้างอิง ({{ count($item['documents']) }})</span>
            @else
                <span class="text-amber-600">รอแนบเอกสาร</span>
            @endif
            @if ($item['verifiedAt'])
                <span class="hidden sm:inline text-slate-500">• ตรวจทานเมื่อ {{ Format::thaiDate($item['verifiedAt']) }}</span>
            @endif
        </div>
        <a href="{{ url("/plans/{$item['planId']}?optionId={$item['id']}") }}"
           class="shrink-0 inline-flex items-center gap-1 font-semibold text-blue-600 hover:text-blue-800 transition rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
            <span>ดูรายละเอียดเต็ม</span><x-lucide-chevron-right aria-hidden="true" class="w-3.5 h-3.5" />
        </a>
    </div>
</div>
