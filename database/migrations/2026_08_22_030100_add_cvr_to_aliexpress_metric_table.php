<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aliexpress_metric')) {
            return;
        }

        if (! Schema::hasColumn('aliexpress_metric', 'output_order')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $after = Schema::hasColumn('aliexpress_metric', 'views') ? 'views' : 'l60';
                $table->unsignedInteger('output_order')->default(0)->after($after);
            });
        }

        if (! Schema::hasColumn('aliexpress_metric', 'cvr')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $after = Schema::hasColumn('aliexpress_metric', 'output_order') ? 'output_order'
                    : (Schema::hasColumn('aliexpress_metric', 'views') ? 'views' : 'l60');
                $table->decimal('cvr', 8, 2)->default(0)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('aliexpress_metric')) {
            return;
        }

        Schema::table('aliexpress_metric', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('aliexpress_metric', 'cvr')) {
                $drop[] = 'cvr';
            }
            if (Schema::hasColumn('aliexpress_metric', 'output_order')) {
                $drop[] = 'output_order';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
