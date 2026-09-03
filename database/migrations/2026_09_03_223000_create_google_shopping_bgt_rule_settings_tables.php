<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'google_shopping_bgt_views_rule_settings',
        'google_shopping_bgt_cvr_rule_settings',
        'google_shopping_bgt_prc_rule_settings',
        'google_shopping_bgt_reviews_rule_settings',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                continue;
            }
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->json('rule');
                $blueprint->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
};
