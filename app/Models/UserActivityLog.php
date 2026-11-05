<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_user_id',
    'actor_name',
    'actor_role',
    'action',
    'subject_user_id',
    'subject_name',
    'subject_email',
    'subject_role',
    'summary',
    'changes',
    'ip_address',
])]
class UserActivityLog extends Model
{
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }
}
