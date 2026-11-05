<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('page_doc_links')) {
            return;
        }

        Schema::create('page_doc_links', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 100);
            $table->string('doc_type', 20); // sop | checklist
            $table->string('url', 2048);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['page_key', 'doc_type'], 'pdl_page_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_doc_links');
    }
};
