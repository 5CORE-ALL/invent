<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_portal_assets')) {
            return;
        }

        Schema::table('supplier_portal_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_portal_assets', 'sku')) {
                $table->string('sku', 120)->nullable()->after('title')->index();
            }
            if (! Schema::hasColumn('supplier_portal_assets', 'parent')) {
                $table->string('parent', 120)->nullable()->after('sku')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_portal_assets')) {
            return;
        }

        Schema::table('supplier_portal_assets', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_portal_assets', 'parent')) {
                $table->dropColumn('parent');
            }
            if (Schema::hasColumn('supplier_portal_assets', 'sku')) {
                $table->dropColumn('sku');
            }
        });
    }
};
