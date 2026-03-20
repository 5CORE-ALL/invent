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
        Schema::create('new_marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name')->nullable();
            $table->string('link_customer')->nullable();
            $table->string('type')->nullable();
            $table->string('priority')->nullable();
            $table->string('category_allowed')->nullable();
            $table->string('link_seller')->nullable(); // e.g., B2B/B2C
            $table->integer('last_year_traffic')->nullable();
            $table->integer('current_traffic')->nullable();
            $table->decimal('us_presence', 5, 2)->nullable();
            $table->integer('us_visitors')->nullable();
            $table->string('commission')->nullable();
            $table->string('applied_through')->nullable();
            $table->string('status')->default('Not Started');
            $table->string('applied_id')->nullable();
            $table->string('password')->nullable();
            $table->text('remarks')->nullable();
            $table->date('apply_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('new_marketplaces');
    }
};
