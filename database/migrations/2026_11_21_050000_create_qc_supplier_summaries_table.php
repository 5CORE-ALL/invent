<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_supplier_summaries')) {
            Schema::create('qc_supplier_summaries', function (Blueprint $table) {
                $table->id();
                $table->string('supplier_key', 191)->unique();
                $table->string('issues_hash', 64); // hash of the issues the summary was written from
                $table->longText('summary'); // JSON: [{group, skus, summary}]
                $table->string('model', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_supplier_summaries');
    }
};
