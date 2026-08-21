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

        if (! Schema::hasColumn('aliexpress_metric', 'reviews')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $after = Schema::hasColumn('aliexpress_metric', 'cvr') ? 'cvr'
                    : (Schema::hasColumn('aliexpress_metric', 'views') ? 'views' : 'l60');
                $table->unsignedInteger('reviews')->default(0)->after($after);
            });
        }

        if (! Schema::hasColumn('aliexpress_metric', 'avg_rating')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $after = Schema::hasColumn('aliexpress_metric', 'reviews') ? 'reviews'
                    : (Schema::hasColumn('aliexpress_metric', 'cvr') ? 'cvr' : 'l60');
                $table->decimal('avg_rating', 3, 2)->default(0)->after($after);
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
            if (Schema::hasColumn('aliexpress_metric', 'avg_rating')) {
                $drop[] = 'avg_rating';
            }
            if (Schema::hasColumn('aliexpress_metric', 'reviews')) {
                $drop[] = 'reviews';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
