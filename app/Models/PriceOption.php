<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceOption extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
        'net_premium' => 'float',
        'tax_stamp' => 'float',
        'premium_total' => 'float',
        'sum_insured_min' => 'float',
        'sum_insured_max' => 'float',
        'deductible_amount' => 'float',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EligibilityRule::class, 'eligibility_rule_id');
    }
}
