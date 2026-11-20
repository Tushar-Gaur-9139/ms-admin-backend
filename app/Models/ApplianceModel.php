<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApplianceModel extends Model
{
    protected $fillable = [
        'brand_id',
        'appliance_type_id',
        'model_number',
        'model_name',
        'series_year',
        'serial_prefix',
        'serial_range_start',
        'serial_range_end',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function applianceType(): BelongsTo
    {
        return $this->belongsTo(ApplianceType::class);
    }

    public function spareParts(): BelongsToMany
    {
        return $this->belongsToMany(SparePart::class, 'spare_part_model_compatibilities', 'appliance_model_id', 'spare_part_id')
            ->withPivot('serial_notes');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

