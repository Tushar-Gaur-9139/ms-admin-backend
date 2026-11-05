<?php

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['sanctum.stateful' => ['localhost:5175']]);
    $this->withHeader('Origin', 'http://localhost:5175')
        ->withHeader('Referer', 'http://localhost:5175');
});

it('allows admin staff to list customers and returns correct classification and type stats', function (): void {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    Customer::factory()->b2c()->newCustomer()->create(['name' => 'John Retail New']);
    Customer::factory()->b2c()->existing()->create(['name' => 'Alice Retail Old']);
    Customer::factory()->b2b()->newCustomer()->create(['name' => 'Bob Shop New', 'business_name' => 'Bob Electronics']);
    Customer::factory()->b2b()->existing()->create(['name' => 'David Wholesale Old', 'business_name' => 'David Spare Parts']);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/admin/customers');

    $response->assertOk()
        ->assertJsonCount(4, 'customers')
        ->assertJsonPath('stats.total', 4)
        ->assertJsonPath('stats.b2c', 2)
        ->assertJsonPath('stats.b2b', 2)
        ->assertJsonPath('stats.new', 2)
        ->assertJsonPath('stats.existing', 2);
});

it('allows admin to register a new B2B customer with GST and company details', function (): void {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $payload = [
        'customer_type' => 'b2b',
        'classification' => 'new',
        'name' => 'Vikram Singh',
        'business_name' => 'Star Refrigeration & Spares',
        'gst_number' => '07AAAAA0000A1Z5',
        'pan_number' => 'AAAAA0000A',
        'phone' => '9812345678',
        'email' => 'vikram@starref.com',
        'city' => 'Delhi',
        'state' => 'Delhi',
        'pincode' => '110001',
        'credit_limit' => 50000,
        'notes' => 'New B2B client in central Delhi.',
    ];

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/admin/customers', $payload);

    $response->assertCreated()
        ->assertJsonPath('customer.customer_type', 'b2b')
        ->assertJsonPath('customer.classification', 'new')
        ->assertJsonPath('customer.business_name', 'Star Refrigeration & Spares')
        ->assertJsonPath('customer.gst_number', '07AAAAA0000A1Z5');

    $this->assertDatabaseHas('customers', [
        'phone' => '9812345678',
        'customer_type' => 'b2b',
        'classification' => 'new',
        'business_name' => 'Star Refrigeration & Spares',
    ]);
});

it('allows admin to register an existing/old B2C customer with past history', function (): void {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $payload = [
        'customer_type' => 'b2c',
        'classification' => 'existing',
        'name' => 'Suman Gupta',
        'phone' => '9998887776',
        'email' => 'suman@example.com',
        'city' => 'Noida',
        'state' => 'Uttar Pradesh',
        'pincode' => '201301',
        'notes' => 'Old offline store customer since 2023.',
    ];

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/admin/customers', $payload);

    $response->assertCreated()
        ->assertJsonPath('customer.customer_type', 'b2c')
        ->assertJsonPath('customer.classification', 'existing');

    $this->assertDatabaseHas('customers', [
        'phone' => '9998887776',
        'customer_type' => 'b2c',
        'classification' => 'existing',
    ]);
});

