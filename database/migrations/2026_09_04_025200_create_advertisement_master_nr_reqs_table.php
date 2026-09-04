<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('advertisement_master_nr_reqs')) {
            return;
        }

        Schema::create('advertisement_master_nr_reqs', function (Blueprint $table) {
            $table->id();
            $table->string('channel_key', 191)->unique();
            $table->string('nr_req', 8)->default('REQ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_master_nr_reqs');
    }
};
