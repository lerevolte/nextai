<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbTestVariant extends Model
{
    protected $fillable = [
        'ab_test_id',
        'name',
        'description',
        'config',
        'traffic_allocation',
        'is_control',
        'conversions',
        'participants',
        'conversion_rate',
        'metrics'
    ];

    protected $casts = [
        'config' => 'array',
        'metrics' => 'array',
        'is_control' => 'boolean',
        'traffic_allocation' => 'float',
        'conversion_rate' => 'float'
    ];

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(AbTest::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AbTestResult::class, 'variant_id');
    }
}