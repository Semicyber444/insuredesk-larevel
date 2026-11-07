<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EligibilityRule extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['min_age' => 'integer', 'max_age' => 'integer', 'inspection_required' => 'boolean'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PriceOption::class)->orderBy('sort_order')->orderBy('id');
    }
}
