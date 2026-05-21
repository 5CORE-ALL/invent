<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores rows imported from arbitrary user-uploaded sheets (CSV / Excel / TSV
 * / etc.) on the "Facebook All Ads Sheet" page. Each upload becomes one batch
 * (`import_batch_id`), and every row in the sheet becomes one DB row with the
 * raw column → value pairs preserved verbatim in `row_data` so the page can
 * render whatever columns the user uploaded without a schema migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_all_ads_sheet', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch_id', 64)->index();
            $table->string('source_filename')->nullable();
            $table->unsignedInteger('row_index')->nullable();
            $table->json('row_data');
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_all_ads_sheet');
    }
};
