<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inv_days_clearances')) {
            Schema::create('inv_days_clearances', function (Blueprint $table) {
                $table->id();
                $table->string('sku_key', 191)->unique();
                $table->string('sku', 191);
                $table->string('value', 3)->default('NO');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inv_days_clearance_logs')) {
            Schema::create('inv_days_clearance_logs', function (Blueprint $table) {
                $table->id();
                $table->string('sku_key', 191)->index();
                $table->string('sku', 191);
                $table->string('from_value', 3);
                $table->string('to_value', 3);
                $table->string('changed_by', 191)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_days_clearance_logs');
        Schema::dropIfExists('inv_days_clearances');
    }
};
