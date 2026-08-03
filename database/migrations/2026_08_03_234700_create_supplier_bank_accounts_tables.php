<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bank_accounts', function (Blueprint $table) {
            $table->id();
            // suppliers.id is signed INT(11)
            $table->integer('supplier_id')->index();
            $table->string('supplier_name', 30)->nullable();
            $table->string('nick_name', 30)->nullable();
            $table->string('company_name', 30)->nullable();
            $table->string('swift', 30)->nullable();
            $table->string('address', 30)->nullable();
            $table->string('city', 30)->nullable();
            $table->string('province', 30)->nullable();
            $table->string('country', 30)->nullable();
            $table->string('account_number', 30)->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
        });

        Schema::create('supplier_bank_account_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('supplier_id')->index();
            $table->unsignedBigInteger('supplier_bank_account_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 100)->nullable();
            $table->string('action', 20); // created | updated | deleted
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bank_account_histories');
        Schema::dropIfExists('supplier_bank_accounts');
    }
};
