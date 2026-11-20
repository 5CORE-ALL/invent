<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_supplier_statuses')) {
            Schema::create('qc_supplier_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('supplier_key', 191)->unique(); // upper-cased, trimmed supplier name
                $table->string('supplier_name', 191);
                $table->boolean('is_active')->default(true);
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_supplier_statuses');
    }
};
