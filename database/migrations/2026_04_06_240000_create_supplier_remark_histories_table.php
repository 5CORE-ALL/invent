<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_remark_histories')) {
            return;
        }

        Schema::create('supplier_remark_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->text('body');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_remark_histories');
    }
};
