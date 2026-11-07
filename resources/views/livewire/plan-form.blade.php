@php
    use App\Support\Format;
    $field = 'w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500';
    $small = 'w-full p-2 bg-slate-50 border border-slate-200 rounded-xl text-xs';
    $mini = 'w-full p-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs';
    $locked = in_array($currentVersionStatus, ['published', 'archived'], true);
    $uploaded = str_starts_with($documentSourceUrl, '/documents/');
    $tabs = [
        'info' => '1. ข้อมูลแพ็กเกจ',
        'vehicle' => '2. เงื่อนไขรถ',
        'price' => '3. ราคาและทุนประกัน ('.count($tiers).' ตัวเลือก)',
        'coverages' => '4. ความคุ้มครอง ('.count($coverages).' หมวด)',
        'document' => '5. เอกสารอ้างอิง',
    ];
    $fileSize = fn ($b) => ! $b ? '' : ($b < 1048576 ? max(1, round($b / 1024)).' KB' : number_format($b / 1048576, 2).' MB');
@endphp
<div>
@if ($open)
    {{-- Native dialog: the browser traps focus and makes the page behind it inert. wire:ignore.self keeps its open state across re-renders. --}}
    <dialog wire:ignore.self x-data x-init="$el.showModal()" aria-labelledby="plan-modal-title"
            @cancel.prevent="if (confirm('ปิดฟอร์มนี้หรือไม่? ข้อมูลที่ยังไม่ได้บันทึกจะหายไป')) $wire.close()"
            class="fixed inset-0 m-0 h-full w-full max-h-none max-w-none border-0 text-slate-900 backdrop:bg-transparent flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 my-6 flex flex-col max-h-[92vh]">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 shrink-0">
                <div>
                    <h2 id="plan-modal-title" class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        @if ($copy) <x-lucide-copy class="w-5 h-5 text-emerald-600" /> @else <x-lucide-layers class="w-5 h-5 text-blue-600" /> @endif
                        <span>{{ $copy ? 'คัดลอกเพื่อสร้างแพ็กเกจใหม่' : ($planId ? 'แก้ไขแพ็กเกจประกันภัย' : 'เพิ่มแพ็กเกจประกันภัยใหม่') }}</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">รองรับตารางหลายทุนและหลายเบี้ยในแพ็กเกจเดียวกัน (Multiple Price Tiers), เงื่อนไขรถ, ความคุ้มครอง {{ count($coverages) }} หมวดมาตรฐาน, และเอกสารต้นทาง</p>
                </div>
                <button type="button" wire:click="close" aria-label="ปิดฟอร์ม" class="p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition cursor-pointer">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto py-3 border-b border-slate-100 shrink-0 text-xs">
                @foreach ($tabs as $id => $label)
                    <button type="button" @click="$wire.activeTab = '{{ $id }}'"
                            :class="$wire.activeTab === '{{ $id }}' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            class="px-3 py-1.5 rounded-lg font-bold transition whitespace-nowrap cursor-pointer">{{ $label }}</button>
                @endforeach
            </div>

            @if ($error)
                <div role="alert" class="my-3 p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2 shrink-0">
                    <x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0" /><span>{{ $error }}</span>
                </div>
            @endif
            @error('upload')
                <div role="alert" class="my-3 p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2 shrink-0">
                    <x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0" /><span>{{ $message }}</span>
                </div>
            @enderror

            <form wire:submit="save" novalidate class="flex-1 overflow-y-auto py-4 space-y-6 text-xs pr-1">
                {{-- TAB 1: plan info --}}
                <div x-show="$wire.activeTab === 'info'" class="space-y-4">
                    @if ($copy)
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3.5 text-xs text-emerald-950 flex items-start gap-2.5">
                            <x-lucide-copy class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                            <div>
                                <span class="font-bold block">กำลังสร้างแพ็กเกจใหม่โดยคัดลอกจาก "{{ $sourceName }}"</span>
                                <span class="text-[11px] text-emerald-800">ดึงข้อมูลเงื่อนไขรถ ตัวเลือกราคา ความคุ้มครองทั้ง {{ count($coverages) }} หมวด และเอกสารอ้างอิงมาให้ครบแล้ว สามารถแก้ชื่อแพ็กเกจ ปรับเงื่อนไขป้ายทะเบียน หรือราคาแล้วกดบันทึกเป็นแพ็กเกจใหม่ได้ทันที</span>
                            </div>
                        </div>
                    @elseif ($planId)
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-blue-950 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-blue-600 inline-block animate-pulse"></span>
                                    รุ่นอ้างอิงล่าสุด: รุ่นที่ {{ $currentVersionNo }} ({{ $currentVersionStatus }})
                                </span>
                                <span class="text-[11px] text-blue-700 bg-blue-100/70 px-2 py-0.5 rounded-md font-mono">ID: {{ substr((string) $currentVersionId, 0, 12) }}...</span>
                            </div>
                            <div class="text-xs text-slate-700 font-semibold pt-2 border-t border-blue-100 flex flex-wrap gap-4">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="new_version" wire:model="saveMode" class="text-blue-600 focus:ring-blue-500">
                                    <span>สร้างเป็นรุ่นใหม่ (รุ่นที่ {{ $currentVersionNo + 1 }}) — เก็บประวัติรุ่นเดิมตามมาตรฐาน</span>
                                </label>
                                @unless ($locked)
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="update" wire:model="saveMode" class="text-blue-600 focus:ring-blue-500">
                                        <span>แก้ไขในรุ่นร่างปัจจุบัน (รุ่นที่ {{ $currentVersionNo }}) โดยตรง</span>
                                    </label>
                                @endunless
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="plan-insurerId" class="font-semibold text-slate-700 block mb-1">บริษัทประกันภัย *</label>
                            <select id="plan-insurerId" required wire:model="insurerId" class="{{ $field }}">
                                @foreach ($insurers as $ins)
                                    <option value="{{ $ins->id }}">{{ $ins->name }} {{ $ins->is_active ? '' : '(ปิดใช้งาน)' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="plan-insuranceType" class="font-semibold text-slate-700 block mb-1">ประเภทประกันภัย *</label>
                            <select id="plan-insuranceType" wire:model="insuranceType" class="{{ $field }}"
                                    @change="if (!{{ $planId ? 'true' : 'false' }} || confirm(`ต้องการรีเซ็ตตารางความคุ้มครอง {{ count($coverages) }} หมวดให้เป็นค่าเริ่มต้นของ &quot;${$event.target.value}&quot; หรือไม่? (กดยกเลิกเพื่อคงค่าที่ปรับแต่งไว้เดิม)`)) $wire.applyCoveragePreset($event.target.value)">
                                @foreach (Format::INSURANCE_TYPES as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="plan-name" class="font-semibold text-slate-700 block mb-1">ชื่อแพ็กเกจประกัน *</label>
                        <input id="plan-name" type="text" required wire:model="name" placeholder="เช่น วิริยะ ซุปเปอร์โปรเทค พรีเมียม หรือ ธนชาต 2+ ฟิต อุ่นใจ" class="{{ $field }}">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="plan-effectiveFrom" class="font-semibold text-slate-700 block mb-1">วันเริ่มมีผลบังคับใช้</label>
                            <input id="plan-effectiveFrom" type="date" wire:model="effectiveFrom" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="plan-effectiveTo" class="font-semibold text-slate-700 block mb-1">วันสิ้นสุดผลบังคับใช้</label>
                            <input id="plan-effectiveTo" type="date" wire:model="effectiveTo" class="{{ $field }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="plan-workflowStatus" class="font-semibold text-slate-700 block mb-1">สถานะข้อมูล (Workflow Status)</label>
                            <select id="plan-workflowStatus" wire:model.live="workflowStatus" class="{{ $field }}">
                                @can('canPublish')<option value="published">เผยแพร่ทันที (Published - แสดงในผลค้นหา)</option>@endcan
                                <option value="in_review">ส่งตรวจ (In Review - ไปที่คิวตรวจสอบ)</option>
                                <option value="draft">บันทึกร่าง (Draft)</option>
                            </select>
                        </div>
                        <div>
                            <label for="plan-notes" class="font-semibold text-slate-700 block mb-1">หมายเหตุของรุ่น (Version Notes)</label>
                            <input id="plan-notes" type="text" wire:model="notes" placeholder="เช่น อัตราเบี้ยปี 2569 ได้รับอนุมัติแล้ว" class="{{ $field }}">
                        </div>
                    </div>
                </div>

                {{-- TAB 2: vehicle eligibility --}}
                <div x-show="$wire.activeTab === 'vehicle'" x-cloak class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="plan-scopeType" class="font-semibold text-slate-700 block mb-1">ขอบเขตรุ่นรถที่รับประกัน</label>
                            <select id="plan-scopeType" wire:model.live="scopeType" class="{{ $field }}">
                                <option value="all_models">รองรับทุกรุ่นภายใต้เงื่อนไข (All Models)</option>
                                <option value="specific_model">เฉพาะรุ่นรถที่ระบุ (Specific Model)</option>
                                <option value="unknown">ยังไม่ระบุขอบเขตรุ่นรถชัดเจน (Unknown)</option>
                            </select>
                        </div>
                        @if ($scopeType === 'specific_model')
                            <div>
                                <label for="plan-vehicleModelId" class="font-semibold text-slate-700 block mb-1">เลือกรุ่นรถเฉพาะ *</label>
                                <select id="plan-vehicleModelId" wire:model="vehicleModelId" class="{{ $field }}">
                                    <option value="">-- กรุณาเลือกรุ่นรถ --</option>
                                    @foreach ($vehicles as $v)<option value="{{ $v->id }}">{{ $v->brand }} {{ $v->model }} ({{ $v->variant ?: ($v->powertrain ?: 'ทั่วไป') }})</option>@endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="plan-minCarAge" class="font-semibold text-slate-700 block mb-1">อายุรถขั้นต่ำ (ปี)</label>
                            <input id="plan-minCarAge" type="number" min="0" placeholder="ไม่จำกัด" wire:model="minCarAge" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                        </div>
                        <div>
                            <label for="plan-maxCarAge" class="font-semibold text-slate-700 block mb-1">อายุรถสูงสุด (ปี)</label>
                            <input id="plan-maxCarAge" type="number" min="0" placeholder="ไม่จำกัด" wire:model="maxCarAge" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                        </div>
                        <div>
                            <label for="plan-ageBasis" class="font-semibold text-slate-700 block mb-1">เกณฑ์นับอายุรถ</label>
                            <select id="plan-ageBasis" wire:model="ageBasis" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option value="registration_year">ปีจดทะเบียน</option>
                                <option value="manufacture_year">ปีที่ผลิต (ค.ศ.)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="plan-usageType" class="font-semibold text-slate-700 block mb-1">ลักษณะการใช้งาน</label>
                            <select id="plan-usageType" wire:model="usageType" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option value="private">การใช้งานส่วนบุคคล (Private)</option>
                                <option value="commercial">การพาณิชย์/รับจ้าง (Commercial)</option>
                                <option value="all">รองรับทุกรูปแบบ</option>
                            </select>
                        </div>
                        <div>
                            <label for="plan-registrationZone" class="font-semibold text-slate-700 block mb-1">เงื่อนไขเขตป้ายทะเบียนรถ</label>
                            <select id="plan-registrationZone" wire:model="registrationZone" class="{{ $field }}">
                                @foreach (['all', 'bangkok_only', 'bangkok_metro', 'upcountry_only'] as $zone)<option value="{{ $zone }}">{{ Format::registrationZone($zone) }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl">
                            <input type="checkbox" wire:model="inspectionRequired" class="rounded text-blue-600 focus:ring-blue-500">
                            <span class="font-semibold text-slate-700">ต้องตรวจสภาพรถก่อนทำประกัน</span>
                        </label>
                    </div>
                </div>

                {{-- TAB 3: price tiers --}}
                <div x-show="$wire.activeTab === 'price'" x-cloak class="space-y-4">
                    <div class="bg-blue-50/70 border border-blue-200 rounded-2xl p-4 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2.5">
                            <x-lucide-sparkles class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                            <div>
                                <span class="font-bold text-blue-900 block">ตารางทุนและเบี้ยประกันภัย (Price Options / Tiers)</span>
                                <p class="text-[11px] text-blue-800 mt-0.5">แพ็กเกจชื่อเดียวกันสามารถมีได้หลายตัวเลือกราคา โดยระบุทุนประกันและเบี้ยที่ต่างกันได้ หากยังไม่ทราบราคาหรือทุนประกัน สามารถติ๊ก "ยังไม่ระบุ" ได้โดยไม่บันทึกเป็น 0 บาท</p>
                            </div>
                        </div>
                        <button type="button" wire:click="addTier" class="bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-1.5 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition shrink-0 cursor-pointer">
                            <x-lucide-plus class="w-3.5 h-3.5" /><span>เพิ่มตัวเลือกราคา / ทุน</span>
                        </button>
                    </div>

                    <div class="space-y-3">
                        @foreach ($tiers as $i => $tier)
                            <div wire:key="tier-{{ $i }}-{{ count($tiers) }}" class="bg-white border-2 border-slate-200 rounded-2xl p-4 shadow-2xs space-y-3 relative hover:border-slate-300 transition">
                                <div class="flex items-center justify-between gap-2 pb-2 border-b border-slate-100">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-800 font-bold flex items-center justify-center text-xs shrink-0">{{ $i + 1 }}</span>
                                        <input type="text" wire:model="tiers.{{ $i }}.label" placeholder="ชื่อตัวเลือกที่ {{ $i + 1 }}" aria-label="ชื่อตัวเลือกราคาที่ {{ $i + 1 }}"
                                               class="font-bold text-slate-800 text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 w-full max-w-sm">
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button type="button" wire:click="duplicateTier({{ $i }})" title="คัดลอกตัวเลือกนี้แล้วเปลี่ยนทุน"
                                                class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg flex items-center gap-1 transition cursor-pointer">
                                            <x-lucide-copy class="w-3 h-3 text-slate-500" /><span>คัดลอก</span>
                                        </button>
                                        @if (count($tiers) > 1)
                                            <button type="button" wire:click="removeTier({{ $i }})" title="ลบตัวเลือกราคานี้" aria-label="ลบตัวเลือกราคานี้"
                                                    class="p-1 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer">
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="plan-tier-{{ $i }}-sumInsuredMax" class="font-semibold text-slate-700">ทุนประกันภัย (บาท)</label>
                                            <label class="text-[10px] text-slate-500 flex items-center gap-1 cursor-pointer">
                                                <input type="checkbox" class="rounded" @checked(! $tier['hasSumInsured']) wire:change="$set('tiers.{{ $i }}.hasSumInsured', ! $event.target.checked)"><span>ยังไม่ระบุ</span>
                                            </label>
                                        </div>
                                        @if ($tier['hasSumInsured'])
                                            <input id="plan-tier-{{ $i }}-sumInsuredMax" type="number" min="0" step="10000" placeholder="เช่น 50000 หรือ 100000" wire:model.live.debounce.500ms="tiers.{{ $i }}.sumInsuredMax"
                                                   class="w-full p-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 text-sm">
                                        @else
                                            <div class="p-2 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 font-semibold text-xs">ยังไม่ระบุทุนประกัน</div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="plan-tier-{{ $i }}-premiumTotal" class="font-semibold text-slate-700">เบี้ยประกันรวม (บาท)</label>
                                            <label class="text-[10px] text-slate-500 flex items-center gap-1 cursor-pointer">
                                                <input type="checkbox" class="rounded" @checked(! $tier['hasPrice']) wire:change="$set('tiers.{{ $i }}.hasPrice', ! $event.target.checked)"><span>ยังไม่ระบุ</span>
                                            </label>
                                        </div>
                                        @if ($tier['hasPrice'])
                                            <input id="plan-tier-{{ $i }}-premiumTotal" type="number" min="0" step="100" placeholder="เช่น 5400" wire:model="tiers.{{ $i }}.premiumTotal"
                                                   class="w-full p-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 text-sm">
                                        @else
                                            <div class="p-2 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 font-semibold text-xs">ยังไม่มีข้อมูลราคา</div>
                                        @endif
                                    </div>
                                    <div>
                                        <label for="plan-tier-{{ $i }}-repairType" class="font-semibold text-slate-700 block mb-1">ลักษณะการซ่อม *</label>
                                        <select id="plan-tier-{{ $i }}-repairType" wire:model.live="tiers.{{ $i }}.repairType" class="{{ $small }}">
                                            <option value="garage">ซ่อมอู่ (อู่ในเครือ)</option>
                                            <option value="dealer">ซ่อมห้าง (ศูนย์บริการ)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                                    <div>
                                        <label for="plan-tier-{{ $i }}-deductibleAmount" class="font-semibold text-slate-700 block mb-1">ค่าเสียหายส่วนแรก Deductible (บาท)</label>
                                        <input id="plan-tier-{{ $i }}-deductibleAmount" type="number" min="0" step="1000" placeholder="0 หากไม่มี" wire:model="tiers.{{ $i }}.deductibleAmount" class="{{ $small }}">
                                    </div>
                                    <div>
                                        <label for="plan-tier-{{ $i }}-compulsoryIncluded" class="font-semibold text-slate-700 block mb-1">การรวม พ.ร.บ.</label>
                                        <select id="plan-tier-{{ $i }}-compulsoryIncluded" wire:model="tiers.{{ $i }}.compulsoryIncluded" class="{{ $small }}">
                                            <option value="excluded">ไม่รวม พ.ร.บ.</option>
                                            <option value="included">รวม พ.ร.บ. แล้ว</option>
                                            <option value="unspecified">ยังไม่ระบุ</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="plan-tier-{{ $i }}-driverConditions" class="font-semibold text-slate-700 block mb-1">เงื่อนไขผู้ขับขี่</label>
                                        <input id="plan-tier-{{ $i }}-driverConditions" type="text" placeholder="เช่น ไม่จำกัดชื่อ หรือ ระบุอายุ" wire:model="tiers.{{ $i }}.driverConditions" class="{{ $small }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="pt-2">
                        <button type="button" wire:click="addTier"
                                class="w-full py-3 border-2 border-dashed border-slate-300 hover:border-blue-500 hover:bg-blue-50/50 rounded-2xl font-bold text-slate-600 hover:text-blue-700 transition flex items-center justify-center gap-2 cursor-pointer">
                            <x-lucide-plus class="w-4 h-4" /><span>เพิ่มตัวเลือกราคา / ทุนประกันอีกรายการ (เช่น ทุน 100,000 / 200,000 / 300,000)</span>
                        </button>
                    </div>
                </div>

                {{-- TAB 4: coverages --}}
                <div x-show="$wire.activeTab === 'coverages'" x-cloak class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 bg-blue-50/70 p-3 rounded-2xl border border-blue-200">
                        <div class="flex items-center gap-2 text-blue-900 font-bold"><x-lucide-sparkles class="w-4 h-4 text-blue-600" /><span>ตั้งค่ามาตรฐานด่วนตามประเภทประกัน:</span></div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach (Format::INSURANCE_TYPES as $type)
                                <button type="button" wire:click="applyCoveragePreset('{{ $type }}')" class="px-2.5 py-1 bg-white hover:bg-blue-100 text-blue-800 rounded-lg border border-blue-300 font-semibold cursor-pointer">{{ $type }}</button>
                            @endforeach
                        </div>
                    </div>
                    @foreach (collect($coverages)->groupBy('category', preserveKeys: true) as $category => $items)
                        <div class="space-y-3">
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[11px] bg-slate-100 p-2 rounded-lg">หมวด{{ $category }} ({{ count($items) }} รายการ)</h4>
                            <div class="space-y-2">
                                @foreach ($items as $c => $cov)
                                    <div wire:key="cov-{{ $cov['code'] }}" class="bg-white p-3 rounded-xl border border-slate-200 hover:border-slate-300 transition space-y-2">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <span class="font-bold text-slate-900 text-xs">{{ $cov['name'] }}</span>
                                            <div class="flex items-center gap-1">
                                                @foreach (['covered' => ['คุ้มครอง', 'bg-emerald-700 border-emerald-700 text-white'], 'not_covered' => ['ไม่คุ้มครอง', 'bg-slate-700 border-slate-700 text-white'], 'optional' => ['ซื้อเพิ่มได้', 'bg-amber-700 border-amber-700 text-white'], 'unknown' => ['ยังไม่ระบุ', 'bg-blue-600 border-blue-600 text-white']] as $value => [$label, $on])
                                                    <button type="button" wire:click="setCoverageStatus({{ $c }}, '{{ $value }}')" aria-pressed="{{ $cov['status'] === $value ? 'true' : 'false' }}"
                                                            class="px-2 py-1 rounded-md text-[11px] font-semibold border transition cursor-pointer {{ $cov['status'] === $value ? $on : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ $label }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                        @if (in_array($cov['status'], ['covered', 'optional'], true))
                                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 pt-1 border-t border-slate-100">
                                                <input type="number" min="0" placeholder="วงเงิน (บาท)" aria-label="{{ $cov['name'] }}: วงเงิน (บาท)" wire:model="coverages.{{ $c }}.limitAmount" class="{{ $mini }}">
                                                <input type="text" placeholder="เกณฑ์วงเงิน เช่น ตามทุน, ต่อครั้ง" aria-label="{{ $cov['name'] }}: เกณฑ์วงเงิน" wire:model="coverages.{{ $c }}.limitBasis" class="{{ $mini }}">
                                                <input type="text" placeholder="เงื่อนไขความคุ้มครอง" aria-label="{{ $cov['name'] }}: เงื่อนไขความคุ้มครอง" wire:model="coverages.{{ $c }}.terms" class="{{ $mini }}">
                                                <input type="text" placeholder="ข้อยกเว้น (ถ้ามี)" aria-label="{{ $cov['name'] }}: ข้อยกเว้น" wire:model="coverages.{{ $c }}.exclusions" class="{{ $mini }}">
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- TAB 5: source document --}}
                <div x-show="$wire.activeTab === 'document'" x-cloak class="space-y-4">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-slate-600 text-xs">
                        <p class="font-semibold text-slate-800 mb-1">การอ้างอิงเอกสารต้นทาง (Section 8.4 & Section 13)</p>
                        <p>ตามหลักการออกแบบ ทุกชุดข้อมูลที่เผยแพร่ต้องมีเอกสารอ้างอิงต้นทางอย่างน้อย 1 รายการ (Rate sheet, Brochure หรือ กรมธรรม์) สามารถอัปโหลดไฟล์เอกสารเข้าสู่ระบบโดยตรง หรือระบุลิงก์อ้างอิงพร้อมเลขหน้าเพื่อให้สมาชิกในทีมตรวจเทียบย้อนหลังได้</p>
                    </div>
                    <div>
                        <label for="plan-document-file" class="font-semibold text-slate-700 block mb-1.5">ไฟล์เอกสารแนบ (PDF, รูปภาพ PNG/JPG/WEBP, หรือ Excel/CSV ไม่เกิน 15 MB)</label>
                        <input id="plan-document-file" x-ref="file" type="file" accept=".pdf,.png,.jpg,.jpeg,.webp,.xlsx,.xls,.csv" class="hidden" wire:model="upload">
                        @if ($uploaded)
                            <div class="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0"><x-lucide-file-text class="w-5 h-5" /></div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 text-emerald-900 font-bold text-xs">
                                            <x-lucide-check-circle-2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                            <span class="truncate">{{ $uploadedOriginalName ?: ($documentTitle ?: $documentStoragePath) }}</span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                                            @if ($documentMimeType)<span class="uppercase bg-white px-1.5 rounded border border-emerald-200 text-[10px] font-semibold text-emerald-800">{{ \Illuminate\Support\Str::afterLast($documentMimeType, '/') }}</span>@endif
                                            @if ($documentFileSize)<span>ขนาด {{ $fileSize($documentFileSize) }}</span>@endif
                                            <span class="text-emerald-700 font-medium">อัปโหลดพร้อมบันทึกแล้ว</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ $documentSourceUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-blue-700 font-bold text-xs transition">
                                        <x-lucide-external-link class="w-3.5 h-3.5" /><span>เปิดดูไฟล์</span>
                                    </a>
                                    <button type="button" @click="$refs.file.click()" wire:loading.attr="disabled" wire:target="upload" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs transition cursor-pointer">
                                        <x-lucide-upload class="w-3.5 h-3.5" /><span>เปลี่ยนไฟล์</span>
                                    </button>
                                    <button type="button" wire:click="clearUpload" title="ลบไฟล์ที่แนบ" aria-label="ลบไฟล์ที่แนบ" class="p-1.5 rounded-xl text-slate-500 hover:text-red-600 hover:bg-red-50 transition cursor-pointer">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        @else
                            <div x-data="{ over: false }" @dragover.prevent="over = true" @dragleave="over = false"
                                 @drop.prevent="over = false; $event.dataTransfer.files[0] && $wire.upload('upload', $event.dataTransfer.files[0])"
                                 @click="$refs.file.click()" :class="over ? 'border-blue-500 bg-blue-50/70' : 'border-slate-300 bg-slate-50/50 hover:border-blue-400 hover:bg-blue-50/30'"
                                 class="border-2 border-dashed rounded-2xl p-6 text-center transition cursor-pointer">
                                <div wire:loading.flex wire:target="upload" class="flex-col items-center justify-center gap-2 py-2">
                                    <x-lucide-loader-2 class="w-7 h-7 text-blue-600 animate-spin mx-auto" />
                                    <span class="font-bold text-blue-800 text-xs">กำลังอัปโหลดไฟล์เอกสารเข้าสู่เซิร์ฟเวอร์...</span>
                                </div>
                                <div wire:loading.remove wire:target="upload" class="flex flex-col items-center justify-center gap-1.5">
                                    <div class="w-10 h-10 rounded-2xl bg-blue-100/80 text-blue-600 flex items-center justify-center mb-1"><x-lucide-upload class="w-5 h-5" /></div>
                                    <p class="font-bold text-slate-800 text-xs">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์เอกสารมาวางที่นี่</p>
                                    <p class="text-[11px] text-slate-500">รองรับไฟล์ PDF, PNG, JPG, WEBP, XLSX, XLS, CSV (สูงสุดไม่เกิน 15 MB)</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="plan-sourceType" class="font-semibold text-slate-700 block mb-1">ประเภทเอกสาร</label>
                            <select id="plan-sourceType" wire:model="sourceType" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option value="rate_sheet">ตารางอัตราเบี้ยประกันภัย (Rate Sheet)</option>
                                <option value="policy_schedule">ตารางกรมธรรม์ (Policy Schedule)</option>
                                <option value="brochure">โบรชัวร์เผยแพร่ (Brochure)</option>
                                <option value="endorsement">เอกสารแนบท้าย (Endorsement)</option>
                                <option value="manual_note">บันทึกภายใน (Manual Note)</option>
                                <option value="other">เอกสารอื่น ๆ</option>
                            </select>
                        </div>
                        <div>
                            <label for="plan-documentTitle" class="font-semibold text-slate-700 block mb-1">ชื่อเอกสารอ้างอิง {{ $workflowStatus === 'published' ? '*' : '' }}</label>
                            <input id="plan-documentTitle" type="text" wire:model="documentTitle" placeholder="เช่น ตารางเบี้ยประกันภัยวิริยะ ซุปเปอร์โปรเทค 2569" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="plan-documentRef" class="font-semibold text-slate-700 block mb-1">จุดอ้างอิง / เลขหน้าในเอกสาร</label>
                            <input id="plan-documentRef" type="text" wire:model="documentRef" placeholder="เช่น หน้า 3-5 ตารางเบี้ยรถกลุ่ม 3, 4" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                        </div>
                        @unless ($uploaded)
                            <div>
                                <label for="plan-documentSourceUrl" class="font-semibold text-slate-700 block mb-1">ลิงก์เอกสารภายนอก (ถ้าไม่ได้อัปโหลดไฟล์)</label>
                                <input id="plan-documentSourceUrl" type="url" wire:model="documentSourceUrl" placeholder="เช่น https://drive.google.com/..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                            </div>
                        @endunless
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100 shrink-0">
                    <div class="flex items-center gap-1.5 text-slate-500 text-xs"><span>ผู้บันทึก:</span><span class="font-bold text-slate-800">{{ auth()->user()->name }}</span></div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="close" class="px-5 py-2.5 font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer">ยกเลิก</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="px-6 py-2.5 font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <x-lucide-save class="w-4 h-4" />
                            <span wire:loading.remove wire:target="save">บันทึกแพ็กเกจ</span><span wire:loading wire:target="save">กำลังบันทึก...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </dialog>
@endif
</div>
