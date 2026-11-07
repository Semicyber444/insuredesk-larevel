<x-base :title="$title ?? null">
    <div class="min-h-screen bg-slate-50 flex flex-col text-slate-900">
        @include('partials.navbar')

        <main class="flex-1">{{ $slot }}</main>

        {{-- Footer disclaimer (Section 21) --}}
        <footer class="bg-white border-t border-slate-200 mt-12 py-6 print:hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-start gap-3 text-xs text-slate-600">
                    <x-lucide-alert-circle class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold text-slate-800 block">ข้อจำกัดและข้อกำหนดการใช้งานภายในทีม:</span>
                        <span>ระบบ InsureDesk นี้เป็นเครื่องมืออ้างอิงข้อมูลภายในทีมเท่านั้น ไม่ใช่การยืนยันราคา การรับประกัน หรือความคุ้มครองสำหรับรถคันใดโดยอัตโนมัติ ก่อนนำข้อมูลไปใช้งานจริง ต้องตรวจสอบเอกสารและเงื่อนไขที่มีผลบังคับใช้กับบริษัทประกันภัยเสมอ</span>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 mt-4">
                    <span>© 2026 InsureDesk — ระบบจัดการและค้นหาประกันภัยรถยนต์ภายในทีม</span>
                    <span>Laravel + Livewire + MySQL</span>
                </div>
            </div>
        </footer>
    </div>
</x-base>
