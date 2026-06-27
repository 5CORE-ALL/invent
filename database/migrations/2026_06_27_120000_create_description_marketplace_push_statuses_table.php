<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('description_marketplace_push_statuses')) {
            return;
        }

        Schema::create('description_marketplace_push_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('marketplace', 50);
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'marketplace'], 'desc_push_status_sku_marketplace_unique');
            $table->index(['marketplace', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('description_marketplace_push_statuses');
    }
};
