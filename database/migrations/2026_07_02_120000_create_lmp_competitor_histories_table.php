<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lmp_competitor_histories')) {
            return;
        }

        Schema::create('lmp_competitor_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('parent')->nullable()->index();
            $table->string('action', 16);
            $table->string('item_id')->nullable();
            $table->unsignedBigInteger('competitor_id')->nullable();
            $table->string('product_title')->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->text('changes')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->index(['sku', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lmp_competitor_histories');
    }
};
