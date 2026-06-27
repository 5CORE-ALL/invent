<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Last-7-day mirror of temu_view_data.
 *
 * Same column shape as the L30 table so the upload handler, sample-file
 * generator, and the /temu-decrease aggregation can reuse one code path
 * (only the target class differs). Kept as a separate table — not a
 * `period` column on temu_view_data — because both uploads are
 * replace-all (delete()-then-insert) and would otherwise wipe each
 * other on every upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu_view_data_l7')) {
            return;
        }

        Schema::create('temu_view_data_l7', function (Blueprint $table) {
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
        Schema::dropIfExists('temu_view_data_l7');
    }
};
