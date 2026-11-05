<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_type', 10)->default('b2c'); // b2c, b2b
            $table->string('classification', 15)->default('new'); // new, existing
            $table->string('name');
            $table->string('phone')->index();
            $table->string('email')->nullable()->index();
            $table->string('alternate_phone')->nullable();
            $table->string('business_name')->nullable()->index();
            $table->string('gst_number', 20)->nullable()->index();
            $table->string('pan_number', 15)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 12)->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('source', 20)->default('admin'); // admin, website, import
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
