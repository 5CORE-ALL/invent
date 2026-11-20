<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_bank_accounts')) {
            return;
        }

        Schema::table('supplier_bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_bank_accounts', 'currency')) {
                $table->string('currency', 3)->nullable();
            }
            if (! Schema::hasColumn('supplier_bank_accounts', 'amount')) {
                $table->decimal('amount', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('supplier_bank_accounts', 'file_image')) {
                $table->string('file_image')->nullable();
            }
            if (! Schema::hasColumn('supplier_bank_accounts', 'snippet_image')) {
                $table->string('snippet_image')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_bank_accounts')) {
            return;
        }

        Schema::table('supplier_bank_accounts', function (Blueprint $table) {
            foreach (['currency', 'amount', 'file_image', 'snippet_image'] as $col) {
                if (Schema::hasColumn('supplier_bank_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
