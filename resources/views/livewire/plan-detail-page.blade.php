@php
    use App\Support\Format;
    $user = auth()->user();
    $status = $active?->workflow_status;
    $inBasket = $selected && in_array($selected->id, $basket, true);
    $zone = $rules->first()?->registration_zone;
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div>
        <a href="{{ url('/search') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-800 transition">
            <x-lucide-arrow-left class="w-4 h-4" /><span>กลับไปหน้ารายการค้นหา</span>
        </a>
    </div>

    @if ($actionError)
        <div role="alert" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700">{{ $actionError }}</div>
    @endif

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-100">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="text-xs font-bold text-blue-900 bg-blue-50 px-2.5 py-1 rounded-md border border-blue-200">{{ $plan->insurer?->name ?: 'ไม่ระบุบริษัท' }}</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $plan->insurance_type }}</span>
                    @if ($active)
                        <span class="text-xs font-semibold px-2 py-0.5 rounded border {{ match ($status) { 'published' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'in_review' => 'bg-amber-50 text-amber-800 border-amber-200', default => 'bg-slate-100 text-slate-700 border-slate-200' } }}">
                            รุ่นที่ {{ $active->version_no }} ({{ $status }})
                        </span>
                    @endif
                    @if ($plan->is_archived)
                        <span class="text-xs font-bold px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200">จัดเก็บเข้ากรุแล้ว (Archived)</span>
                    @endif
                    @if ($zone && $zone !== 'all')
                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-300">
                            <x-lucide-map-pin class="w-3 h-3 text-amber-700" />{{ Format::registrationZone($zone) }}
                        </span>
                    @endif
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $plan->name }}</h1>
                <p class="text-xs text-slate-500 mt-1">
                    ช่องทางติดต่อบริษัท: {{ $plan->insurer?->contact_channels ?: '-' }} • เว็บไซต์: {{ $plan->insurer?->website ?: '-' }}
                    @if ($active?->effective_from) • มีผลบังคับใช้: {{ $active->effective_from }} ถึง {{ $active->effective_to ?: 'ไม่กำหนด' }} @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($user->can('canSubmitReview') && $status === 'draft')
                    <button type="button" wire:click="submitForReview('{{ $active->id }}')"
                            class="bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                        <x-lucide-send class="w-3.5 h-3.5 text-amber-700" /><span>ส่งตรวจสอบ (Submit Review)</span>
                    </button>
                @endif

                @can('canEditDraft')
                    <button type="button" wire:click="$dispatch('open-plan-form', { planId: '{{ $plan->id }}', copy: false })"
                            class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <x-lucide-edit-3 class="w-3.5 h-3.5 text-blue-600" /><span>แก้ไขข้อมูลแพ็กเกจ</span>
                    </button>
                    <button type="button" wire:click="$dispatch('open-plan-form', { planId: '{{ $plan->id }}', copy: true })"
                            class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <x-lucide-copy class="w-3.5 h-3.5 text-emerald-600" /><span>คัดลอกแพ็กเกจ</span>
                    </button>
                @endcan

                @if ($selected && $status === 'published')
                    <button type="button" wire:click="toggleCompare('{{ $selected->id }}')" @disabled(! $inBasket && count($basket) >= 4)
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $inBasket ? 'bg-blue-600 text-white shadow-xs' : 'bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 disabled:opacity-50' }}">
                        @if ($inBasket) <x-lucide-check class="w-3.5 h-3.5" /> @else <x-lucide-plus class="w-3.5 h-3.5" /> @endif
                        <span>{{ $inBasket ? 'อยู่ในรายการเปรียบเทียบแล้ว' : 'เพิ่มลงรายการเปรียบเทียบ' }}</span>
                    </button>
                @endif

                @if ($options->count() >= 2 && $status === 'published')
                    <a href="{{ url('/compare?ids='.$options->take(4)->pluck('id')->implode(',')) }}"
                       class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                        <x-lucide-columns-3 class="w-3.5 h-3.5" /><span>เทียบตัวเลือกในแผนนี้ ({{ min($options->count(), 4) }})</span>
                    </a>
                @endif

                @if (count($basket) >= 2)
                    <a href="{{ url('/compare?ids='.implode(',', $basket)) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                        <x-lucide-columns-3 class="w-3.5 h-3.5" /><span>เปิดตารางเปรียบเทียบ ({{ count($basket) }})</span>
                    </a>
                @endif
            </div>
        </div>

        <div class="pt-4 flex items-center gap-2 overflow-x-auto text-xs">
            <span class="font-semibold text-slate-500 flex items-center gap-1 shrink-0"><x-lucide-history class="w-3.5 h-3.5" />ประวัติรุ่น (คลิกเพื่อดูย้อนหลัง):</span>
            @foreach ($versions as $ver)
                @php $on = $ver->id === $active?->id; @endphp
                <button type="button" wire:key="ver-{{ $ver->id }}" wire:click="selectVersion('{{ $ver->id }}')"
                        class="px-3 py-1 rounded-lg border font-medium flex items-center gap-1.5 transition shrink-0 cursor-pointer {{ $on ? 'bg-blue-600 border-blue-600 text-white shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' }}">
                    <span>รุ่นที่ {{ $ver->version_no }}</span>
                    <span class="text-[10px] uppercase font-bold {{ $on ? 'text-white' : 'text-slate-500' }}">({{ $ver->workflow_status }})</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-3">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2"><x-lucide-car class="w-4 h-4 text-blue-600" /><span>เงื่อนไขรถที่รับประกัน</span></h3>
                @forelse ($rules as $r)
                    @php $v = $r->vehicleModel; $restrictedZone = $r->registration_zone && $r->registration_zone !== 'all'; @endphp
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs space-y-1">
                        <div class="font-bold text-slate-800">
                            @if ($r->scope_type === 'specific_model')
                                {{ trim('เฉพาะรุ่น: '.($v?->brand ?? '').' '.($v?->model ?? '').' '.($v?->variant ? "({$v->variant})" : '')) }}
                            @elseif ($r->scope_type === 'unknown')
                                ยังไม่ระบุขอบเขตรุ่นรถชัดเจน
                            @else
                                รองรับรถทั่วไปทุกรุ่นภายใต้เงื่อนไข
                            @endif
                        </div>
                        <div class="text-slate-600">อายุรถ: {{ Format::carAgeRange($r->min_age, $r->max_age) }} ({{ match ($r->age_basis) { 'manufacture_year' => 'นับตามปีผลิต ค.ศ.', 'registration_year' => 'นับตามปีจดทะเบียน', default => 'ยังไม่ระบุเกณฑ์นับอายุ' } }})</div>
                        <div class="flex items-center flex-wrap gap-1.5 text-slate-600">
                            <span>ป้ายทะเบียน:</span>
                            <span class="{{ $restrictedZone ? 'inline-flex items-center gap-1 font-bold text-amber-900 bg-amber-100/80 border border-amber-200 px-2 py-0.5 rounded-md text-[11px]' : 'font-medium text-slate-700' }}">
                                @if ($restrictedZone)<x-lucide-map-pin class="w-3 h-3 text-amber-700" />@endif{{ Format::registrationZone($r->registration_zone) }}
                            </span>
                        </div>
                        <div class="text-slate-600">ตรวจสภาพรถ: {{ $r->inspection_required ? 'ต้องตรวจสภาพรถก่อนทำประกัน' : 'ไม่ต้องตรวจสภาพรถ' }}</div>
                        @if ($r->notes)<div class="text-slate-500">{{ $r->notes }}</div>@endif
                    </div>
                @empty
                    <p class="text-xs text-slate-500">ยังไม่มีข้อมูลเงื่อนไขรถยนต์ในรุ่นนี้</p>
                @endforelse
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-3">
                <h3 class="text-sm font-bold text-slate-900">ตัวเลือกราคาในรุ่นนี้ ({{ $options->count() }})</h3>
                @forelse ($options as $opt)
                    @php $on = $opt->id === $selected?->id; $sum = $opt->sum_insured_max ?? $opt->sum_insured_min; @endphp
                    <button type="button" wire:key="opt-{{ $opt->id }}" wire:click="selectOption('{{ $opt->id }}')"
                            class="w-full text-left p-3 rounded-xl border transition cursor-pointer {{ $on ? 'bg-blue-50/70 border-blue-500 ring-1 ring-blue-500' : 'bg-slate-50/50 border-slate-200 hover:bg-slate-100' }}">
                        <div class="flex items-center justify-between gap-2 text-xs mb-1">
                            <span class="font-bold text-slate-800">{{ $opt->label }}</span>
                            <span class="font-extrabold text-blue-900 shrink-0">{{ Format::money($opt->premium_total) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                            <span>{{ $opt->repair_type === 'dealer' ? 'ซ่อมห้าง' : 'ซ่อมอู่' }}</span>
                            <span>• {{ $sum !== null ? 'ทุน ฿'.Format::number($sum) : 'ยังไม่ระบุทุน' }}</span>
                            @if ($opt->deductible_amount > 0)<span class="text-amber-700">• Deduct ฿{{ Format::number($opt->deductible_amount) }}</span>@endif
                        </div>
                    </button>
                @empty
                    <p class="text-xs text-slate-500">ยังไม่มีตัวเลือกราคาในรุ่นนี้</p>
                @endforelse
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs space-y-3">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2"><x-lucide-file-text class="w-4 h-4 text-blue-600" /><span>เอกสารต้นทางอ้างอิง ({{ $documents->count() }})</span></h3>
                @forelse ($documents as $doc)
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs space-y-2">
                        <div class="space-y-1">
                            <div class="font-bold text-slate-800 line-clamp-1">{{ $doc->title }}</div>
                            @if ($doc->page_reference)<div class="text-[11px] text-slate-500">จุดอ้างอิง: {{ $doc->page_reference }}</div>@endif
                            <div class="text-[10px] text-slate-500">
                                ประเภท: {{ $doc->source_type }}{{ $doc->storage_path ? " • ไฟล์: {$doc->storage_path}" : '' }}{{ $doc->file_size ? ' ('.max(1, round($doc->file_size / 1024)).' KB)' : '' }}
                            </div>
                        </div>
                        @if ($doc->source_url)
                            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-200/70">
                                <a href="{{ $doc->source_url }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-blue-50 hover:border-blue-200 text-blue-700 font-bold text-[11px] transition">
                                    <x-lucide-external-link class="w-3 h-3" /><span>เปิดดูเอกสาร</span>
                                </a>
                                @if (str_starts_with($doc->source_url, '/documents/'))
                                    <a href="{{ $doc->source_url }}?download=1" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-semibold text-[11px] transition">
                                        <x-lucide-download class="w-3 h-3" /><span>ดาวน์โหลด</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-3">ยังไม่มีเอกสารอ้างอิงแนบในรุ่นนี้ (ต้องแนบอย่างน้อย 1 รายการก่อนอนุมัติเผยแพร่)</p>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">ตารางความคุ้มครองและข้อยกเว้น</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $selected ? "แสดงผลตามตัวเลือก \"{$selected->label}\" (รวมข้อยกเว้นและเงื่อนไขเฉพาะ)" : 'ยังไม่มีตัวเลือกราคาให้แสดงผล' }}</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded bg-blue-50 text-blue-700 border border-blue-200">{{ count($coverages) }} หมวด</span>
                </div>
                <div class="divide-y divide-slate-100 mt-2">
                    @foreach ($coverages as $cov)
                        <div class="py-3.5 flex items-start justify-between gap-4 text-xs">
                            <div class="space-y-0.5">
                                <span class="font-bold text-slate-800">{{ $cov['name'] }}</span>
                                @if ($cov['terms'])<p class="text-slate-500 text-[11px]">{{ $cov['terms'] }}</p>@endif
                                @if ($cov['exclusions'])<p class="text-amber-700 text-[11px]">ข้อยกเว้น: {{ $cov['exclusions'] }}</p>@endif
                            </div>
                            <div class="text-right shrink-0">
                                <span class="inline-block font-bold px-2 py-0.5 rounded text-xs {{ match ($cov['status']) { 'covered' => 'bg-emerald-50 text-emerald-800', 'not_covered' => 'bg-slate-100 text-slate-600', 'optional' => 'bg-amber-50 text-amber-800', default => 'bg-slate-50 text-slate-500 border border-slate-200' } }}">
                                    @switch($cov['status'])
                                        @case('covered') {{ $cov['limitAmount'] ? '฿'.Format::number($cov['limitAmount']).($cov['limitBasis'] ? " ({$cov['limitBasis']})" : '') : ($cov['limitBasis'] ?: 'คุ้มครอง (ยังไม่ระบุวงเงิน)') }} @break
                                        @case('not_covered') ไม่คุ้มครอง @break
                                        @case('optional') ซื้อเพิ่มได้ (Optional) @break
                                        @default ยังไม่ระบุ
                                    @endswitch
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-3">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2"><x-lucide-history class="w-4 h-4 text-slate-500" /><span>ประวัติการเปลี่ยนแปลงและการตรวจสอบ (Audit Trail)</span></h3>
                @forelse ($auditLogs as $log)
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                        <div class="flex items-center justify-between text-slate-500 mb-1">
                            <span class="font-bold text-slate-700">ผู้ดำเนินการ: {{ $log->actor_name ?: 'ระบบ' }}</span>
                            <span>{{ Format::thaiDateTime($log->created_at) }}</span>
                        </div>
                        <div class="text-slate-800 font-medium">การกระทำ: <span class="text-blue-700 uppercase font-bold">{{ $log->action }}</span></div>
                        @if ($log->reason)<div class="text-slate-600 mt-0.5">เหตุผล: {{ $log->reason }}</div>@endif
                    </div>
                @empty
                    <p class="text-xs text-slate-500">ยังไม่มีประวัติบันทึกสำหรับแพ็กเกจนี้</p>
                @endforelse
            </div>
        </div>
    </div>

    @can('canEditDraft')
        <livewire:plan-form />
    @endcan
</div>
