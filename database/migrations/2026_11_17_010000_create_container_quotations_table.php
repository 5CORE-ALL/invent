<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('container_quotations')) {
            return;
        }

        Schema::create('container_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('container_number')->unique();
            $table->string('supplier')->nullable();
            $table->string('container_size', 50)->nullable();
            $table->text('pickup_address')->nullable();
            $table->string('loading_port')->nullable();
            $table->string('discharge_port')->nullable();
            $table->string('gross_weight', 100)->nullable();
            $table->decimal('freight_all_inclusive', 14, 2)->nullable();
            $table->string('forwarder')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_quotations');
    }
};