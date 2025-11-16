<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplierRatingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('supplier_ratings')) {
            Schema::create('supplier_ratings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->string('parent')->nullable(); // Parent Group
                $table->json('skus')->nullable();     // Multiple SKUs under that parent
                $table->date('evaluation_date')->nullable();

                $table->json('criteria')->nullable(); // All score, label, weight
                $table->float('final_score')->nullable(); // Final total score
                $table->timestamps();

                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('supplier_ratings');
    }
}
