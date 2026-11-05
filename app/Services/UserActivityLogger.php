<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class UserActivityLogger
{
    public function record(Request $request, User $actor, string $action, User $subject, string $summary, array $changes = []): UserActivityLog
    {
        return UserActivityLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'action' => $action,
            'subject_user_id' => $subject->id,
            'subject_name' => $subject->name,
            'subject_email' => $subject->email,
            'subject_role' => $subject->role,
            'summary' => $summary,
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => $request->ip(),
        ]);
    }

    public function diff(User $user, array $original, bool $passwordChanged): array
    {
        $changes = [];

        foreach (['name', 'email', 'role'] as $field) {
            if ($user->{$field} !== $original[$field]) {
                $changes[$field] = [
                    'from' => $original[$field],
                    'to' => $user->{$field},
                ];
            }
        }

        if ($passwordChanged) {
            $changes['password'] = ['changed' => true];
        }

        return $changes;
    }

    public function summary(User $actor, User $subject, string $action, array $changes = []): string
    {
        $actorName = $actor->name;
        $subjectName = $subject->name;

        return match ($action) {
            'created' => "{$actorName} created {$subjectName} ({$subject->email}) with the {$this->roleName($subject->role)} role.",
            'blocked' => "{$actorName} blocked {$subjectName} ({$subject->email}).",
            'unblocked' => "{$actorName} unblocked {$subjectName} ({$subject->email}).",
            'deleted' => "{$actorName} deleted {$subjectName} ({$subject->email}), who had the {$this->roleName($subject->role)} role.",
            'role_changed' => "{$actorName} changed {$subjectName}'s role from {$this->roleName($changes['role']['from'] ?? null)} to {$this->roleName($subject->role)}.",
            default => "{$actorName} updated {$subjectName}: {$this->changeText($changes)}.",
        };
    }

    private function changeText(array $changes): string
    {
        $parts = [];

        if (isset($changes['name'])) {
            $parts[] = "name from \"{$changes['name']['from']}\" to \"{$changes['name']['to']}\"";
        }

        if (isset($changes['email'])) {
            $parts[] = "email from {$changes['email']['from']} to {$changes['email']['to']}";
        }

        if (isset($changes['role'])) {
            $parts[] = "role from {$this->roleName($changes['role']['from'])} to {$this->roleName($changes['role']['to'])}";
        }

        if (isset($changes['password'])) {
            $parts[] = 'password';
        }

        return $parts === [] ? 'account details' : implode(', ', $parts);
    }

    private function roleName(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return 'no role';
        }

        return Role::query()->where('slug', $slug)->value('name') ?? $slug;
    }
}