it('allows any staff role to create a customer, but STRICTLY prevents non-admins from editing or deleting', function (): void {
    // Create custom role for staff member with customer view permission
    $staffRole = Role::create([
        'name' => 'Store Staff',
        'slug' => 'store_staff',
        'permissions' => ['customers.view', 'overview.view'],
        'is_system' => false,
    ]);

    $staffUser = User::factory()->create([
        'role' => 'store_staff',
        'is_active' => true,
    ]);

    $adminUser = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    // 1. Staff can create a new customer
    $createResponse = $this->actingAs($staffUser)->postJson('/api/v1/admin/customers', [
        'customer_type' => 'b2c',
        'name' => 'Client Created by Staff',
        'phone' => '9800112233',
        'city' => 'Delhi',
    ]);
    $createResponse->assertCreated();
    $customerId = $createResponse->json('customer.id');

    // 2. Staff tries to EDIT the customer -> MUST FAIL 403 Forbidden!
    $editAttempt = $this->actingAs($staffUser)->putJson("/api/v1/admin/customers/{$customerId}", [
        'customer_type' => 'b2c',
        'name' => 'Client Modified by Staff',
        'phone' => '9800112233',
    ]);
    $editAttempt->assertForbidden();

    // 3. Staff tries to DELETE the customer -> MUST FAIL 403 Forbidden!
    $deleteAttempt = $this->actingAs($staffUser)->deleteJson("/api/v1/admin/customers/{$customerId}");
    $deleteAttempt->assertForbidden();

    // 4. Admin CAN edit the customer
    $adminEdit = $this->actingAs($adminUser)->putJson("/api/v1/admin/customers/{$customerId}", [
        'customer_type' => 'b2c',
        'name' => 'Client Modified by Admin',
        'phone' => '9800112233',
    ]);
    $adminEdit->assertOk()
        ->assertJsonPath('customer.name', 'Client Modified by Admin');

    // 5. Admin CAN delete the customer
    $adminDelete = $this->actingAs($adminUser)->deleteJson("/api/v1/admin/customers/{$customerId}");
    $adminDelete->assertOk();
    $this->assertSoftDeleted('customers', ['id' => $customerId]);
});

it('allows public website visitors to lookup their customer status and self-register', function (): void {
    // 1. Lookup non-existing customer
    $lookupResponse = $this->postJson('/api/v1/store/customers/lookup', [
        'phone' => '9111222333',
    ]);
    $lookupResponse->assertOk()->assertJsonPath('exists', false);

    // 2. Self-register from website as B2C
    $registerResponse = $this->postJson('/api/v1/store/customers/register', [
        'customer_type' => 'b2c',
        'name' => 'Rohan Mehta',
        'phone' => '9111222333',
        'email' => 'rohan@example.com',
        'city' => 'Gurugram',
    ]);

    $registerResponse->assertCreated()
        ->assertJsonPath('is_new', true)
        ->assertJsonPath('customer.name', 'Rohan Mehta');

    // 3. Lookup again, should now exist!
    $lookupAgain = $this->postJson('/api/v1/store/customers/lookup', [
        'phone' => '9111222333',
    ]);
    $lookupAgain->assertOk()
        ->assertJsonPath('exists', true)
        ->assertJsonPath('customer.name', 'Rohan Mehta');
});

it('ensures API responses never leak raw SQL queries or database internals', function (): void {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/customers');
    $response->assertOk();

    $content = $response->getContent();
    expect($content)->not->toContain('SQLSTATE')
        ->not->toContain('QueryException')
        ->not->toContain('select * from')
        ->not->toContain('PDOException');
});

it('allows authorized staff to bulk import customers and admin to bulk delete', function (): void {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $bulkData = [
        'customers' => [
            [
                'customer_type' => 'b2c',
                'name' => 'Bulk Customer One',
                'phone' => '9800000001',
                'email' => 'bulk1@example.com',
                'city' => 'Delhi',
            ],
            [
                'customer_type' => 'b2b',
                'name' => 'Bulk B2B Two',
                'business_name' => 'Metro Spares Corporation',
                'phone' => '9800000002',
                'gst_number' => '07BBBBB0000B1Z5',
                'city' => 'Noida',
            ],
        ],
    ];

    $importResponse = $this->actingAs($admin)->postJson('/api/v1/admin/customers/bulk-import', $bulkData);
    $importResponse->assertOk()
        ->assertJsonPath('imported_count', 2)
        ->assertJsonPath('skipped_count', 0);

    $this->assertDatabaseHas('customers', ['phone' => '9800000001', 'customer_type' => 'b2c']);
    $this->assertDatabaseHas('customers', ['phone' => '9800000002', 'customer_type' => 'b2b']);

    // Bulk delete both
    $ids = Customer::whereIn('phone', ['9800000001', '9800000002'])->pluck('id')->toArray();
    $deleteResponse = $this->actingAs($admin)->postJson('/api/v1/admin/customers/bulk-delete', ['ids' => $ids]);
    $deleteResponse->assertOk()->assertJsonPath('deleted_count', 2);

    $this->assertSoftDeleted('customers', ['phone' => '9800000001']);
    $this->assertSoftDeleted('customers', ['phone' => '9800000002']);
});
