<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prior-week mirror of temu_view_data_l7 (days 8–14 back from "today").
 *
 * Same column shape as temu_view_data and temu_view_data_l7 so the upload
 * handler + sample-file generator can reuse one code path; only the target
 * table differs. Kept in its own table — not stuffed into temu_view_data_l7
 * with a period flag — because both uploads are replace-all and would
 * otherwise wipe each other on every upload.
 *
 * Drives week-over-week comparisons on /temu-decrease (L7 vs L7-to-L14).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu_view_data_l7_to_l14')) {
            return;
        }

        Schema::create('temu_view_data_l7_to_l14', function (Blueprint $table) {
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
        Schema::dropIfExists('temu_view_data_l7_to_l14');
    }
};
