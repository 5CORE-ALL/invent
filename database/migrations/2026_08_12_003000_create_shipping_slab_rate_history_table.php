<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_slab_rate_history')) {
            return;
        }

        Schema::create('shipping_slab_rate_history', function (Blueprint $table) {
            $table->id();
            $table->string('slab_key', 64)->index();
            $table->string('slab_label', 191)->nullable();
            $table->string('field', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedInteger('skus_updated')->default(0);
            $table->string('scope', 32)->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->index(['slab_key', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_slab_rate_history');
    }
};
