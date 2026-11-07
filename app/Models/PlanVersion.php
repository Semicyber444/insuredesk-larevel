<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanVersion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['version_no' => 'integer'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(EligibilityRule::class);
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(VersionCoverage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SourceDocument::class)->orderByDesc('created_at')->orderBy('id');
    }
}
