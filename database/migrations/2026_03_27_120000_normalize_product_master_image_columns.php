<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'image7')) {
                $table->text('image7')->nullable()->after('image6');
            }
            if (! Schema::hasColumn('product_master', 'image8')) {
                $table->text('image8')->nullable()->after('image7');
            }
            if (! Schema::hasColumn('product_master', 'image9')) {
                $table->text('image9')->nullable()->after('image8');
            }
            if (! Schema::hasColumn('product_master', 'image10')) {
                $table->text('image10')->nullable()->after('image9');
            }
            if (! Schema::hasColumn('product_master', 'image11')) {
                $table->text('image11')->nullable()->after('image10');
            }
            if (! Schema::hasColumn('product_master', 'image12')) {
                $table->text('image12')->nullable()->after('image11');
            }
        });

        $legacyToCanonical = [
            'images7' => 'image7',
            'images8' => 'image8',
            'images9' => 'image9',
            'images10' => 'image10',
            'images11' => 'image11',
            'images12' => 'image12',
        ];

        foreach ($legacyToCanonical as $legacy => $canonical) {
            if (Schema::hasColumn('product_master', $legacy) && Schema::hasColumn('product_master', $canonical)) {
                DB::statement("UPDATE product_master SET `{$canonical}` = COALESCE(`{$canonical}`, `{$legacy}`)");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            foreach (['image7', 'image8', 'image9', 'image10', 'image11', 'image12'] as $column) {
                if (Schema::hasColumn('product_master', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
