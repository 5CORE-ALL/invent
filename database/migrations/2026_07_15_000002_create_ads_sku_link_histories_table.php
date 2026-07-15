<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ads_sku_link_histories')) {
            return;
        }

        Schema::create('ads_sku_link_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('parent')->nullable()->index();
            $table->string('action', 16);
            $table->string('linked_sku')->nullable();
            $table->text('changes')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->index(['sku', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_sku_link_histories');
    }
};
