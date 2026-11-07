<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasUuids;

    public const ROLE_PRIORITY = ['admin', 'reviewer', 'editor', 'viewer'];

    /** Seeded by database/seeders/sql/demo.sql. They have no password and only work while demo auth is on. */
    public const DEMO_USER_IDS = [
        'admin' => '00000000-0000-0000-0000-000000000001',
        'reviewer' => '00000000-0000-0000-0000-000000000004',
        'editor' => '00000000-0000-0000-0000-000000000002',
        'viewer' => '00000000-0000-0000-0000-000000000003',
    ];

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    private ?array $roleNames = null;

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** Held roles, highest first. Cached per request: every permission check reads it. */
    public function roleNames(): array
    {
        if ($this->roleNames === null) {
            $held = $this->roles()->pluck('role')->all();
            $this->roleNames = array_values(array_intersect(self::ROLE_PRIORITY, $held));
        }

        return $this->roleNames;
    }

    public function primaryRole(): string
    {
        return $this->roleNames()[0] ?? 'viewer';
    }

    public function isDemo(): bool
    {
        return in_array($this->id, self::DEMO_USER_IDS, true);
    }

    public static function demoAuthEnabled(): bool
    {
        return (bool) config('insuredesk.demo_auth');
    }
}
