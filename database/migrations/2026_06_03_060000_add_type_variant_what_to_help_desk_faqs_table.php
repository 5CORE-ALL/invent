<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('help_desk_faqs')) {
            return;
        }

        Schema::table('help_desk_faqs', function (Blueprint $table) {
            if (! Schema::hasColumn('help_desk_faqs', 'type_variant')) {
                $table->text('type_variant')->nullable()->after('dept');
            }
            if (! Schema::hasColumn('help_desk_faqs', 'what')) {
                $table->text('what')->nullable()->after('type_variant');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('help_desk_faqs')) {
            return;
        }

        Schema::table('help_desk_faqs', function (Blueprint $table) {
            if (Schema::hasColumn('help_desk_faqs', 'type_variant')) {
                $table->dropColumn('type_variant');
            }
            if (Schema::hasColumn('help_desk_faqs', 'what')) {
                $table->dropColumn('what');
            }
        });
    }
};
