<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $permissions = [
            'overview.view',
            'orders.view',
            'products.view',
            'inventory.view',
            'customers.view',
            'reports.view',
            'settings.view',
        ];
        $staffPermissions = [
            'overview.view',
        ];
        $now = now();

        foreach ([
            ['Customer', 'customer', []],
            ['Super Administrator', 'super_admin', $permissions],
            ['Administrator', 'admin', $permissions],
            ['Catalog', 'catalog', [...$staffPermissions, 'products.view']],
            ['Orders', 'orders', [...$staffPermissions, 'orders.view']],
            ['Inventory', 'inventory', [...$staffPermissions, 'inventory.view']],
            ['Support', 'support', [...$staffPermissions, 'customers.view']],
            ['Finance', 'finance', [...$staffPermissions, 'orders.view', 'reports.view']],
        ] as [$name, $slug, $rolePermissions]) {
            DB::table('roles')->insert([
                'name' => $name,
                'slug' => $slug,
                'permissions' => json_encode($rolePermissions),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
