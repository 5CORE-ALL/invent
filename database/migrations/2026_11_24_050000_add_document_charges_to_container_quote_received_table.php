<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('container_quote_received') || Schema::hasColumn('container_quote_received', 'document_charges')) {
            return;
        }

        Schema::table('container_quote_received', function (Blueprint $table) {
            $table->decimal('document_charges', 14, 2)->nullable()->after('pickup_charges');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('container_quote_received') && Schema::hasColumn('container_quote_received', 'document_charges')) {
            Schema::table('container_quote_received', function (Blueprint $table) {
                $table->dropColumn('document_charges');
            });
        }
    }
};
