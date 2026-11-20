<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appliance_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('appliance_type_id')->constrained('appliance_types')->cascadeOnDelete();
            $table->string('model_number', 100);
            $table->string('model_name', 200)->nullable();
            $table->string('series_year', 20)->nullable()->comment('e.g. 2024, 2022-2024');
            $table->string('serial_prefix', 30)->nullable()->comment('Serial number prefix for this model');
            $table->string('serial_range_start', 50)->nullable();
            $table->string('serial_range_end', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'model_number'], 'brand_model_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appliance_models');
    }
};

