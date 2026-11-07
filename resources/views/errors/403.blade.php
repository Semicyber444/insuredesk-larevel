<x-base title="ไม่มีสิทธิ์เข้าถึง">
    <div class="max-w-xl mx-auto px-4 py-20 text-center space-y-3">
        <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center mx-auto"><x-lucide-lock class="w-6 h-6" /></div>
        <h2 class="text-lg font-black text-slate-900">ไม่มีสิทธิ์เข้าถึงหน้านี้</h2>
        <p class="text-xs text-slate-500">{{ $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'บทบาทของคุณไม่ได้รับสิทธิ์สำหรับหน้านี้' }}</p>
        <a href="{{ url('/search') }}" class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-bold">กลับไปหน้าค้นหาประกัน</a>
    </div>
</x-base>
