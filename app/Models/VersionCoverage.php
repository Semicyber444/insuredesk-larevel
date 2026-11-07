<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersionCoverage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['limit_amount' => 'float', 'deductible_amount' => 'float'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(CoverageType::class, 'coverage_type_id');
    }
}
