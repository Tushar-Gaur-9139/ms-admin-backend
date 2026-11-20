<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brand_appliances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('appliance_type_id')->constrained('appliance_types')->cascadeOnDelete();
            $table->unique(['brand_id', 'appliance_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_appliances');
    }
};

