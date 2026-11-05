<?php

use App\Models\Role;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeader('Origin', 'http://localhost:5173')
        ->withHeader('Referer', 'http://localhost:5173');
});

it('registers new accounts as customers even if a role is submitted', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'New Staff Applicant',
        'email' => 'New.User@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'role' => 'admin',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonPath('user.email', 'new.user@example.com');

    $this->assertDatabaseHas('users', [
        'email' => 'new.user@example.com',
        'role' => 'customer',
    ]);
});

it('rejects malformed or SQL-injection-shaped emails with a friendly validation error', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => "' OR 1=1 --",
        'password' => 'anything',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Enter a valid email address.');

    $this->assertDatabaseCount('users', 0);
});

it('allows staff to log in and retrieve their shared account details', function (): void {
    $user = User::factory()->create([
        'email' => 'staff@example.com',
        'password' => 'correct-password',
        'role' => 'orders',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'STAFF@example.com',
        'password' => 'correct-password',
        'remember' => true,
    ])->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'orders');

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('user.email', 'staff@example.com');
});

it('lets administrators create dynamic roles by name and assign them to users', function (): void {
    $admin = User::factory()->create([
        'name' => 'Store Administrator',
        'email' => 'admin@example.com',
        'password' => 'correct-password',
        'role' => 'admin',
    ]);
    $customer = User::factory()->create([
        'email' => 'customer@example.com',
        'role' => 'customer',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $this->getJson('/api/v1/admin/roles')->assertOk();

    $roleResponse = $this->postJson('/api/v1/admin/roles', [
        'name' => 'Warehouse Lead',
    ])->assertCreated()
        ->assertJsonPath('role.slug', 'warehouse_lead')
        ->assertJsonPath('role.name', 'Warehouse Lead')
        ->assertJsonPath('role.created_by', 'Store Administrator');

    expect($roleResponse->json('role.created_at'))->not->toBeNull();
    expect($roleResponse->json('role.permissions'))->toBe(array_keys(Role::PERMISSIONS));

    $this->putJson('/api/v1/admin/roles/warehouse_lead', [
        'name' => 'Warehouse Manager',
    ])->assertOk()
        ->assertJsonPath('role.name', 'Warehouse Manager');

    $this->patchJson('/api/v1/admin/users/'.$customer->id.'/role', [
        'role' => 'warehouse_lead',
    ])->assertOk()
        ->assertJsonPath('user.role', 'warehouse_lead')
        ->assertJsonPath('user.role_name', 'Warehouse Manager');

    expect($customer->fresh()->role)->toBe('warehouse_lead');
});

it('allows administrators to delete unused roles but keeps roles with assigned users', function (): void {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'correct-password',
        'role' => 'admin',
    ]);
    $assignedUser = User::factory()->create(['role' => 'catalog']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $this->deleteJson('/api/v1/admin/roles/orders')
        ->assertOk()
        ->assertJsonPath('message', 'Role deleted.');

    $this->deleteJson('/api/v1/admin/roles/catalog')
        ->assertStatus(409)
        ->assertJsonPath('message', 'Move all accounts to another role before deleting this role.');

    expect($assignedUser->fresh()->role)->toBe('catalog');
});

it('allows administrators to create, edit, block, unblock, and delete user accounts', function (): void {
    User::factory()->create([
        'name' => 'Store Administrator',
        'email' => 'admin@example.com',
        'password' => 'correct-password',
        'role' => 'admin',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $userResponse = $this->postJson('/api/v1/admin/users', [
        'name' => 'New Catalog Staff',
        'email' => 'NEW.STAFF@example.com',
        'password' => 'temporary-password',
        'role' => 'catalog',
    ])->assertCreated()
        ->assertJsonPath('user.name', 'New Catalog Staff')
        ->assertJsonPath('user.email', 'new.staff@example.com')
        ->assertJsonPath('user.role', 'catalog')
        ->assertJsonPath('user.is_active', true)
        ->assertJsonPath('user.created_by', 'Store Administrator');

    $userId = $userResponse->json('user.id');
    expect($userResponse->json('user.created_at'))->not->toBeNull();

    $this->putJson('/api/v1/admin/users/'.$userId, [
        'name' => 'Updated Catalog Staff',
        'email' => 'updated.staff@example.com',
    ])->assertOk()
        ->assertJsonPath('user.name', 'Updated Catalog Staff')
        ->assertJsonPath('user.email', 'updated.staff@example.com');

    $this->patchJson('/api/v1/admin/users/'.$userId.'/status', [
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('user.is_active', false);

    $this->patchJson('/api/v1/admin/users/'.$userId.'/status', [
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('user.is_active', true);

    $this->deleteJson('/api/v1/admin/users/'.$userId)
        ->assertOk()
        ->assertJsonPath('message', 'User account deleted.')
        ->assertJsonPath('activity.action', 'deleted')
        ->assertJsonPath('activity.actor_name', 'Store Administrator');

    $this->assertDatabaseMissing('users', ['id' => $userId]);
    $this->assertDatabaseHas('user_activity_logs', [
        'action' => 'created',
        'actor_name' => 'Store Administrator',
        'subject_email' => 'new.staff@example.com',
    ]);
    $this->assertDatabaseHas('user_activity_logs', [
        'action' => 'deleted',
        'actor_name' => 'Store Administrator',
        'subject_email' => 'updated.staff@example.com',
        'subject_user_id' => null,
    ]);
    expect(UserActivityLog::query()->pluck('changes')->toJson())->not->toContain('temporary-password');
});

it('denies blocked staff accounts access to the admin panel', function (): void {
    User::factory()->create([
        'email' => 'blocked@example.com',
        'password' => 'correct-password',
        'role' => 'catalog',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'blocked@example.com',
        'password' => 'correct-password',
    ])->assertForbidden()
        ->assertJsonPath('message', 'This account has been blocked. Please contact an administrator.');
});

it('prevents regular administrators from changing privileged user accounts', function (): void {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'correct-password',
        'role' => 'admin',
    ]);
    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $this->putJson('/api/v1/admin/users/'.$superAdmin->id, [
        'name' => 'Changed Super Admin',
    ])->assertForbidden();
    $this->patchJson('/api/v1/admin/users/'.$superAdmin->id.'/status', [
        'is_active' => false,
    ])->assertForbidden();
    $this->deleteJson('/api/v1/admin/users/'.$superAdmin->id)->assertForbidden();
    $this->postJson('/api/v1/admin/users', [
        'name' => 'Unauthorized Admin',
        'email' => 'unauthorized.admin@example.com',
        'password' => 'temporary-password',
        'role' => 'super_admin',
    ])->assertForbidden();
});

it('restricts role management and protects administrator role assignment', function (): void {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'correct-password',
        'role' => 'admin',
    ]);
    $staff = User::factory()->create(['role' => 'customer']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $this->patchJson('/api/v1/admin/users/'.$staff->id.'/role', [
        'role' => 'super_admin',
    ])->assertForbidden();
});

it('denies non-admin staff access to role management endpoints', function (): void {
    User::factory()->create([
        'email' => 'staff@example.com',
        'password' => 'correct-password',
        'role' => 'orders',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'staff@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $this->getJson('/api/v1/admin/roles')->assertForbidden();
    $this->postJson('/api/v1/admin/roles', [
        'name' => 'Warehouse Lead',
    ])->assertForbidden()
        ->assertJsonPath('message', 'Only an administrator or super administrator can create and manage roles.');
});

it('allows super administrators to assign administrator roles', function (): void {
    User::factory()->create([
        'email' => 'super@example.com',
        'password' => 'correct-password',
        'role' => 'super_admin',
    ]);
    $staff = User::factory()->create(['role' => 'customer']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'super@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $this->patchJson('/api/v1/admin/users/'.$staff->id.'/role', [
        'role' => 'admin',
    ])->assertOk()
        ->assertJsonPath('user.role', 'admin');
});

it('denies customer accounts access to the admin panel', function (): void {
    User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'correct-password',
        'role' => 'customer',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'customer@example.com',
        'password' => 'correct-password',
    ])->assertForbidden()
        ->assertJsonPath('message', 'This account does not have staff access yet. Please contact an administrator.');

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('allows a server administrator to assign an approved staff role', function (): void {
    $user = User::factory()->create([
        'email' => 'new.staff@example.com',
        'role' => 'customer',
    ]);

    $this->artisan('users:assign-role', [
        'email' => 'NEW.STAFF@example.com',
        'role' => 'catalog',
    ])->assertExitCode(0);

    expect($user->fresh()->role)->toBe('catalog');
});

it('signs staff out and invalidates their admin session', function (): void {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'correct-password',
        'role' => 'admin',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'correct-password',
    ])->assertOk();

    $this->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'You have been signed out.');

    Auth::forgetGuards();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('sends password reset notifications without revealing whether an email exists', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'staff@example.com']);

    $knownEmailResponse = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $user->email,
    ]);
    $unknownEmailResponse = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'missing@example.com',
    ]);

    $knownEmailResponse->assertOk()
        ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
    $unknownEmailResponse->assertOk()
        ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets passwords with a valid one-time reset token', function (): void {
    $user = User::factory()->create([
        'email' => 'staff@example.com',
        'password' => 'old-password',
        'role' => 'admin',
    ]);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'STAFF@example.com',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertOk()
        ->assertJsonPath('message', 'Your password has been reset. You can now sign in.');

    expect(password_verify('new-secure-password', $user->fresh()->password))->toBeTrue();
});
