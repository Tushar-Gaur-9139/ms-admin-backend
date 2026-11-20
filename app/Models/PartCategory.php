<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends Model
{
    protected $fillable = [
        'appliance_type_id',
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function applianceType(): BelongsTo
    {
        return $this->belongsTo(ApplianceType::class);
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

