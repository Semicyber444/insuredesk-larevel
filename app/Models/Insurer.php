<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insurer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }
}
