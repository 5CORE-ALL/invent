<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('to_order_analysis')) {
            Schema::create('to_order_analysis', function (Blueprint $table) {
                $table->id();
                $table->string('parent')->nullable();
                $table->string('sku')->nullable()->index();
                $table->integer('approved_qty')->nullable();
                $table->date('date_apprvl')->nullable();
                $table->text('rfq_form_link')->nullable();
                $table->text('mail_link')->nullable();
                $table->text('rfq_report_link')->nullable();
                $table->string('stage')->nullable();
                $table->date('advance_date')->nullable();
                $table->integer('order_qty')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('to_order_analysis');
    }
};
