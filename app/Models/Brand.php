<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'short_code',
        'logo_url',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
            if (empty($brand->short_code)) {
                $brand->short_code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $brand->name), 0, 4));
            } else {
                $brand->short_code = strtoupper($brand->short_code);
            }
        });

        static::updating(function (Brand $brand) {
            $brand->short_code = strtoupper($brand->short_code);
        });
    }

    // Relationships
    public function applianceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ApplianceType::class, 'brand_appliances');
    }

    public function applianceModels(): HasMany
    {
        return $this->hasMany(ApplianceModel::class);
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function generateNextSku(): string
    {
        $lastPart = SparePart::where('brand_id', $this->id)
            ->orderByDesc('id')
            ->first(['sku']);

        $seq = 1;
        if ($lastPart) {
            $parts = explode('-', $lastPart->sku);
            $seq = (int) end($parts) + 1;
        }

        return sprintf('ME-%s-%04d', $this->short_code, $seq);
    }
}

