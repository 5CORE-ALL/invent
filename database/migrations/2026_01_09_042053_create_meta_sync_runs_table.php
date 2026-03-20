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
        Schema::create('meta_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ad_account_meta_id', 191)->nullable();
            $table->string('sync_type', 50)->index(); // full, incremental, insights_only
            $table->string('status', 50)->index(); // running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('accounts_synced')->default(0);
            $table->integer('campaigns_synced')->default(0);
            $table->integer('adsets_synced')->default(0);
            $table->integer('ads_synced')->default(0);
            $table->integer('insights_synced')->default(0);
            $table->text('error_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_sync_runs');
    }
};
