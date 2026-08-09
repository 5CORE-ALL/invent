<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_dashboard_preferences')) {
            return;
        }

        Schema::create('user_dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            /** @var list<string> Hidden item keys, e.g. pricing-card__gpft */
            $table->json('hidden_items')->nullable();
            /** @var array<string, list<array{label:string,url:string}>> Extra page links per card id */
            $table->json('custom_links')->nullable();
            /** @var array<string, list<array{key:string,label?:string}>> Extra KPI badges per card (badge:page|field) */
            $table->json('custom_kpis')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_preferences');
    }
};
