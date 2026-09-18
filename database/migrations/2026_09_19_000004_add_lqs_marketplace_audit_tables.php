<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lqs_marketplace_audit_prompts')) {
            Schema::create('lqs_marketplace_audit_prompts', function (Blueprint $table) {
                $table->id();
                $table->string('marketplace', 50)->unique();
                $table->longText('prompt');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('lqs_marketplace_scores')) {
            Schema::table('lqs_marketplace_scores', function (Blueprint $table) {
                if (! Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
                    $table->text('audit_findings')->nullable()->after('listing_id');
                }
                if (! Schema::hasColumn('lqs_marketplace_scores', 'audit_suggestions')) {
                    $table->text('audit_suggestions')->nullable()->after('audit_findings');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lqs_marketplace_scores')) {
            Schema::table('lqs_marketplace_scores', function (Blueprint $table) {
                if (Schema::hasColumn('lqs_marketplace_scores', 'audit_suggestions')) {
                    $table->dropColumn('audit_suggestions');
                }
                if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
                    $table->dropColumn('audit_findings');
                }
            });
        }

        Schema::dropIfExists('lqs_marketplace_audit_prompts');
    }
};
