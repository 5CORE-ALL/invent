<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('help_desk_faqs') || Schema::hasColumn('help_desk_faqs', 'group_name')) {
            return;
        }

        Schema::table('help_desk_faqs', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('help_desk_faqs') && Schema::hasColumn('help_desk_faqs', 'group_name')) {
            Schema::table('help_desk_faqs', function (Blueprint $table) {
                $table->dropColumn('group_name');
            });
        }
    }
};
