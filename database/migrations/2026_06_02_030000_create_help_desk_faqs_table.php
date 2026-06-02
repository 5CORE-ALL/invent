<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('help_desk_faqs')) {
            return;
        }

        Schema::create('help_desk_faqs', function (Blueprint $table) {
            $table->id();
            $table->text('faq');
            $table->text('answers')->nullable();
            $table->string('link')->nullable();
            $table->string('link2')->nullable();
            $table->string('sop')->nullable();
            $table->string('video')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_faqs');
    }
};
