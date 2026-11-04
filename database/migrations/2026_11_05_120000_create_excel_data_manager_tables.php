<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('excel_imports')) {
            Schema::create('excel_imports', function (Blueprint $table) {
                $table->id();
                $table->string('original_filename');
                $table->string('stored_filename');
                $table->unsignedInteger('total_worksheets')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('excel_worksheets')) {
            Schema::create('excel_worksheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('excel_import_id')->constrained('excel_imports')->cascadeOnDelete();
                $table->string('worksheet_name', 191);
                $table->unsignedInteger('worksheet_index')->default(0);
                $table->unsignedInteger('total_rows')->default(0);
                $table->json('headers');
                $table->timestamps();

                $table->index(['excel_import_id', 'worksheet_index']);
            });
        }

        if (! Schema::hasTable('excel_worksheet_rows')) {
            Schema::create('excel_worksheet_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('excel_worksheet_id')->constrained('excel_worksheets')->cascadeOnDelete();
                $table->unsignedInteger('row_index');
                $table->json('data');
                $table->text('search_text')->nullable();
                $table->timestamps();

                $table->index(['excel_worksheet_id', 'row_index']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_worksheet_rows');
        Schema::dropIfExists('excel_worksheets');
        Schema::dropIfExists('excel_imports');
    }
};
