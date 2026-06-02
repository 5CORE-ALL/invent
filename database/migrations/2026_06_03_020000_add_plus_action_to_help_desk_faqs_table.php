<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('help_desk_faqs') || Schema::hasColumn('help_desk_faqs', 'plus_action')) {
            return;
        }

        Schema::table('help_desk_faqs', function (Blueprint $table) {
            $table->text('plus_action')->nullable()->after('ca');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('help_desk_faqs') && Schema::hasColumn('help_desk_faqs', 'plus_action')) {
            Schema::table('help_desk_faqs', function (Blueprint $table) {
                $table->dropColumn('plus_action');
            });
        }
    }
};
