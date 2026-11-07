@php
    use App\Support\Format;
    $n = count($options);
    $sumOf = fn ($o) => $o['sumInsuredMax'] ?? $o['sumInsuredMin'];
    $repairLabel = fn ($o) => $o['repairType'] === 'dealer' ? 'ซ่อมห้าง (ศูนย์บริการ)' : 'ซ่อมอู่ (อู่ในเครือ)';
    $coreSpecs = [
        'sum_insured' => 'ทุนประกันภัย (บาท)',
        'repair_type' => 'ลักษณะการซ่อม',
        'deductible' => 'ค่าเสียหายส่วนแรก (Deductible)',
        'driver_conditions' => 'เงื่อนไขผู้ขับขี่',
        'compulsory' => 'พ.ร.บ. คุ้มครองผู้ประสบภัย',
        'vehicle_conditions' => 'เงื่อนไขอายุ รุ่นรถ และป้ายทะเบียน',
    ];
    $todayThai = Format::thaiDate(today(), 'd MMMM yyyy');
    $untilThai = Format::thaiDate($validity['until'], 'd MMMM yyyy');
@endphp
<div x-data="{ onlyDiff: false, warningsOpen: false }" class="max-w-5xl mx-auto px-2 sm:px-4 py-4 sm:py-6 space-y-4 print:max-w-none print:p-0">
    {{-- Print CSS that keeps the quotation on exactly one A4 page --}}
    <style>
        @media print {
            @page { size: A4 portrait; margin: 0 !important; }
            html, body {
                width: 210mm !important; height: auto !important; margin: 0 !important; padding: 0 !important;
                background: white !important; color: #0f172a !important; font-size: 8.5pt !important; line-height: 1.15 !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            .no-print { display: none !important; }
            /* The workspace shell is one screen tall, which on paper is exactly one page; rounding then adds a blank one. */
            .min-h-screen { min-height: 0 !important; }
            .a4-quotation-sheet {
                width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 6mm 8mm !important;
                border: none !important; box-shadow: none !important; page-break-after: avoid !important;
                page-break-inside: avoid !important; box-sizing: border-box !important;
                /* shortcut: a sheet taller than A4 is cut at the bottom instead of starting page 2; shrink the type if long terms ever push the signatures off. */
                max-height: 296mm !important; overflow: hidden !important;
            }
            table { min-width: 0 !important; page-break-inside: avoid !important; font-size: 8.5pt !important; }
            tr { page-break-inside: avoid !important; page-break-after: auto !important; }
            th, td { padding: 1.5px 5px !important; line-height: 1.12 !important; }
        }
    </style>

    {{-- Screen toolbar --}}
    <div class="no-print bg-white border border-slate-200 rounded-2xl p-3 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ url('/search') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 transition bg-blue-50 px-3 py-1.5 rounded-xl border border-blue-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                <x-lucide-arrow-left class="w-3.5 h-3.5" /><span>กลับไปหน้าค้นหา</span>
            </a>
            <span class="text-xs text-slate-500">•</span>
            <span class="text-xs font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg">{{ $n }} แผนประกัน</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="onlyDiff = !onlyDiff" :aria-pressed="onlyDiff"
                    :class="onlyDiff ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                    class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border transition cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                <x-lucide-sparkles class="w-3.5 h-3.5" ::class="onlyDiff ? 'text-amber-300' : 'text-blue-600'" /><span>เน้นเฉพาะจุดต่าง</span>
            </button>
            @if (count($warnings) > 0)
                <button type="button" @click="warningsOpen = !warningsOpen" :aria-expanded="warningsOpen" aria-controls="compare-warnings"
                        class="flex items-center gap-1.5 text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-3 py-1.5 rounded-xl transition cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                    <x-lucide-alert-triangle class="w-3.5 h-3.5 text-amber-600" /><span>ข้อสังเกต ({{ count($warnings) }})</span>
                </button>
            @endif
            <button type="button" onclick="window.print()"
                    class="flex items-center gap-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 px-4 py-1.5 rounded-xl shadow-xs transition cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                <x-lucide-printer class="w-4 h-4" /><span>พิมพ์ใบเสนอราคา (A4 1 หน้า)</span>
            </button>
            <button type="button" wire:click="clearAll" title="ล้างการเปรียบเทียบ" aria-label="ล้างการเปรียบเทียบและกลับไปหน้าค้นหา"
                    class="flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-red-600 px-2 py-1.5 rounded-lg transition cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600">
                <x-lucide-rotate-ccw aria-hidden="true" class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>

    @if (count($warnings) > 0)
        <div id="compare-warnings" x-show="warningsOpen" x-cloak class="no-print bg-amber-50/90 border border-amber-200 rounded-xl p-3 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-950 flex items-center gap-1.5"><x-lucide-shield-alert class="w-4 h-4 text-amber-600" />ข้อสังเกตความแตกต่างที่สำคัญ</span>
                <button type="button" @click="warningsOpen = false" class="text-[11px] text-amber-800 hover:underline cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-amber-600">ปิดแถบนี้</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-700">
                @foreach ($warnings as $w)
                    <div class="bg-white/80 p-2 rounded-lg border border-amber-200/70">
                        <span class="font-bold text-amber-950 block text-[11px]">{{ $w['title'] }}</span>
                        <span class="text-[10px] text-slate-600 leading-snug">{{ $w['message'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- A4 quotation sheet --}}
    <div class="a4-quotation-sheet bg-white border border-slate-300 rounded-2xl shadow-sm p-4 sm:p-6 print:p-0 print:border-none print:shadow-none space-y-2.5">
        <div class="border-b-2 border-slate-800 pb-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-lg bg-blue-700 flex items-center justify-center text-white shadow-xs"><x-lucide-shield class="w-5 h-5" /></div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-base font-black text-slate-900 tracking-tight">InsureDesk</span>
                        <span class="text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-1.5 rounded">ใบเสนอราคาประกันภัยรถยนต์</span>
                    </div>
                    <p class="text-[10px] text-slate-500 -mt-0.5">ระบบจัดการและเปรียบเทียบข้อมูลประกันภัยรถยนต์ • ฝ่ายบริการข้อมูลลูกค้า</p>
                </div>
            </div>
            <div class="text-right text-[10px] text-slate-600 space-y-0.5 self-end sm:self-auto">
                <div><span class="font-semibold text-slate-500">เลขที่ใบเสนอราคา: </span><span class="font-bold font-mono text-slate-900">{{ $quotationNo }}</span></div>
                <div><span class="font-semibold text-slate-500">วันที่เสนอราคา: </span><span class="font-semibold text-slate-900">{{ $todayThai }}</span></div>
                <div>
                    <span class="font-semibold text-slate-500">การยืนยันราคา: </span>
                    @if ($validity['days'] < 0)
                        <span class="font-bold text-red-700">ราคาบางแผนหมดอายุแล้ว (สิ้นสุด {{ $untilThai }})</span>
                    @else
                        <span class="font-bold text-slate-900">{{ $validity['days'] === 0 ? 'ถึงสิ้นวันนี้' : "{$validity['days']} วัน" }} (ถึงวันที่ {{ $untilThai }}{{ $validity['capped'] ? ' ตามวันสิ้นสุดราคาของแผน' : '' }})</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-2 grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px]">
            <div><span class="text-slate-500 block">ประเภทการใช้งาน:</span><span class="font-bold text-slate-800">{{ $usageSummary }}</span></div>
            <div><span class="text-slate-500 block">ขอบเขตรุ่นรถยนต์:</span><span class="font-bold text-slate-800">{{ $scopeSummary }}</span></div>
            <div><span class="text-slate-500 block">อายุรถยนต์ที่รับประกัน:</span><span class="font-bold text-slate-800">{{ $ageSummary }}</span></div>
            <div><span class="text-slate-500 block">การตรวจสภาพรถ:</span><span class="font-bold text-slate-800">{{ $inspectionSummary }}</span></div>
        </div>

        <p class="no-print sm:hidden text-[11px] text-slate-500">เลื่อนตารางไปด้านข้างเพื่อดูทุกตัวเลือก</p>
        <div class="overflow-x-auto">
            <table class="w-full table-fixed border-collapse border border-slate-300 text-[10px]" style="min-width: {{ 180 + $n * 150 }}px">
                <colgroup>
                    <col style="width: 28%">
                    @foreach ($options as $opt)<col style="width: {{ 72 / $n }}%">@endforeach
                </colgroup>
                <thead>
                    <tr class="bg-slate-100/90 border-b-2 border-slate-300">
                        <th class="p-3 text-center align-middle font-bold text-slate-700 border-r border-slate-300 bg-slate-50 max-sm:sticky max-sm:left-0 max-sm:z-10">
                            <div class="flex flex-col items-center justify-center h-full space-y-1">
                                <span class="block text-[11.5px] font-black text-slate-900">รายการเปรียบเทียบ</span>
                                <span class="text-[9px] font-normal text-slate-500">ความคุ้มครองและเงื่อนไข</span>
                            </div>
                        </th>
                        @foreach ($options as $idx => $opt)
                            @php $compulsory = match ($opt['compulsoryIncluded']) { 'included' => 'รวม พ.ร.บ.', 'unspecified' => 'ยังไม่ระบุ พ.ร.บ.', default => 'ไม่รวม พ.ร.บ.' }; @endphp
                            <th class="p-2 text-center align-top border-r border-slate-300 bg-white">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[9px] font-bold pb-1 border-b border-slate-100">
                                        <span class="bg-blue-100 text-blue-900 px-1.5 rounded font-black">ตัวเลือกที่ {{ $idx + 1 }}</span>
                                        <div class="flex items-center gap-1">
                                            <span class="text-slate-500">{{ $opt['insuranceType'] }}</span>
                                            @if ($n > 2)
                                                <button type="button" wire:click="removeOption('{{ $opt['id'] }}')" title="นำตัวเลือกนี้ออก" aria-label="นำตัวเลือกที่ {{ $idx + 1 }} ({{ $opt['planName'] }}) ออก"
                                                        class="no-print text-slate-500 hover:text-red-600 p-0.5 rounded transition cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600">
                                                    <x-lucide-x class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="min-h-[30px] flex flex-col justify-center">
                                        <div class="font-bold text-[10.5px] text-slate-900 leading-tight line-clamp-1">{{ $opt['insurerName'] }}</div>
                                        <div class="text-[9.5px] text-slate-600 line-clamp-1">{{ $opt['planName'] }}</div>
                                    </div>
                                    <div class="bg-blue-50/80 border border-blue-200 rounded-md p-1.5 my-1">
                                        <span class="text-[9px] text-blue-800 block">เบี้ยประกันภัยรวม ({{ $compulsory }})</span>
                                        <div class="text-base font-black text-blue-950 tracking-tight">
                                            @if ($opt['premiumTotal'] !== null) ฿{{ Format::number($opt['premiumTotal']) }} @else <span class="text-xs text-amber-700">ยังไม่มีข้อมูลราคา</span> @endif
                                        </div>
                                        <span class="text-[8.5px] text-slate-500 block">{{ $repairLabel($opt) }}</span>
                                    </div>
                                    <div class="text-[9.5px] font-bold text-slate-800">
                                        {{ $sumOf($opt) !== null ? 'ทุนประกัน ฿'.Format::number($sumOf($opt)).' บาท' : 'ยังไม่ระบุทุนประกัน' }}
                                    </div>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr class="bg-slate-200/80 font-bold text-slate-900 text-[9.5px]" @if (! in_array(true, $diff, true)) x-show="!onlyDiff" @endif>
                        <td colspan="{{ $n + 1 }}" class="py-1 px-2"><span class="max-sm:sticky max-sm:left-2">1. ข้อมูลพื้นฐานและเงื่อนไขราคา (Pricing & Basic Conditions)</span></td>
                    </tr>
                    @foreach ($coreSpecs as $id => $label)
                        <tr class="hover:bg-slate-50/60" @unless ($diff[$id]) x-show="!onlyDiff" @endunless>
                            <td class="py-1 px-2 font-semibold text-slate-700 border-r border-slate-300 bg-white max-sm:sticky max-sm:left-0 max-sm:z-10">
                                <div class="flex items-center justify-between"><span>{{ $label }}</span>
                                    @if ($diff[$id])<span class="text-[8px] font-bold text-amber-700 bg-amber-100 px-1 rounded no-print">ต่างกัน</span>@endif
                                </div>
                            </td>
                            @foreach ($options as $opt)
                                <td class="py-1 px-2 text-center border-r border-slate-300 align-middle">
                                    @switch($id)
                                        @case('sum_insured')
                                            <span class="font-bold text-slate-900">@if ($sumOf($opt) !== null) ฿{{ Format::number($sumOf($opt)) }} @else <span class="text-slate-500 font-normal">ยังไม่ระบุทุน</span> @endif</span>
                                            @break
                                        @case('repair_type')
                                            <span class="font-semibold text-slate-800">{{ $repairLabel($opt) }}</span>
                                            @break
                                        @case('deductible')
                                            @if ($opt['deductibleAmount'] > 0)
                                                <span class="font-bold text-amber-800">฿{{ Format::number($opt['deductibleAmount']) }} บาท (เมื่อเป็นฝ่ายผิด)</span>
                                            @else
                                                <span class="text-emerald-700 font-medium">ไม่มี (฿0)</span>
                                            @endif
                                            @break
                                        @case('driver_conditions')
                                            <span>{{ $opt['driverConditions'] ?: 'ไม่จำกัดชื่อผู้ขับขี่' }}</span>
                                            @break
                                        @case('compulsory')
                                            <span>{{ match ($opt['compulsoryIncluded']) { 'included' => 'รวมในเบี้ยแล้ว', 'unspecified' => 'ยังไม่ระบุ', default => 'ไม่รวม พ.ร.บ.' } }}</span>
                                            @break
                                        @case('vehicle_conditions')
                                            @php $zone = $opt['registrationZone'] ?? 'all'; @endphp
                                            <span>
                                                <span>{{ $scopeText($opt) ?? 'รถทั่วไปอายุ '.Format::carAgeRange($opt['minCarAge'], $opt['maxCarAge']) }}</span>
                                                @if ($zone !== 'all')
                                                    <span class="font-bold text-amber-900"> • {{ Format::registrationZone($zone, true) }}</span>
                                                @elseif ($hasPlateRestriction)
                                                    <span class="text-slate-500"> • ทุกป้ายทะเบียน</span>
                                                @endif
                                            </span>
                                            @break
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    @foreach ($categories as $cIdx => $cat)
                        @php $catHasDiff = collect($cat['items'])->contains('hasDifference', true); @endphp
                        <tr class="bg-slate-200/80 font-bold text-slate-900 text-[9.5px]" @unless ($catHasDiff) x-show="!onlyDiff" @endunless>
                            <td colspan="{{ $n + 1 }}" class="py-1 px-2"><span class="max-sm:sticky max-sm:left-2">{{ $cIdx + 2 }}. หมวดความคุ้มครอง: {{ $cat['name'] }}</span></td>
                        </tr>
                        @foreach ($cat['items'] as $item)
                            <tr class="hover:bg-slate-50/60" @unless ($item['hasDifference']) x-show="!onlyDiff" @endunless>
                                <td class="py-1 px-2 font-medium text-slate-800 border-r border-slate-300 bg-white max-sm:sticky max-sm:left-0 max-sm:z-10">
                                    <div class="flex items-center justify-between"><span>{{ $item['name'] }}</span>
                                        @if ($item['hasDifference'])<span class="text-[8px] font-bold text-amber-700 bg-amber-100 px-1 rounded no-print">ต่างกัน</span>@endif
                                    </div>
                                </td>
                                @foreach ($options as $opt)
                                    @php $val = $item['values'][$opt['id']] ?? ['status' => 'unknown', 'text' => '']; @endphp
                                    <td class="py-1 px-2 text-center border-r border-slate-300 align-middle">
                                        @switch($val['status'])
                                            @case('covered') <div class="leading-tight"><span class="font-bold text-slate-900">{{ $val['text'] }}</span></div> @break
                                            @case('not_covered') <span class="text-slate-500 font-normal">ไม่คุ้มครอง</span> @break
                                            @case('optional') <span class="text-amber-800 font-medium">{{ $val['text'] }}</span> @break
                                            @default <span class="text-slate-500">ยังไม่ระบุ</span>
                                        @endswitch
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-2 border-t border-slate-300 space-y-2">
            <div class="text-[8.5px] text-slate-500 leading-tight space-y-0.5">
                <p class="font-bold text-slate-700">หมายเหตุและเงื่อนไขประกอบใบเสนอราคา:</p>
                <p>1. เบี้ยประกันภัยและทุนประกันภัยข้างต้นเป็นราคาที่รวมภาษีอากรและ พ.ร.บ. ตามเงื่อนไขที่ระบุแล้ว</p>
                <p>2. ความคุ้มครอง ข้อยกเว้น และเงื่อนไขการเรียกร้องค่าเสียหายเป็นไปตามกรมธรรม์ประกันภัยของแต่ละบริษัทประกันภัยที่ได้รับความเห็นชอบจาก คปภ.</p>
                <p>3. ใบเสนอราคานี้ใช้สำหรับเปรียบเทียบข้อมูลภายในทีม InsureDesk เพื่อเสนอแนะแก่ลูกค้า ขอสงวนสิทธิ์ในการเปลี่ยนแปลงตามผลการตรวจสภาพรถหรือประวัติการขับขี่</p>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-slate-200 text-[9.5px]">
                <div class="border border-slate-200 rounded-lg p-2 text-center space-y-1 bg-slate-50/50">
                    <span class="font-bold text-slate-700 block">ผู้จัดทำใบเสนอราคา (Advisor)</span>
                    <div class="h-6"></div>
                    <div class="border-b border-slate-400 w-3/4 mx-auto"></div>
                    <span class="text-slate-600 block">( {{ auth()->user()->name }} / ทีม InsureDesk )</span>
                    <span class="text-slate-500 text-[8.5px] block">วันที่: {{ $todayThai }}</span>
                </div>
                <div class="border border-slate-200 rounded-lg p-2 text-center space-y-1 bg-slate-50/50">
                    <span class="font-bold text-slate-700 block">ผู้ขอเอาประกันภัย / ลูกค้า (Customer Acceptance)</span>
                    <div class="flex items-center justify-center gap-3 text-[9px] pt-0.5">
                        @foreach ($options as $i => $opt)
                            <span class="inline-flex items-center gap-1"><span aria-hidden="true" class="w-3 h-3 border border-slate-400 rounded-xs inline-block"></span><span>เลือกตัวเลือกที่ {{ $i + 1 }}</span></span>
                        @endforeach
                    </div>
                    <div class="border-b border-slate-400 w-3/4 mx-auto mt-2"></div>
                    <span class="text-slate-600 block">ลงชื่อ: ................................................................</span>
                    <span class="text-slate-500 text-[8.5px] block">วันที่: ........ / ........ / {{ now()->year + 543 }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
