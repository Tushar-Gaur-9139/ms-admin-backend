<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_B2C = 'b2c';

    public const TYPE_B2B = 'b2b';

    public const CLASSIFICATION_NEW = 'new';

    public const CLASSIFICATION_EXISTING = 'existing';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_code',
        'customer_type',
        'classification',
        'name',
        'first_name',
        'last_name',
        'phone',
        'email',
        'alternate_phone',
        'date_of_birth',
        'gender',
        'business_name',
        'legal_name',
        'trade_name',
        'gst_number',
        'pan_number',
        'registration_number',
        'industry',
        'company_size',
        'company_type',
        'website',
        'annual_revenue',
        'job_title',
        'department',
        'credit_limit',
        'address_line1',
        'address_line2',
        'country',
        'city',
        'state',
        'pincode',
        'segment',
        'stage',
        'primary_manager',
        'customer_priority',
        'billing_email',
        'payment_terms',
        'preferred_currency',
        'preferred_language',
        'orders_count',
        'total_spent',
        'notes',
        'is_active',
        'source',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'orders_count' => 'integer',
            'total_spent' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'date_of_birth' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (empty($customer->customer_code)) {
                $maxId = static::withTrashed()->max('id') ?? 0;
                $customer->customer_code = sprintf('MSE%07d', $maxId + 1);
            }

            if (empty($customer->name)) {
                $customer->name = trim("{$customer->first_name} {$customer->last_name}");
            }
        });
    }

    public function isB2B(): bool
    {
        return $this->customer_type === self::TYPE_B2B;
    }

    public function isB2C(): bool
    {
        return $this->customer_type === self::TYPE_B2C;
    }

    public function isExisting(): bool
    {
        return $this->classification === self::CLASSIFICATION_EXISTING;
    }

    public function isNew(): bool
    {
        return $this->classification === self::CLASSIFICATION_NEW;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeB2b(Builder $query): Builder
    {
        return $query->where('customer_type', self::TYPE_B2B);
    }

    public function scopeB2c(Builder $query): Builder
    {
        return $query->where('customer_type', self::TYPE_B2C);
    }

    public function scopeNewCustomers(Builder $query): Builder
    {
        return $query->where('classification', self::CLASSIFICATION_NEW);
    }

    public function scopeExistingCustomers(Builder $query): Builder
    {
        return $query->where('classification', self::CLASSIFICATION_EXISTING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $clean = trim($term);

        return $query->where(function (Builder $sub) use ($clean): void {
            $sub->where('name', 'like', "%{$clean}%")
                ->orWhere('customer_code', 'like', "%{$clean}%")
                ->orWhere('first_name', 'like', "%{$clean}%")
                ->orWhere('last_name', 'like', "%{$clean}%")
                ->orWhere('phone', 'like', "%{$clean}%")
                ->orWhere('alternate_phone', 'like', "%{$clean}%")
                ->orWhere('email', 'like', "%{$clean}%")
                ->orWhere('business_name', 'like', "%{$clean}%")
                ->orWhere('legal_name', 'like', "%{$clean}%")
                ->orWhere('trade_name', 'like', "%{$clean}%")
                ->orWhere('gst_number', 'like', "%{$clean}%")
                ->orWhere('pan_number', 'like', "%{$clean}%")
                ->orWhere('city', 'like', "%{$clean}%")
                ->orWhere('pincode', 'like', "%{$clean}%");
        });
    }
}
