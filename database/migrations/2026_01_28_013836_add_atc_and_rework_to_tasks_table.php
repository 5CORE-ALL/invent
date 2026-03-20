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
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('atc')->nullable()->after('etc_minutes')->comment('Actual Time to Complete in minutes');
            $table->text('rework_reason')->nullable()->after('atc')->comment('Reason for rework');
            $table->dateTime('completed_at')->nullable()->after('rework_reason')->comment('Task completion timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['atc', 'rework_reason', 'completed_at']);
        });
    }
};
