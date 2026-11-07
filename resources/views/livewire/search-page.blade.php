@php
    $input = 'w-full text-xs bg-slate-50 border border-slate-200 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
    $check = 'rounded border-slate-300 text-blue-600 focus:ring-blue-500';
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">ค้นหาและเปรียบเทียบประกันภัยรถยนต์</h1>
            <p class="text-sm text-slate-500 mt-1">ฐานข้อมูลเงื่อนไขและราคาอ้างอิงภายในทีม เฉพาะแพ็กเกจที่ผ่านการอนุมัติเผยแพร่แล้ว</p>
        </div>
        <div class="flex items-center gap-2">
            <x-lucide-arrow-up-down class="w-4 h-4 text-slate-500" />
            <span class="text-xs font-semibold text-slate-600">เรียงตาม:</span>
            <select aria-label="เรียงตาม" wire:model.live="sortBy"
                    class="text-xs bg-white border border-slate-200 rounded-lg px-3 py-2 font-medium text-slate-700 shadow-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                <option value="premium_asc">เบี้ยประกันรวม: ต่ำไปสูง</option>
                <option value="premium_desc">เบี้ยประกันรวม: สูงไปต่ำ</option>
                <option value="sum_insured_desc">ทุนประกัน: มากไปน้อย</option>
                <option value="name_asc">ชื่อบริษัทประกัน (ก-ฮ)</option>
            </select>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row items-start gap-6">
        {{-- Filter sidebar. Below lg the filters fold away so results start on the first screen. --}}
        <aside x-data="{ open: false }" aria-label="ตัวกรองค้นหา" class="w-full lg:w-80 lg:shrink-0 bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between gap-3" :class="open ? 'pb-3 border-b border-slate-100' : 'lg:pb-3 lg:border-b lg:border-slate-100'">
                <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="filter-panel"
                        class="lg:hidden flex items-center gap-2 text-slate-800 font-bold text-base rounded-lg -m-1 p-1 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600">
                    <x-lucide-filter class="w-4 h-4 text-blue-600" />
                    <span>ตัวกรองค้นหา</span>
                    @if ($activeCount > 0)
                        <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-semibold">ใช้อยู่ {{ $activeCount }}</span>
                    @endif
                    <x-lucide-chevron-down aria-hidden="true" class="w-4 h-4 text-slate-500 transition-transform duration-150" ::class="open && 'rotate-180'" />
                </button>
                <div class="hidden lg:flex items-center gap-2 text-slate-800 font-bold text-base">
                    <x-lucide-filter class="w-4 h-4 text-blue-600" />
                    <span>ตัวกรองค้นหา</span>
                    <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-semibold"><span class="sr-only">พบ </span>{{ $results['totalCount'] }}<span class="sr-only"> รายการ</span></span>
                </div>
                <button type="button" wire:click="resetFilters" class="text-xs text-slate-500 hover:text-blue-600 flex items-center gap-1 font-medium transition cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-blue-600">
                    <x-lucide-rotate-ccw class="w-3 h-3" /> ล้างค่า
                </button>
            </div>

            <div id="filter-panel" class="lg:block mt-6 space-y-6" :class="open ? 'block' : 'hidden'">
                <div class="space-y-1.5">
                    <label for="filter-query" class="text-xs font-semibold text-slate-700">ค้นหาชื่อบริษัท หรือ แพ็กเกจ</label>
                    <div class="relative">
                        <x-lucide-search class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" />
                        <input id="filter-query" type="text" placeholder="เช่น วิริยะ, BKI, ซ่อมห้าง..." wire:model.live.debounce.300ms="query"
                               class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                    </div>
                </div>

                <div class="space-y-2">
                    <span id="filter-insurance-types" class="text-xs font-semibold text-slate-700">ประเภทประกันภัย</span>
                    <div role="group" aria-labelledby="filter-insurance-types" class="grid grid-cols-2 gap-1.5">
                        @foreach (App\Support\Format::INSURANCE_TYPES as $type)
                            @php $on = in_array($type, $insuranceTypes, true); @endphp
                            <button type="button" wire:click="toggle('insuranceTypes', '{{ $type }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-medium border text-center transition cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 {{ $on ? 'bg-blue-600 border-blue-600 text-white shadow-xs' : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300' }}">
                                {{ $type }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-2">
                    <span id="filter-repair-types" class="text-xs font-semibold text-slate-700">ลักษณะการซ่อม</span>
                    <div role="group" aria-labelledby="filter-repair-types" class="space-y-1.5">
                        @foreach (['dealer' => 'ซ่อมห้าง (ศูนย์บริการ)', 'garage' => 'ซ่อมอู่ (อู่ในเครือ)'] as $value => $label)
                            <label class="flex items-center gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer">
                                <input type="checkbox" value="{{ $value }}" wire:model.live="repairTypes" class="{{ $check }}"><span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-2">
                    <span id="filter-insurers" class="text-xs font-semibold text-slate-700">บริษัทประกันภัย</span>
                    <div role="group" aria-labelledby="filter-insurers" class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                        @foreach ($insurers as $insurer)
                            <label wire:key="ins-{{ $insurer->id }}" class="flex items-center gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer">
                                <input type="checkbox" value="{{ $insurer->id }}" wire:model.live="insurerIds" class="{{ $check }}"><span class="truncate">{{ $insurer->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <div class="space-y-1.5">
                        <label for="filter-vehicleBrand" class="text-xs font-semibold text-slate-700">ยี่ห้อรถยนต์</label>
                        <select id="filter-vehicleBrand" wire:model.live="vehicleBrand" class="{{ $input }}">
                            <option value="">ทุกยี่ห้อ / ไม่ระบุ</option>
                            @foreach ($brands as $brand)<option value="{{ $brand }}">{{ $brand }}</option>@endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="filter-vehicleModelId" class="text-xs font-semibold text-slate-700">รุ่นรถยนต์</label>
                        <select id="filter-vehicleModelId" wire:model.live="vehicleModelId" class="{{ $input }}">
                            <option value="">ทุกรุ่น / ไม่ระบุ</option>
                            @foreach ($visibleModels as $vm)
                                <option value="{{ $vm->id }}">{{ $vm->brand }} {{ $vm->model }} ({{ $vm->variant ?: ($vm->powertrain ?: 'ทั่วไป') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1.5">
                            <label for="filter-carAge" class="text-xs font-semibold text-slate-700">อายุรถ (ปี)</label>
                            <input id="filter-carAge" type="number" min="0" max="35" placeholder="เช่น 3 ปี" wire:model.live.debounce.300ms="carAge" class="{{ $input }}">
                        </div>
                        <div class="space-y-1.5">
                            <label for="filter-usageType" class="text-xs font-semibold text-slate-700">การใช้งาน</label>
                            <select id="filter-usageType" wire:model.live="usageType" class="{{ $input }}">
                                <option value="">ทั้งหมด</option>
                                <option value="private">ส่วนบุคคล</option>
                                <option value="commercial">เชิงพาณิชย์</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label for="filter-registrationPlate" class="text-xs font-semibold text-slate-700 flex items-center gap-1">
                            <x-lucide-map-pin class="w-3.5 h-3.5 text-blue-600" /><span>ป้ายทะเบียนรถ</span>
                        </label>
                        <select id="filter-registrationPlate" wire:model.live="registrationPlate" class="{{ $input }}">
                            <option value="">ทุกป้ายทะเบียน / ไม่ระบุ</option>
                            <option value="bangkok">รถป้าย กทม. (แสดงแผนที่รับรถ กทม.)</option>
                            <option value="metro">รถป้ายปริมณฑล</option>
                            <option value="upcountry">รถป้ายต่างจังหวัด (ตจว.)</option>
                            <option value="bangkok_only_strict">ดูเฉพาะแผนจำกัดป้าย กทม. เท่านั้น</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <div class="space-y-1.5">
                        <span id="filter-premium-range" class="text-xs font-semibold text-slate-700">ช่วงเบี้ยรวม (บาท)</span>
                        <div role="group" aria-labelledby="filter-premium-range" class="grid grid-cols-2 gap-2">
                            <input type="number" min="0" placeholder="ต่ำสุด" aria-label="เบี้ยรวมต่ำสุด (บาท)" wire:model.live.debounce.400ms="minPremium" class="{{ $input }}">
                            <input type="number" min="0" placeholder="สูงสุด" aria-label="เบี้ยรวมสูงสุด (บาท)" wire:model.live.debounce.400ms="maxPremium" class="{{ $input }}">
                        </div>
                        <label class="flex items-center gap-2 text-[11px] text-slate-500 cursor-pointer pt-1">
                            <input type="checkbox" wire:model.live="includeUnknownPrice" class="{{ $check }}"><span>รวมแผนที่ยังไม่ระบุราคา</span>
                        </label>
                    </div>
                    <div class="space-y-1.5">
                        <span id="filter-sum-range" class="text-xs font-semibold text-slate-700">ช่วงทุนประกัน (บาท)</span>
                        <div role="group" aria-labelledby="filter-sum-range" class="grid grid-cols-2 gap-2">
                            <input type="number" min="0" step="50000" placeholder="ทุนต่ำสุด" aria-label="ทุนประกันต่ำสุด (บาท)" wire:model.live.debounce.400ms="minSumInsured" class="{{ $input }}">
                            <input type="number" min="0" step="50000" placeholder="ทุนสูงสุด" aria-label="ทุนประกันสูงสุด (บาท)" wire:model.live.debounce.400ms="maxSumInsured" class="{{ $input }}">
                        </div>
                    </div>
                </div>

                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div>
                        <span id="filter-coverages" class="text-xs font-semibold text-slate-700 block">ความคุ้มครองที่ต้องมี ({{ count($requiredCoverageCodes) }})</span>
                        <span class="text-[10px] text-slate-500">แผนต้องคุ้มครอง ไม่นับที่เป็นตัวเลือกซื้อเพิ่ม</span>
                    </div>
                    <div role="group" aria-labelledby="filter-coverages" class="space-y-3 max-h-72 overflow-y-auto pr-1">
                        @foreach ($coverageCategories as $category => $types)
                            <div class="space-y-1">
                                <span class="text-[11px] font-semibold text-slate-500 block">หมวด{{ $category }}</span>
                                @foreach ($types as $ct)
                                    <label wire:key="cov-{{ $ct->id }}" class="flex items-start gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer">
                                        <input type="checkbox" value="{{ $ct->code }}" wire:model.live="requiredCoverageCodes" class="{{ $check }} mt-0.5"><span class="line-clamp-1">{{ $ct->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </aside>

        {{-- Results. Refetches keep the current list on screen, dimmed, instead of flashing a spinner. --}}
        <div class="flex-1 min-w-0 w-full space-y-4 transition-opacity duration-150" wire:loading.class="opacity-60" wire:loading.attr="aria-busy">
            @if (count($results['items']) === 0)
                <div class="bg-white border border-slate-200 rounded-xl p-12 text-center">
                    <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3 text-slate-500"><x-lucide-search-x class="w-6 h-6" /></div>
                    <h3 class="text-base font-bold text-slate-800">ไม่พบแพ็กเกจที่ตรงตามเงื่อนไข</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">ลองปรับลดเงื่อนไขตัวกรอง เช่น ขยายช่วงเบี้ย หรือเลือกไม่บังคับความคุ้มครองบางรายการ</p>
                </div>
            @else
                <div class="flex items-center justify-between gap-3 text-xs text-slate-500 px-1">
                    <span aria-live="polite">แสดง {{ count($results['items']) }} จากทั้งหมด {{ $results['totalCount'] }} รายการ</span>
                    <span class="hidden sm:inline">(คลิกปุ่ม "เปรียบเทียบ" เพื่อเลือกเทียบได้สูงสุด 4 รายการ)</span>
                </div>

                @foreach ($results['items'] as $item)
                    <x-price-option-card wire:key="opt-{{ $item['id'] }}" :item="$item" :selected="in_array($item['id'], $basket, true)" :can-select-more="count($basket) < 4" />
                @endforeach

                @if ($results['totalPages'] > 1)
                    <nav aria-label="เลือกหน้าผลการค้นหา" class="flex flex-wrap justify-center items-center gap-2 pt-6">
                        @for ($p = 1; $p <= $results['totalPages']; $p++)
                            <button type="button" wire:click="goToPage({{ $p }})" aria-label="หน้า {{ $p }}" @if ($results['page'] === $p) aria-current="page" @endif
                                    class="w-9 h-9 rounded-lg text-xs font-bold transition cursor-pointer {{ $results['page'] === $p ? 'bg-blue-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">{{ $p }}</button>
                        @endfor
                    </nav>
                @endif
            @endif
        </div>
    </div>

    <x-compare-dock :items="$basketItems" />
</div>
