<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'permissions', 'is_system', 'created_by_user_id'])]
class Role extends Model
{
    public const PERMISSIONS = [
        'overview.view' => 'Overview',
        'orders.view' => 'Orders',
        'products.view' => 'Products',
        'inventory.view' => 'Inventory',
        'customers.view' => 'Customers',
        'reports.view' => 'Reports',
        'settings.view' => 'Settings',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
