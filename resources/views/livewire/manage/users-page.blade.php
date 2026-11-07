@php use App\Support\Format; @endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2"><x-lucide-users class="w-6 h-6 text-blue-600" /><span>จัดการสมาชิกและบทบาท (Team Members)</span></h1>
            <p class="text-xs text-slate-500 mt-1">จำกัดการเข้าใช้งานเฉพาะบัญชีที่ผู้ดูแลระบบสร้างให้เท่านั้น</p>
        </div>
        <button type="button" onclick="alert('เพิ่มสมาชิกจริงได้ด้วยคำสั่ง php artisan insuredesk:create-user บนเซิร์ฟเวอร์ (ดูรายละเอียดใน README)')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition self-start sm:self-auto cursor-pointer">
            <x-lucide-user-plus class="w-4 h-4" /><span>คำแนะนำการเชิญสมาชิก</span>
        </button>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">บัญชีและบทบาทมาตรฐานในระบบ ({{ $users->count() }} บัญชี)</div>
        <div class="divide-y divide-slate-100">
            @foreach ($users as $u)
                <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-800 flex items-center justify-center font-bold text-sm">{{ mb_substr($u->name, 0, 1) }}</div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ $u->name }}</h3>
                            <div class="flex items-center gap-2 text-xs text-slate-500"><x-lucide-mail class="w-3.5 h-3.5" /><span>{{ $u->email }}</span></div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($u->roleNames() as $r)
                            <span class="text-xs px-2.5 py-1 rounded-lg border font-semibold {{ Format::roleBadge($r) }}">{{ Format::role($r) }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
