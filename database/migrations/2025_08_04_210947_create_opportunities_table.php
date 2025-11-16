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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->integer('channel_id')->nullable();
            $table->string('regn_link')->nullable();
            $table->string('status')->nullable();
            $table->string('aa_stage')->nullable();
            $table->string('priority')->nullable();
            $table->string('item_sold')->nullable();
            $table->string('link_as_customer')->nullable();
            $table->string('last_year_traffic')->nullable();
            $table->string('current_traffic')->nullable();
            $table->string('us_presence')->nullable();
            $table->string('us_visitor_count')->nullable();
            $table->string('comm_chgs', 10, 2)->nullable();
            $table->string('current_status')->nullable();
            $table->string('final')->nullable();
            $table->date('date')->nullable();
            $table->string('email')->nullable();
            $table->text('remarks')->nullable();
            $table->string('sign_up_page_link')->nullable();
            $table->string('followup_dt')->nullable();
            $table->text('masum_comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
