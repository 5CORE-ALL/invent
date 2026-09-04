<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_youtube_campaign_attrs')) {
            Schema::create('google_youtube_campaign_attrs', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_id', 64)->unique();
                $table->string('category', 32)->nullable();
                $table->string('audience', 80)->nullable();
                $table->string('landing', 160)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('google_youtube_attr_options')) {
            Schema::create('google_youtube_attr_options', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 32);
                $table->string('label', 160);
                $table->timestamps();
                $table->unique(['kind', 'label']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_youtube_campaign_attrs');
        Schema::dropIfExists('google_youtube_attr_options');
    }
};
