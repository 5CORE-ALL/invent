<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inv_verify_container_audits')) {
            return;
        }

        Schema::create('inv_verify_container_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('arrived_container_id')->unique();
            $table->string('our_sku')->nullable()->index();
            $table->string('supplier_name')->nullable();
            $table->json('action_history')->nullable();
            $table->timestamps();

            $table->foreign('arrived_container_id')
                ->references('id')
                ->on('arrived_containers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_verify_container_audits');
    }
};
