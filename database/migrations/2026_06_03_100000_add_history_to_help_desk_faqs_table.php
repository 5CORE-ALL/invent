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
            if (! Schema::hasColumn('help_desk_faqs', 'created_by_email')) {
                $table->string('created_by_email')->nullable();
            }
            if (! Schema::hasColumn('help_desk_faqs', 'updated_by_email')) {
                $table->string('updated_by_email')->nullable();
            }
            if (! Schema::hasColumn('help_desk_faqs', 'edit_history')) {
                $table->json('edit_history')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('help_desk_faqs')) {
            return;
        }

        Schema::table('help_desk_faqs', function (Blueprint $table) {
            foreach (['created_by_email', 'updated_by_email', 'edit_history'] as $col) {
                if (Schema::hasColumn('help_desk_faqs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
