<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->text('answer_text')->nullable()->change();
            $table->longText('code')->nullable()->after('question_id');
            $table->boolean('passed')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['code', 'passed']);
            $table->text('answer_text')->nullable(false)->change();
        });
    }
};