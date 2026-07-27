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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->enum('type', ['dsa', 'hr', 'system_design', 'core_subject']);
            $table->string('topic')->nullable(); // e.g. 'array', 'linked_list', 'os', 'dbms'
            $table->enum('difficulty', ['easy', 'medium', 'hard']);
            $table->string('tags')->nullable();
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};