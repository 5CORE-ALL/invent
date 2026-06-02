<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('help_desk_faqs') || Schema::hasColumn('help_desk_faqs', 'messages')) {
            return;
        }

        Schema::table('help_desk_faqs', function (Blueprint $table) {
            $table->string('messages', 200)->nullable()->after('plus_action');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('help_desk_faqs') && Schema::hasColumn('help_desk_faqs', 'messages')) {
            Schema::table('help_desk_faqs', function (Blueprint $table) {
                $table->dropColumn('messages');
            });
        }
    }
};
