<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renames products and prevents deleting products mapped to companies', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

    $product = $this->postJson('/api/v1/admin/inventory/appliance-types', ['name' => 'Room AC'])
        ->assertCreated()
        ->json('appliance_type');
    $url = '/api/v1/admin/inventory/appliance-types/'.$product['id'];

    $this->putJson($url, ['name' => 'Air Conditioner'])
        ->assertOk()
        ->assertJsonPath('appliance_type.name', 'Air Conditioner')
        ->assertJsonPath('appliance_type.slug', 'room-ac');

    $brandId = $this->postJson('/api/v1/admin/brands', [
        'name' => 'Catalog Company',
        'short_code' => 'CAT',
        'appliance_type_ids' => [$product['id']],
    ])->assertCreated()->json('brand.id');

    $this->deleteJson($url)->assertUnprocessable();
    $this->assertDatabaseHas('brand_appliances', ['brand_id' => $brandId, 'appliance_type_id' => $product['id']]);

    $this->putJson('/api/v1/admin/brands/'.$brandId, ['appliance_type_ids' => []])->assertOk();
    $this->deleteJson($url)->assertOk();
    $this->assertDatabaseMissing('appliance_types', ['id' => $product['id']]);
});

it('accepts image uploads on company creation and multipart updates', function (): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');

    $created = $this->post('/api/v1/admin/brands', [
        'name' => 'Uploaded Logo Company',
        'short_code' => 'ULC',
        'logo' => UploadedFile::fake()->createWithContent('logo.png', $png),
    ], ['Accept' => 'application/json'])->assertCreated();

    $id = $created->json('brand.id');
    $originalUrl = $created->json('brand.logo_url');
    Storage::disk('public')->assertExists('brand-logos/'.basename($originalUrl));
    expect($this->get($originalUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->streamedContent())->toBe($png);

    $replaced = $this->post("/api/v1/admin/brands/{$id}", [
        '_method' => 'PUT',
        'logo' => UploadedFile::fake()->createWithContent('replacement.png', $png),
        'appliance_type_ids' => '',
    ], ['Accept' => 'application/json'])->assertOk()->json('brand.logo_url');

    expect($replaced)->not->toBe($originalUrl);
    $this->assertDatabaseHas('brands', ['id' => $id, 'logo_url' => $replaced]);
    Storage::disk('public')->assertExists('brand-logos/'.basename($replaced));
});

it('rejects invalid company logo files and keeps URL-only saves working', function (): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    $brand = ['name' => 'URL Logo Company', 'short_code' => 'URL'];

    $this->post('/api/v1/admin/brands', $brand + [
        'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('logo');

    expect(Storage::disk('public')->allFiles())->toBe([]);
    $url = 'https://example.test/logo.png';
    $this->postJson('/api/v1/admin/brands', $brand + ['logo_url' => $url])
        ->assertCreated()->assertJsonPath('brand.logo_url', $url);
});

it('saves inventory classifications and rejects models from another company', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

    $typeId = $this->postJson('/api/v1/admin/inventory/appliance-types', ['name' => 'Refrigerator'])
        ->assertCreated()
        ->json('appliance_type.id');
    $categoryId = $this->postJson('/api/v1/admin/inventory/part-categories', [
        'name' => 'Compressors',
        'appliance_type_id' => $typeId,
    ])->assertCreated()->json('part_category.id');

    $brandId = $this->postJson('/api/v1/admin/brands', [
        'name' => 'Test Appliances',
        'short_code' => 'TEST',
        'appliance_type_ids' => [$typeId],
    ])->assertCreated()->json('brand.id');
    $otherBrandId = $this->postJson('/api/v1/admin/brands', [
        'name' => 'Other Appliances',
        'short_code' => 'OTHER',
    ])->assertCreated()->json('brand.id');

    $model = ['model_number' => 'RF100', 'appliance_type_id' => $typeId];
    $modelId = $this->postJson("/api/v1/admin/brands/{$brandId}/models", $model)
        ->assertCreated()->json('model.id');
    $otherModelId = $this->postJson("/api/v1/admin/brands/{$otherBrandId}/models", $model)
        ->assertCreated()->json('model.id');

    $part = [
        'name' => 'Compressor',
        'brand_id' => $brandId,
        'appliance_type_id' => $typeId,
        'part_category_id' => $categoryId,
        'b2c_price' => 3000,
        'b2b_price' => 2500,
    ];

    $this->postJson('/api/v1/admin/inventory', $part + ['compatible_model_ids' => [$otherModelId]])
        ->assertUnprocessable()->assertJsonValidationErrors('compatible_model_ids.0');
    $this->postJson('/api/v1/admin/inventory', $part + ['compatible_model_ids' => [$modelId]])
        ->assertCreated();

    $this->getJson('/api/v1/admin/inventory')
        ->assertOk()
        ->assertJsonPath('appliance_types.0.id', $typeId)
        ->assertJsonPath('part_categories.0.id', $categoryId)
        ->assertJsonPath('parts.0.compatible_models.0.model_number', 'RF100');
});
