<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'reverb_make')) {
                $table->string('reverb_make', 255)->nullable()->after('category_id');
            }
            if (! Schema::hasColumn('product_master', 'reverb_model')) {
                $table->string('reverb_model', 255)->nullable()->after('reverb_make');
            }
            if (! Schema::hasColumn('product_master', 'reverb_finish')) {
                $table->string('reverb_finish', 255)->nullable()->after('reverb_model');
            }
            if (! Schema::hasColumn('product_master', 'reverb_year')) {
                $table->string('reverb_year', 32)->nullable()->after('reverb_finish');
            }
            if (! Schema::hasColumn('product_master', 'reverb_condition')) {
                $table->string('reverb_condition', 100)->nullable()->after('reverb_year');
            }
            if (! Schema::hasColumn('product_master', 'reverb_shipping_profile_id')) {
                $table->string('reverb_shipping_profile_id', 100)->nullable()->after('reverb_condition');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            foreach ([
                'reverb_make',
                'reverb_model',
                'reverb_finish',
                'reverb_year',
                'reverb_condition',
                'reverb_shipping_profile_id',
            ] as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
