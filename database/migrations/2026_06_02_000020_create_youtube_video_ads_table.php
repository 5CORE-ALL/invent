<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores rows imported from user-uploaded YouTube ad sheets (CSV / Excel /
 * TSV) on the "YouTube Video Ads" page. Independent of the other ad sheets —
 * its own table, its own batches. Each upload becomes one batch
 * (`import_batch_id`), and every sheet row becomes one DB row with the raw
 * column → value pairs preserved verbatim in `row_data`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_video_ads_sheet', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch_id', 64)->index();
            $table->string('source_filename')->nullable();
            $table->unsignedInteger('row_index')->nullable();
            $table->json('row_data');
            $table->string('ad_type', 32)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_video_ads_sheet');
    }
};
