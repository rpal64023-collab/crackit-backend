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
        Schema::table('questions', function (Blueprint $table) {
            $table->text('starter_code')->nullable()->after('content');
            $table->text('brute_force_solution')->nullable()->after('starter_code');
            $table->text('optimal_solution')->nullable()->after('brute_force_solution');
            $table->string('status')->default('approved')->after('optimal_solution'); // 'draft' | 'approved'
            $table->string('company')->nullable()->after('status'); // future-ready, empty for now
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['starter_code', 'brute_force_solution', 'optimal_solution', 'status', 'company']);
        });
    }
};