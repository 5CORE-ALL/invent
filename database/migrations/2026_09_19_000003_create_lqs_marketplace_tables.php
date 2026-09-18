<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lqs_marketplace_scores')) {
            Schema::create('lqs_marketplace_scores', function (Blueprint $table) {
                $table->id();
                $table->string('marketplace', 50);
                $table->string('sku');
                $table->decimal('lqs', 4, 1)->nullable();
                $table->decimal('rating', 3, 1)->nullable();
                $table->unsignedInteger('reviews')->nullable();
                $table->unsignedInteger('l30')->nullable();
                $table->unsignedInteger('sessions')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->string('listing_id')->nullable();
                $table->timestamps();
                $table->unique(['marketplace', 'sku']);
                $table->index('marketplace');
            });
        }

        if (! Schema::hasTable('lqs_marketplace_actions')) {
            Schema::create('lqs_marketplace_actions', function (Blueprint $table) {
                $table->id();
                $table->string('marketplace', 50);
                $table->string('sku');
                $table->string('action', 100);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
                $table->index(['marketplace', 'sku']);
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('lqs_marketplace_history')) {
            Schema::create('lqs_marketplace_history', function (Blueprint $table) {
                $table->id();
                $table->string('marketplace', 50);
                $table->date('date');
                $table->decimal('total_inv', 12, 2)->default(0);
                $table->decimal('total_l30', 12, 2)->default(0);
                $table->decimal('total_sessions', 12, 2)->default(0);
                $table->decimal('avg_dil', 10, 2)->default(0);
                $table->decimal('avg_lqs', 10, 2)->default(0);
                $table->decimal('avg_rating', 10, 2)->default(0);
                $table->unsignedInteger('lqs_below_9_count')->default(0);
                $table->timestamps();
                $table->unique(['marketplace', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lqs_marketplace_history');
        Schema::dropIfExists('lqs_marketplace_actions');
        Schema::dropIfExists('lqs_marketplace_scores');
    }
};
