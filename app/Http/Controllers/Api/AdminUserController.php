<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRoleManagement($request);

        return response()->json([
            'users' => UserResource::collection(
                User::query()->with(['roleDefinition', 'createdBy:id,name'])->orderBy('name')->get(),
            )->resolve($request),
            'roles' => Role::query()->orderBy('name')->get(['name', 'slug']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request);
        $this->normalizeEmail($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8)],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
        ]);
        $role = Role::query()->where('slug', $validated['role'])->firstOrFail();
        $this->authorizeRoleAssignment($actor, $role);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $user->role = $role->slug;
        $user->created_by_user_id = $actor->id;
        $user->save();

        return response()->json([
            'message' => 'User account created.',
            'user' => UserResource::make($user->refresh()->load(['roleDefinition', 'createdBy:id,name']))->resolve($request),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request);
        $this->authorizeTargetUser($actor, $user);
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', PasswordRule::min(8)],
            'role' => ['sometimes', 'required', 'string', Rule::exists('roles', 'slug')],
        ]);

        if (array_key_exists('role', $validated)) {
            abort_if(
                $user->is($actor) && $validated['role'] !== $user->role,
                403,
                'You cannot change your own role.',
            );
            $role = Role::query()->where('slug', $validated['role'])->firstOrFail();
            $this->authorizeRoleAssignment($actor, $role);
            $user->role = $role->slug;
        }

        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = trim($validated[$field]);
            }
        }

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return response()->json([
            'message' => 'User account updated.',
            'user' => UserResource::make($user->load('roleDefinition'))->resolve($request),
        ]);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request);
        $this->authorizeTargetUser($actor, $user);
        abort_if($user->is($actor), 403, 'You cannot change your own role.');

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
        ]);
        $role = Role::query()->where('slug', $validated['role'])->firstOrFail();
        $this->authorizeRoleAssignment($actor, $role);
        $user->role = $role->slug;
        $user->save();

        return response()->json([
            'message' => 'User role updated.',
            'user' => UserResource::make($user->load('roleDefinition'))->resolve($request),
        ]);
    }

    public function setStatus(Request $request, User $user): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request);
        $this->authorizeTargetUser($actor, $user);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        abort_if(! $validated['is_active'] && $user->is($actor), 403, 'You cannot block your own account.');
        abort_if(
            ! $validated['is_active']
                && $user->isSuperAdmin()
                && User::query()->where('role', 'super_admin')->where('is_active', true)->count() <= 1,
            409,
            'The last active super administrator cannot be blocked.',
        );

        $user->is_active = $validated['is_active'];
        $user->save();

        return response()->json([
            'message' => $user->is_active ? 'User account unblocked.' : 'User account blocked.',
            'user' => UserResource::make($user->load('roleDefinition'))->resolve($request),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request);
        $this->authorizeTargetUser($actor, $user);

        abort_if($user->is($actor), 403, 'You cannot delete your own account.');
        abort_if(
            $user->isSuperAdmin() && User::query()->where('role', 'super_admin')->count() <= 1,
            409,
            'The last super administrator cannot be deleted.',
        );

        $user->delete();

        return response()->json(['message' => 'User account deleted.']);
    }

    private function authorizeRoleManagement(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->canManageRoles(),
            403,
            'Only administrators can manage users and roles.',
        );

        return $user;
    }

    private function authorizeTargetUser(User $actor, User $target): void
    {
        abort_if(
            ! $actor->isSuperAdmin() && in_array($target->role, ['admin', 'super_admin'], true),
            403,
            'Only a super administrator can manage administrator accounts.',
        );
    }

    private function authorizeRoleAssignment(User $actor, Role $role): void
    {
        abort_if(
            ! $actor->isSuperAdmin() && in_array($role->slug, ['admin', 'super_admin'], true),
            403,
            'Only a super administrator can assign administrator roles.',
        );
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
    }
}
