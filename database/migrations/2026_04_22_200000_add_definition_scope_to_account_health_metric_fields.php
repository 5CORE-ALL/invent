<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_health_metric_field_definitions')) {
            return;
        }

        Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
            if (! Schema::hasColumn('account_health_metric_field_definitions', 'definition_scope')) {
                $table->string('definition_scope', 96)->default('legacy');
            }
        });

        try {
            Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
                $table->dropUnique(['field_key']);
            });
        } catch (\Throwable $e) {
            try {
                Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
                    $table->dropUnique('account_health_metric_field_definitions_field_key_unique');
                });
            } catch (\Throwable $e2) {
            }
        }

        if (Schema::hasColumn('account_health_metric_field_definitions', 'definition_scope')) {
            try {
                Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
                    $table->unique(['definition_scope', 'field_key'], 'ahm_field_def_scope_key_unique');
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_health_metric_field_definitions')) {
            return;
        }

        try {
            Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
                $table->dropUnique('ahm_field_def_scope_key_unique');
            });
        } catch (\Throwable $e) {
        }

        Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('account_health_metric_field_definitions', 'definition_scope')) {
                $table->dropColumn('definition_scope');
            }
        });

        try {
            Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
                $table->unique('field_key');
            });
        } catch (\Throwable $e) {
        }
    }
};
