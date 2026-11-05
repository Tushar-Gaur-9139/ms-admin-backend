<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_name' => $this->roleDefinition?->name ?? Str::headline($this->role),
            'permissions' => $this->permissionKeys(),
            'is_active' => $this->is_active,
            'created_by' => $this->createdBy?->name ?? 'Self registration',
            'created_at' => $this->created_at?->toISOString(),
            'last_action' => $this->latestActivity?->action,
            'last_changed_by' => $this->latestActivity?->actor_name,
            'last_changed_at' => $this->latestActivity?->created_at?->toISOString(),
        ];
    }
}
