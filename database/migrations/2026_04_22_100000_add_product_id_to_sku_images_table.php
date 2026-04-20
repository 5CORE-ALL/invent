<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sku_images') || Schema::hasColumn('sku_images', 'product_id')) {
            return;
        }

        $hadSkuId = Schema::hasColumn('sku_images', 'sku_id');

        Schema::table('sku_images', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('id');
        });

        if ($hadSkuId) {
            if (Schema::hasTable('skus')) {
                if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                    try {
                        DB::statement('
                            UPDATE sku_images AS si
                            INNER JOIN skus AS s ON s.id = si.sku_id
                            INNER JOIN product_master AS pm ON pm.sku = s.sku_code
                            SET si.product_id = pm.id
                            WHERE si.product_id IS NULL
                        ');
                    } catch (\Throwable) {
                    }
                }
            }

            try {
                $rows = DB::table('sku_images')
                    ->whereNull('product_id')
                    ->select('id', 'sku_id')
                    ->get();
                foreach ($rows as $row) {
                    $productId = (int) $row->sku_id;
                    if (DB::table('product_master')->where('id', $productId)->exists()) {
                        DB::table('sku_images')
                            ->where('id', $row->id)
                            ->update(['product_id' => $productId]);
                    }
                }
            } catch (\Throwable) {
            }
        }

        if ($hadSkuId) {
            if (Schema::hasTable('sku_images')) {
                try {
                    DB::table('sku_images')->whereNull('product_id')->delete();
                } catch (\Throwable) {
                }
            }
        }

        if (Schema::hasColumn('sku_images', 'sku_id')) {
            $this->dropMySqlForeignKey('sku_images', 'sku_id');
            Schema::table('sku_images', function (Blueprint $table) {
                $table->dropColumn('sku_id');
            });
        }

        if (Schema::hasTable('product_master') && Schema::hasColumn('sku_images', 'product_id')) {
            if (! $this->foreignKeyExists('sku_images', 'sku_images_product_id_foreign')) {
                Schema::table('sku_images', function (Blueprint $table) {
                    $table->foreign('product_id')->references('id')->on('product_master')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sku_images') || ! Schema::hasColumn('sku_images', 'product_id')) {
            return;
        }

        try {
            if ($this->foreignKeyExists('sku_images', 'sku_images_product_id_foreign')) {
                Schema::table('sku_images', function (Blueprint $table) {
                    $table->dropForeign(['product_id']);
                });
            }
        } catch (\Throwable) {
        }

        if (Schema::hasColumn('sku_images', 'product_id')) {
            Schema::table('sku_images', function (Blueprint $table) {
                $table->dropColumn('product_id');
            });
        }
    }

    private function dropMySqlForeignKey(string $table, string $column): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropForeign([$column]);
                });
            } catch (\Throwable) {
            }

            return;
        }
        $db = DB::getDatabaseName();
        $row = DB::selectOne('
            SELECT kcu.CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.TABLE_CONSTRAINTS tc
              ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
            WHERE kcu.TABLE_SCHEMA = ?
              AND kcu.TABLE_NAME = ?
              AND kcu.COLUMN_NAME = ?
              AND tc.CONSTRAINT_TYPE = "FOREIGN KEY"
        ', [$db, $table, $column]);
        if ($row?->CONSTRAINT_NAME) {
            $name = (string) $row->CONSTRAINT_NAME;
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            } catch (\Throwable) {
            }
        } else {
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropForeign([$column]);
                });
            } catch (\Throwable) {
            }
        }
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }
        $db = DB::getDatabaseName();
        $c = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->count();

        return $c > 0;
    }
};
