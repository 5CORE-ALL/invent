<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advance_payments') || Schema::hasColumn('advance_payments', 'is_archived')) {
            return;
        }

        Schema::table('advance_payments', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('advance_payments') || ! Schema::hasColumn('advance_payments', 'is_archived')) {
            return;
        }

        Schema::table('advance_payments', function (Blueprint $table) {
            $table->dropColumn(['is_archived', 'archived_at']);
        });
    }
};
