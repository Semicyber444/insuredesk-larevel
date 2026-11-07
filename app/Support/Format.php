<?php

namespace App\Support;

/** Thai labels and number formats shared by services and views (from src/types/insurance.ts and roles.ts). */
class Format
{
    public const INSURANCE_TYPES = ['ชั้น 1', 'ชั้น 2+', 'ชั้น 3+', 'ชั้น 2', 'ชั้น 3'];

    public const ROLES = [
        'admin' => 'ผู้ดูแลระบบ (Admin)',
        'reviewer' => 'ผู้ตรวจสอบ (Reviewer)',
        'editor' => 'ผู้กรอกข้อมูล (Editor)',
        'viewer' => 'สมาชิกทั่วไป (Viewer)',
    ];

    public const ROLE_BADGES = [
        'admin' => 'bg-purple-100 text-purple-800 border-purple-200',
        'reviewer' => 'bg-amber-100 text-amber-800 border-amber-200',
        'editor' => 'bg-blue-100 text-blue-800 border-blue-200',
        'viewer' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    ];

    /** Like JavaScript's toLocaleString(): thousands separators, decimals only when there are any. */
    public static function number(int|float|string|null $value): string
    {
        $value = (float) $value;

        return number_format($value, fmod($value, 1.0) == 0.0 ? 0 : 2);
    }

    public static function money(int|float|string|null $value, string $empty = 'ยังไม่มีข้อมูลราคา'): string
    {
        return $value === null || $value === '' ? $empty : '฿'.self::number($value);
    }

    /** An empty bound means no limit on that side, the same way search treats it. */
    public static function carAgeRange(?int $min, ?int $max): string
    {
        return match (true) {
            $min !== null && $max !== null => "{$min}-{$max} ปี",
            $min !== null => "{$min} ปีขึ้นไป",
            $max !== null => "ไม่เกิน {$max} ปี",
            default => 'ไม่จำกัด',
        };
    }

    public static function registrationZone(?string $zone, bool $short = false): string
    {
        return match ($zone) {
            'bangkok_only' => $short ? 'เฉพาะป้าย กทม.' : 'เฉพาะรถป้าย กทม. เท่านั้น',
            'bangkok_metro' => $short ? 'เฉพาะป้าย กทม./ปริมณฑล' : 'เฉพาะรถป้าย กทม. และปริมณฑล',
            'upcountry_only' => $short ? 'เฉพาะป้าย ตจว.' : 'เฉพาะรถป้ายต่างจังหวัด (ตจว.)',
            default => $short ? 'ทุกป้ายทะเบียน' : 'รับทุกป้ายทะเบียน (ทั่วประเทศ)',
        };
    }

    public static function role(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }

    public static function roleBadge(string $role): string
    {
        return self::ROLE_BADGES[$role] ?? self::ROLE_BADGES['viewer'];
    }

    /** Like toLocaleDateString('th-TH'): 7/11/2569, in the Buddhist calendar. */
    public static function thaiDate(mixed $date, string $pattern = 'd/M/yyyy'): string
    {
        if (! $date) {
            return '-';
        }
        $formatter = new \IntlDateFormatter('th_TH@calendar=buddhist', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE,
            config('app.timezone'), \IntlDateFormatter::TRADITIONAL, $pattern);

        return $formatter->format(\Illuminate\Support\Carbon::parse($date));
    }

    /** Like toLocaleString('th-TH'): date and time. */
    public static function thaiDateTime(mixed $date): string
    {
        return self::thaiDate($date, 'd/M/yyyy HH:mm:ss');
    }
}
