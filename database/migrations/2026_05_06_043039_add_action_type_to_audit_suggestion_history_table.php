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
        Schema::table('audit_suggestion_history', function (Blueprint $table) {
            $table->string('action_type')->nullable()->after('audit_suggestion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_suggestion_history', function (Blueprint $table) {
            $table->dropColumn('action_type');
        });
    }
};
