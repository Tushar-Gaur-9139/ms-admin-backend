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
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('customer_code', 30)->nullable()->unique()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('date_of_birth')->nullable()->after('alternate_phone');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('legal_name')->nullable()->after('business_name');
            $table->string('trade_name')->nullable()->after('legal_name');
            $table->string('registration_number', 50)->nullable()->after('gst_number');
            $table->string('industry', 100)->nullable()->after('registration_number');
            $table->string('company_size', 50)->nullable()->after('industry');
            $table->string('company_type', 50)->nullable()->after('company_size');
            $table->string('website')->nullable()->after('company_type');
            $table->string('annual_revenue', 50)->nullable()->after('website');
            $table->string('job_title', 100)->nullable()->after('annual_revenue');
            $table->string('department', 100)->nullable()->after('job_title');
            $table->string('country', 60)->default('India')->after('address_line2');
            $table->string('segment', 50)->nullable()->after('total_spent');
            $table->string('stage', 50)->nullable()->after('segment');
            $table->string('primary_manager', 100)->nullable()->after('stage');
            $table->string('customer_priority', 30)->nullable()->after('primary_manager');
            $table->string('billing_email')->nullable()->after('customer_priority');
            $table->string('payment_terms', 50)->nullable()->after('billing_email');
            $table->string('preferred_currency', 10)->default('INR')->after('payment_terms');
            $table->string('preferred_language', 40)->default('English')->after('preferred_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_code',
                'first_name',
                'last_name',
                'date_of_birth',
                'gender',
                'legal_name',
                'trade_name',
                'registration_number',
                'industry',
                'company_size',
                'company_type',
                'website',
                'annual_revenue',
                'job_title',
                'department',
                'country',
                'segment',
                'stage',
                'primary_manager',
                'customer_priority',
                'billing_email',
                'payment_terms',
                'preferred_currency',
                'preferred_language',
            ]);
        });
    }
};
