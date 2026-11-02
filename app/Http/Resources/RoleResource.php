<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'slug' => $this->slug,
            'permissions' => $this->permissions ?? [],
            'is_system' => $this->is_system,
            'users_count' => $this->users_count ?? 0,
            'created_by' => $this->createdBy?->name ?? 'System',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
