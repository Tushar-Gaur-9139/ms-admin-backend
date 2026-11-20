<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class SparePart extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'slug',
        'brand_id',
        'appliance_type_id',
        'part_category_id',
        'description',
        'purchase_price',
        'b2c_price',
        'b2b_price',
        'mrp_price',
        'stock_quantity',
        'min_stock_alert',
        'rack_location',
        'qc_status',
        'is_active',
        'is_featured',
        'image_url',
        'specs_json',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'b2c_price' => 'decimal:2',
        'b2b_price' => 'decimal:2',
        'mrp_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_alert' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'specs_json' => 'array',
    ];

    public const QC_ORIGINAL = 'original_oem';
    public const QC_TESTED = 'tested_qc_pass';
    public const QC_UNIVERSAL = 'universal_fit';

    protected static function booted(): void
    {
        static::creating(function (SparePart $part) {
            if (empty($part->slug)) {
                $part->slug = Str::slug($part->name) . '-' . strtolower($part->sku ?? Str::random(6));
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function applianceType(): BelongsTo
    {
        return $this->belongsTo(ApplianceType::class);
    }

    public function partCategory(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class);
    }

    public function compatibleModels(): BelongsToMany
    {
        return $this->belongsToMany(ApplianceModel::class, 'spare_part_model_compatibilities', 'spare_part_id', 'appliance_model_id')
            ->withPivot('serial_notes');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_alert')
                     ->where('stock_quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', 0);
    }

    public function scopeForBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeForAppliance($query, int $applianceTypeId)
    {
        return $query->where('appliance_type_id', $applianceTypeId);
    }

    // Helpers
    public function isLowStock(): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->min_stock_alert;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity === 0;
    }

    public function qcLabel(): string
    {
        return match ($this->qc_status) {
            self::QC_ORIGINAL => 'Original OEM',
            self::QC_TESTED => 'Tested QC Pass',
            self::QC_UNIVERSAL => 'Universal Fit',
            default => ucfirst($this->qc_status),
        };
    }
}

