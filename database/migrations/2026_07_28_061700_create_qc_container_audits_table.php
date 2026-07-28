<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qc_container_audits')) {
            return;
        }

        Schema::create('qc_container_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('arrived_container_id')->nullable()->index();
            $table->string('our_sku')->nullable()->index();
            $table->string('supplier_name')->nullable();
            $table->json('items')->nullable();
            $table->unsignedBigInteger('audited_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['arrived_container_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_container_audits');
    }
};
