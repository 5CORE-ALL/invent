<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outgoing_order_meta')) {
            return;
        }

        Schema::create('outgoing_order_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('order_id', 128)->nullable();
            $table->timestamps();

            $table->unique('inventory_id');
            if (Schema::hasTable('inventories')) {
                $table->foreign('inventory_id')
                    ->references('id')
                    ->on('inventories')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_order_meta');
    }
};
