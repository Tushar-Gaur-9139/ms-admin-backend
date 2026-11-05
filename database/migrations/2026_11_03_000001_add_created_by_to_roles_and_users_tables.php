<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
