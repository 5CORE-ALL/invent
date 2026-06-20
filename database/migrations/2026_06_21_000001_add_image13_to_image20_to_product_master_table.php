<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        $after = [
            'image13' => 'image12',
            'image14' => 'image13',
            'image15' => 'image14',
            'image16' => 'image15',
            'image17' => 'image16',
            'image18' => 'image17',
            'image19' => 'image18',
            'image20' => 'image19',
        ];

        foreach ($after as $col => $prev) {
            if (Schema::hasColumn('product_master', $col)) {
                continue;
            }
            Schema::table('product_master', function (Blueprint $table) use ($col, $prev) {
                $table->text($col)->nullable()->after($prev);
            });
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

        $columns = ['image13', 'image14', 'image15', 'image16', 'image17', 'image18', 'image19', 'image20'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('product_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
