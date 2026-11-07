<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50/30 to-indigo-50/20 flex flex-col justify-center items-center p-4">
    <div class="max-w-md w-full bg-white border border-slate-200 rounded-3xl p-8 shadow-xl space-y-6">
        <div class="text-center space-y-2">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-700 to-indigo-600 flex items-center justify-center text-white mx-auto shadow-md">
                <x-lucide-shield class="w-7 h-7" />
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">InsureDesk</h1>
            <p class="text-xs text-slate-500 max-w-xs mx-auto">ระบบค้นหาและเปรียบเทียบประกันรถยนต์ภายในทีม (สำหรับสมาชิกที่ได้รับเชิญเท่านั้น)</p>
        </div>

        @if ($error)
            <div role="alert" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2">
                <x-lucide-alert-circle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
                <span>{{ $error }}</span>
            </div>
        @endif

        <form wire:submit="login" class="space-y-4">
            <div class="space-y-1">
                <label for="login-email" class="text-xs font-semibold text-slate-700">อีเมลสมาชิก</label>
                <div class="relative">
                    <x-lucide-mail class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                    <input id="login-email" type="email" required wire:model="email" placeholder="name@insuredesk.team"
                           class="w-full pl-9 pr-3 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white focus:outline-none transition">
                </div>
            </div>
            <div class="space-y-1">
                <label for="login-password" class="text-xs font-semibold text-slate-700">รหัสผ่าน</label>
                <div class="relative">
                    <x-lucide-lock class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                    <input id="login-password" type="password" required wire:model="password" placeholder="••••••••"
                           class="w-full pl-9 pr-3 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white focus:outline-none transition">
                </div>
            </div>
            <button type="submit" wire:loading.attr="disabled"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold text-xs shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                <span wire:loading.remove>เข้าสู่ระบบ</span>
                <span wire:loading>กำลังเข้าสู่ระบบ...</span>
                <x-lucide-arrow-right class="w-4 h-4" />
            </button>
        </form>

        @if ($demoAuth)
            <div class="pt-4 border-t border-slate-100 space-y-2">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block text-center">เลือกเข้าสู่ระบบด่วนด้วยบัญชีจำลอง (สำหรับทดสอบ)</span>
                <div class="grid grid-cols-4 gap-2">
                    @foreach ([
                        'admin' => 'hover:bg-purple-50 hover:border-purple-300 text-purple-900',
                        'reviewer' => 'hover:bg-amber-50 hover:border-amber-300 text-amber-900',
                        'editor' => 'hover:bg-blue-50 hover:border-blue-300 text-blue-900',
                        'viewer' => 'hover:bg-emerald-50 hover:border-emerald-300 text-emerald-900',
                    ] as $role => $tone)
                        <button type="button" wire:click="demoLogin('{{ $role }}')" wire:loading.attr="disabled"
                                class="p-2 rounded-xl border border-slate-200 text-center {{ $tone }} transition text-[11px] font-semibold cursor-pointer capitalize">
                            {{ $role }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
