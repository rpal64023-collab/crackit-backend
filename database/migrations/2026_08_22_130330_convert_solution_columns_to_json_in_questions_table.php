<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add new JSON columns alongside the old text ones
        Schema::table('questions', function (Blueprint $table) {
            $table->json('starter_code_json')->nullable();
            $table->json('brute_force_solution_json')->nullable();
            $table->json('optimal_solution_json')->nullable();
            $table->json('test_harness_json')->nullable();
        });

        // Step 2: migrate existing Java-only data into the new JSON shape,
        // so old questions keep working with the new multi-language format
        $questions = DB::table('questions')->get();
        foreach ($questions as $q) {
            DB::table('questions')->where('id', $q->id)->update([
                'starter_code_json' => $q->starter_code ? json_encode(['java' => $q->starter_code]) : null,
                'brute_force_solution_json' => $q->brute_force_solution ? json_encode(['java' => $q->brute_force_solution]) : null,
                'optimal_solution_json' => $q->optimal_solution ? json_encode(['java' => $q->optimal_solution]) : null,
                'test_harness_json' => $q->test_harness ? json_encode(['java' => $q->test_harness]) : null,
            ]);
        }

        // Step 3: drop the old text columns, rename the new ones into their place
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['starter_code', 'brute_force_solution', 'optimal_solution', 'test_harness']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('starter_code_json', 'starter_code');
            $table->renameColumn('brute_force_solution_json', 'brute_force_solution');
            $table->renameColumn('optimal_solution_json', 'optimal_solution');
            $table->renameColumn('test_harness_json', 'test_harness');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('starter_code_old')->nullable();
            $table->text('brute_force_solution_old')->nullable();
            $table->text('optimal_solution_old')->nullable();
            $table->text('test_harness_old')->nullable();
        });

        $questions = DB::table('questions')->get();
        foreach ($questions as $q) {
            $starter = $q->starter_code ? json_decode($q->starter_code, true) : null;
            $brute = $q->brute_force_solution ? json_decode($q->brute_force_solution, true) : null;
            $optimal = $q->optimal_solution ? json_decode($q->optimal_solution, true) : null;
            $harness = $q->test_harness ? json_decode($q->test_harness, true) : null;

            DB::table('questions')->where('id', $q->id)->update([
                'starter_code_old' => $starter['java'] ?? null,
                'brute_force_solution_old' => $brute['java'] ?? null,
                'optimal_solution_old' => $optimal['java'] ?? null,
                'test_harness_old' => $harness['java'] ?? null,
            ]);
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['starter_code', 'brute_force_solution', 'optimal_solution', 'test_harness']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('starter_code_old', 'starter_code');
            $table->renameColumn('brute_force_solution_old', 'brute_force_solution');
            $table->renameColumn('optimal_solution_old', 'optimal_solution');
            $table->renameColumn('test_harness_old', 'test_harness');
        });
    }
};