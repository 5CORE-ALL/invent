<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('escalated_claims_links')) {
            return;
        }

        Schema::table('escalated_claims_links', function (Blueprint $table) {
            if (! Schema::hasColumn('escalated_claims_links', 'required_parameter')) {
                $table->decimal('required_parameter', 8, 2)->nullable()->after('link');
            }
            if (! Schema::hasColumn('escalated_claims_links', 'current_parameter')) {
                $table->decimal('current_parameter', 8, 2)->nullable()->after('required_parameter');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('escalated_claims_links')) {
            return;
        }

        Schema::table('escalated_claims_links', function (Blueprint $table) {
            if (Schema::hasColumn('escalated_claims_links', 'current_parameter')) {
                $table->dropColumn('current_parameter');
            }
            if (Schema::hasColumn('escalated_claims_links', 'required_parameter')) {
                $table->dropColumn('required_parameter');
            }
        });
    }
};
