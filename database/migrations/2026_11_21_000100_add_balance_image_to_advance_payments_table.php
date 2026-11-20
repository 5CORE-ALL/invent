<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advance_payments') || Schema::hasColumn('advance_payments', 'balance_image')) {
            return;
        }

        Schema::table('advance_payments', function (Blueprint $table) {
            $table->string('balance_image')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('advance_payments') || ! Schema::hasColumn('advance_payments', 'balance_image')) {
            return;
        }

        Schema::table('advance_payments', function (Blueprint $table) {
            $table->dropColumn('balance_image');
        });
    }
};
