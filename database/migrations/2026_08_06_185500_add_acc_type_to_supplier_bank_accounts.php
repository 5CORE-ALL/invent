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

        if (! Schema::hasColumn('supplier_bank_accounts', 'acc_type')) {
            Schema::table('supplier_bank_accounts', function (Blueprint $table) {
                $table->string('acc_type', 10)->nullable()->after('account_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_bank_accounts') && Schema::hasColumn('supplier_bank_accounts', 'acc_type')) {
            Schema::table('supplier_bank_accounts', function (Blueprint $table) {
                $table->dropColumn('acc_type');
            });
        }
    }
};
