<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comparison_sku_links')) {
            return;
        }

        Schema::create('comparison_sku_links', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('linked_sku');
            $table->string('sku_norm');
            $table->string('linked_sku_norm');
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['sku_norm', 'linked_sku_norm']);
            $table->index('sku_norm');
            $table->index('linked_sku_norm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_sku_links');
    }
};
