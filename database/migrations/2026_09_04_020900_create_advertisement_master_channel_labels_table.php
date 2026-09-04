<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Display-name overrides for /advertisement-master Group + Channel columns.
     * channel_key is the original source name (used by links, snapshots, history).
     */
    public function up(): void
    {
        if (Schema::hasTable('advertisement_master_channel_labels')) {
            return;
        }

        Schema::create('advertisement_master_channel_labels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_key', 191)->unique();
            $table->string('group_name', 80);
            $table->string('channel_name', 191);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_master_channel_labels');
    }
};
