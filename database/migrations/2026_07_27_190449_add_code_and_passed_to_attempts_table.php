<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('attempts', 'code')) {
                $table->longText('code')->nullable()->after('question_id');
            }
            if (!Schema::hasColumn('attempts', 'passed')) {
                $table->boolean('passed')->nullable()->after('code');
            }
            if (Schema::hasColumn('attempts', 'answer_text')) {
                $table->text('answer_text')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            if (Schema::hasColumn('attempts', 'code')) {
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('attempts', 'passed')) {
                $table->dropColumn('passed');
            }
        });
    }
};