<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditLog extends Model
{
    use HasUuids;

    protected $guarded = [];

    public const UPDATED_AT = null;

    protected $casts = ['before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];

    protected $dateFormat = 'Y-m-d H:i:s.v';
}
