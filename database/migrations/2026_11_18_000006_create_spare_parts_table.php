<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique()->comment('Auto-generated: ME-{BRAND_SHORT}-{seq}');
            $table->string('name', 200);
            $table->string('slug', 250)->unique();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('appliance_type_id')->constrained('appliance_types')->restrictOnDelete();
            $table->foreignId('part_category_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->text('description')->nullable();

            // Pricing (dual-tier B2C / B2B)
            $table->decimal('purchase_price', 10, 2)->default(0)->comment('Cost price from supplier');
            $table->decimal('b2c_price', 10, 2)->comment('Retail price for end customers');
            $table->decimal('b2b_price', 10, 2)->comment('Wholesale price for trade customers');
            $table->decimal('mrp_price', 10, 2)->nullable()->comment('MRP / strike-through price on storefront');

            // Stock
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('min_stock_alert')->default(5)->comment('Alert threshold for low-stock warning');
            $table->string('rack_location', 50)->nullable()->comment('Physical rack/bin identifier, e.g. R2-B4');

            // Quality control
            $table->enum('qc_status', ['original_oem', 'tested_qc_pass', 'universal_fit'])->default('original_oem');

            // Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Media & extras
            $table->string('image_url', 500)->nullable();
            $table->json('specs_json')->nullable()->comment('Arbitrary key-value specs for storefront display');

            $table->timestamps();

            // Indexes for filtering
            $table->index(['brand_id', 'appliance_type_id']);
            $table->index('is_active');
            $table->index('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_parts');
    }
};

