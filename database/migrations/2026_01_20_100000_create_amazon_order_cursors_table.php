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
        Schema::create('amazon_order_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('cursor_key')->unique()->comment('Unique key based on date range');
            $table->text('next_token')->nullable()->comment('Amazon NextToken for pagination');
            $table->enum('status', ['running', 'failed', 'completed'])->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_page_at')->nullable()->comment('Last successful page fetch');
            $table->timestamp('completed_at')->nullable();
            $table->integer('orders_fetched')->default(0)->comment('Total orders fetched in this cursor');
            $table->integer('pages_fetched')->default(0)->comment('Total pages fetched');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_order_cursors');
    }
};
