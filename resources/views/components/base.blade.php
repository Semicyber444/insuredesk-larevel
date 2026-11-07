@props(['title' => null])
<!DOCTYPE html>
<html lang="th" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? "{$title} — InsureDesk" : 'InsureDesk — ระบบค้นหาและเปรียบเทียบประกันรถยนต์ภายในทีม' }}</title>
    <meta name="description" content="ระบบจัดเก็บ ค้นหา และเปรียบเทียบข้อมูลประกันภัยรถยนต์จากหลายบริษัทอย่างเป็นระบบ">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full flex flex-col bg-slate-50 text-slate-900 font-sans">
    {{ $slot }}
    @livewireScripts
</body>
</html>
