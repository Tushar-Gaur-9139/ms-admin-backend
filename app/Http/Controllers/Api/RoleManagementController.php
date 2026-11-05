<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\ValidationException;
use Illuminate\Support\Str;

class RoleManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRoleManagement($request);
        $roles = Role::query()
            ->with('createdBy:id,name')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return response()->json([
            'roles' => RoleResource::collection($roles)->resolve($request),
            'permissions' => collect(Role::PERMISSIONS)
                ->map(fn (string $label, string $key): array => [
                    'key' => $key,
                    'label' => $label,
                    'required' => $key === 'overview.view',
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request);
        $validated = $request->validate($this->roleRules());
        $name = trim($validated['name']);
        $slug = Str::slug($name, '_');

        if ($slug === '' || Role::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A role with this name already exists or the name is not valid.',
            ]);
        }

        $role = Role::create([
            'name' => $name,
            'slug' => $slug,
            'permissions' => array_keys(Role::PERMISSIONS),
            'is_system' => false,
            'created_by_user_id' => $actor->id,
        ]);

        $role->load('createdBy')->loadCount('users');

        return response()->json([
            'role' => RoleResource::make($role)->resolve($request),
        ], 201);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $this->authorizeRoleManagement($request);
        $role = Role::query()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate($this->roleRules());
        $role->update([
            'name' => trim($validated['name']),
        ]);

        $role->load('createdBy')->loadCount('users');

        return response()->json([
            'role' => RoleResource::make($role)->resolve($request),
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $this->authorizeRoleManagement($request);
        $role = Role::query()->where('slug', $slug)->firstOrFail();

        abort_if($role->users()->exists(), 409, 'Move all accounts to another role before deleting this role.');

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    private function authorizeRoleManagement(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->canManageRoles(),
            403,
            'Only an administrator or super administrator can create and manage roles.',
        );

        return $user;
    }

    private function roleRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }
}
