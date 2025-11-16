<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForecastAnalysisTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('forecast_analysis')) {
            Schema::create('forecast_analysis', function (Blueprint $table) {
                $table->id();
                $table->string('parent')->nullable();
                $table->string('sku')->nullable();
                $table->integer('s_msl')->nullable();
                $table->integer('order_given')->nullable();
                $table->integer('transit')->nullable();
                $table->integer('approved_qty')->nullable();
                $table->string('nr',20)->nullable();
                $table->string('req',20)->nullable();
                $table->string('hide',20)->default(false);
                $table->text('notes')->nullable();
                $table->text('clink')->nullable();
                $table->text('olink')->nullable();
                $table->text('rfq_form_link')->nullable();
                $table->text('rfq_report')->nullable();
                $table->date('date_apprvl')->nullable();
                $table->timestamps(); // created_at and updated_at
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('forecast_analysis');
    }
}

