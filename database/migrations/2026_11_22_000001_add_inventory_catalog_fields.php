<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appliance_types', function (Blueprint $table): void {
            $table->string('image_url')->nullable();
        });

        Schema::table('appliance_models', function (Blueprint $table): void {
            $table->string('image_url')->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->index(['brand_id', 'appliance_type_id', 'is_active'], 'appliance_models_order_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('appliance_models', function (Blueprint $table): void {
            $table->dropIndex('appliance_models_order_lookup');
            $table->dropColumn(['image_url', 'serial_number', 'purchase_price', 'sale_price']);
        });

        Schema::table('appliance_types', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }
};
