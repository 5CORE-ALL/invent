<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_yesterday_views')) {
            return;
        }

        Schema::create('channel_yesterday_views', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 64);
            $table->date('snapshot_date');
            $table->unsignedInteger('views')->default(0);
            $table->string('source', 40)->nullable();
            $table->timestamps();
            $table->unique(['channel', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_yesterday_views');
    }
};
