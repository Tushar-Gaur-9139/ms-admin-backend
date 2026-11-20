<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('spare_part_model_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->foreignId('appliance_model_id')->constrained('appliance_models')->cascadeOnDelete();
            $table->string('serial_notes', 300)->nullable()->comment('Any serial-range restrictions for this compatibility');
            $table->unique(['spare_part_id', 'appliance_model_id'], 'part_model_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_model_compatibilities');
    }
};

