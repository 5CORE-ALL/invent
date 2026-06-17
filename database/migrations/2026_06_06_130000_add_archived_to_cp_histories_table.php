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
        if (! Schema::hasTable('cp_histories') || Schema::hasColumn('cp_histories', 'archived')) {
            return;
        }

        Schema::table('cp_histories', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('approved_at');
            $table->timestamp('archived_at')->nullable()->after('archived');
            $table->index('archived');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('cp_histories')) {
            return;
        }

        Schema::table('cp_histories', function (Blueprint $table) {
            $table->dropColumn(['archived', 'archived_at']);
        });
    }
};
