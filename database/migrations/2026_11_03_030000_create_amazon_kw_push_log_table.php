<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every keyword the FBM targeting page pushed to an SP keyword campaign, per push run, so a run can be undone
     * (archived on Amazon) later.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_kw_push_log')) {
            return;
        }

        Schema::create('amazon_kw_push_log', function (Blueprint $table) {
            $table->id();
            $table->string('run_token', 40)->index();
            $table->string('campaign_id', 64)->index();
            $table->string('campaign_name', 255)->nullable();
            $table->string('keyword_text', 512);
            $table->string('keyword_key', 191);
            $table->string('match_type', 16);
            $table->timestamp('pushed_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->string('pushed_by', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_push_log');
    }
};
