<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('type')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name')->nullable();
                $table->string('company')->nullable();
                $table->text('sku')->nullable(); // can be comma-separated SKUs
                $table->string('parent')->nullable();
                $table->string('country_code')->nullable();
                $table->string('phone')->nullable();
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('email')->nullable();
                $table->string('website')->nullable();
                $table->string('whatsapp')->nullable();
                $table->string('wechat')->nullable();
                $table->string('alibaba')->nullable();
                $table->string('others')->nullable();
                $table->text('address')->nullable();
                $table->text('bank_details')->nullable();
                $table->timestamps();
                $table->softDeletes(); // for deleted_at
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
