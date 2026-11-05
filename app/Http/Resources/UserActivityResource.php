<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'summary' => $this->summary,
            'actor_name' => $this->actor_name,
            'actor_role' => $this->actor_role,
            'subject_user_id' => $this->subject_user_id,
            'subject_name' => $this->subject_name,
            'subject_email' => $this->subject_email,
            'subject_role' => $this->subject_role,
            'changes' => $this->changes ?? [],
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
