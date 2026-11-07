@php
    use App\Support\Format;
    $user = auth()->user();
    $role = $user->primaryRole();
    $links = [
        ['search', 'ค้นหาประกัน', 'search', true],
        ['compare', 'เปรียบเทียบ', 'columns-3', true],
        ['manage/plans', 'จัดการแพ็กเกจ', 'file-text', $user->can('canEditDraft')],
        ['manage/reviews', 'ตรวจสอบ (Review)', 'file-check', $user->can('canReview')],
        ['manage/insurers', 'บริษัทประกัน', 'settings', $user->can('canEditDraft')],
        ['manage/users', 'สมาชิก', 'users', $user->can('canManageUsers')],
        ['manage/audit', 'ประวัติ (Audit)', 'history', $user->can('canViewAudit')],
    ];
    // From xl the links sit in the bar; demo sessions keep the menu for the role switcher.
    $menuClass = $user->isDemo() ? '' : 'xl:hidden';
@endphp
<header x-data="{ open: false }" class="sticky top-0 z-50 bg-white border-b border-slate-200 shadow-xs print:hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-6">
                <a href="{{ url('/search') }}" class="flex items-center gap-2.5 font-bold text-slate-900 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-700 to-indigo-600 flex items-center justify-center text-white shadow-sm group-hover:shadow-md transition">
                        <x-lucide-shield class="w-5 h-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-lg font-black tracking-tight text-blue-900">InsureDesk</span>
                        <span class="text-[10px] text-slate-500 font-medium -mt-1 whitespace-nowrap xl:hidden">ระบบเปรียบเทียบประกันรถยนต์</span>
                    </div>
                </a>

                <nav aria-label="เมนูหลัก" class="hidden xl:flex items-center gap-0.5">
                    @foreach ($links as [$href, $label, $icon, $show])
                        @continue(! $show)
                        @php $active = request()->is($href, "{$href}/*"); @endphp
                        <a href="{{ url($href) }}" @if ($active) aria-current="page" @endif
                           class="inline-flex items-center gap-1.5 px-2.5 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition focus-visible:outline-2 focus-visible:outline-blue-600 {{ $active ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                            <x-dynamic-component :component="'lucide-'.$icon" class="w-4 h-4" />
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-2.5">
                <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                    <div title="{{ $user->name }} · {{ Format::role($role) }}"
                         class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                    <div class="hidden sm:flex xl:hidden flex-col text-right whitespace-nowrap">
                        <span class="text-xs font-semibold text-slate-800">{{ $user->name }}</span>
                        <span class="text-[10px] px-1.5 rounded border font-medium {{ Format::roleBadge($role) }}">{{ Format::role($role) }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="ออกจากระบบ / สลับบัญชี" aria-label="ออกจากระบบ"
                                class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600">
                            <x-lucide-log-out class="w-4 h-4" />
                        </button>
                    </form>
                </div>

                <button type="button" @click="open = !open" :aria-label="open ? 'ปิดเมนูนำทาง' : 'เปิดเมนูนำทาง'" :aria-expanded="open"
                        class="{{ $menuClass }} p-2 rounded-lg focus-visible:outline-2 focus-visible:outline-blue-600 text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                    <x-lucide-menu class="w-5 h-5" x-show="!open" />
                    <x-lucide-x class="w-5 h-5" x-show="open" x-cloak />
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" x-cloak class="{{ $menuClass }} border-t border-slate-200 bg-white px-4 py-3 space-y-3 shadow-lg">
        <nav aria-label="เมนูหลัก" class="xl:hidden flex flex-col space-y-1">
            @foreach ($links as [$href, $label, $icon, $show])
                @continue(! $show)
                @php $active = request()->is($href, "{$href}/*"); @endphp
                <a href="{{ url($href) }}" @if ($active) aria-current="page" @endif
                   class="inline-flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-blue-600 {{ $active ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                    <x-dynamic-component :component="'lucide-'.$icon" class="w-4 h-4" />
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if ($user->isDemo())
            <div class="pt-2 border-t border-slate-100 xl:pt-0 xl:border-t-0 xl:max-w-md xl:ml-auto">
                <span id="demo-role-label" class="text-[11px] text-slate-500 font-semibold block mb-1.5">สลับสิทธิ์ทดสอบ (Demo Role):</span>
                <div role="group" aria-labelledby="demo-role-label" class="grid grid-cols-4 gap-1.5 bg-slate-100 p-1 rounded-xl text-xs">
                    @foreach (array_keys(App\Models\User::DEMO_USER_IDS) as $r)
                        <form method="POST" action="{{ route('demo.switch', $r) }}">
                            @csrf
                            <button type="submit" aria-pressed="{{ $role === $r ? 'true' : 'false' }}"
                                    class="w-full py-1.5 rounded-lg font-semibold capitalize transition cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 {{ $role === $r ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600' }}">
                                {{ $r }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</header>
