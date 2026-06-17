<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QC Improvement Req — per product_master row; QC Upgrade page.
     */
    public function up(): void
    {
        if (Schema::hasTable('qc_improvement_req_before_item_pkg')) {
            return;
        }

        Schema::create('qc_improvement_req_before_item_pkg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_master_id')->unique()->constrained('product_master')->cascadeOnDelete();
            $table->text('qc_improvement_req')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_improvement_req_before_item_pkg');
    }
};
