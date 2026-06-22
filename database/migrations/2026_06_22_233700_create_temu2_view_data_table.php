<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors temu_view_data exactly so the Temu 2 page can have its own
     * view-data uploads without overwriting Temu 1's table (the upload handler
     * does a wipe-and-replace, so one shared table can only hold one store at
     * a time).
     */
    public function up(): void
    {
        if (Schema::hasTable('temu2_view_data')) {
            return;
        }

        Schema::create('temu2_view_data', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('goods_id')->nullable()->index();
            $table->text('goods_name')->nullable();
            $table->integer('product_impressions')->default(0);
            $table->integer('visitor_impressions')->default(0);
            $table->integer('product_clicks')->default(0);
            $table->integer('visitor_clicks')->default(0);
            $table->decimal('ctr', 8, 2)->default(0)->comment('CTR percentage');
            $table->timestamps();

            $table->index(['goods_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu2_view_data');
    }
};
