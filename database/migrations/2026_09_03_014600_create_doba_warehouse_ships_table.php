<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doba_warehouse_ships')) {
            return;
        }

        Schema::create('doba_warehouse_ships', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 191);
            $table->boolean('shipped')->default(true);
            $table->timestamp('shipped_at')->nullable();
            $table->unsignedBigInteger('shipped_by')->nullable();
            $table->timestamps();

            $table->unique('order_no', 'doba_warehouse_ships_order_no_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doba_warehouse_ships');
    }
};
