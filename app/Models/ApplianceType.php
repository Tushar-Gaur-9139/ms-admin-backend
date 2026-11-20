<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApplianceType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ApplianceType $at) {
            if (empty($at->slug)) {
                $at->slug = Str::slug($at->name);
            }
        });
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_appliances');
    }

    public function applianceModels(): HasMany
    {
        return $this->hasMany(ApplianceModel::class);
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    public function partCategories(): HasMany
    {
        return $this->hasMany(PartCategory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

