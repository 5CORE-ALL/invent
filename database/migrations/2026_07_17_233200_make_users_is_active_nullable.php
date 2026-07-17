<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_active')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->nullable()->default(true)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_active')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->nullable(false)->change();
        });
    }
};
