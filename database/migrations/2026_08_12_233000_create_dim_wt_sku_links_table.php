<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dim_wt_sku_links')) {
            return;
        }

        Schema::create('dim_wt_sku_links', function (Blueprint $table) {
            $table->id();
            $table->string('parent')->nullable()->index();
            $table->string('group_key', 64)->index();
            $table->string('sku');
            $table->string('sku_norm')->unique();
            $table->string('fingerprint', 64)->index();
            $table->decimal('wt_act', 12, 4)->nullable();
            $table->decimal('l', 12, 4)->nullable();
            $table->decimal('w', 12, 4)->nullable();
            $table->decimal('h', 12, 4)->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['parent', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_wt_sku_links');
    }
};
