<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('container_quote_received')) {
            return;
        }

        Schema::create('container_quote_received', function (Blueprint $table) {
            $table->id();
            $table->string('container_number')->index();
            $table->string('forwarder')->nullable();
            $table->decimal('pickup_charges', 14, 2)->nullable();
            $table->decimal('freight', 14, 2)->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_quote_received');
    }
};