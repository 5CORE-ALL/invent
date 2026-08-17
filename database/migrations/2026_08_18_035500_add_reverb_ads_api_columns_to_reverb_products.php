<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reverb_products')) {
            return;
        }

        if (! Schema::hasColumn('reverb_products', 'api_recommended_bid')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                $after = Schema::hasColumn('reverb_products', 'recommended_bid') ? 'recommended_bid' : 'bump_bid';
                $table->string('api_recommended_bid', 50)->nullable()->after($after)
                    ->comment('Reverb API recommended bump bid (seller ads dashboard)');
            });
        }

        if (! Schema::hasColumn('reverb_products', 'total_interactions')) {
            Schema::table('reverb_products', function (Blueprint $table) {
                $after = Schema::hasColumn('reverb_products', 'api_recommended_bid') ? 'api_recommended_bid' : 'bump_bid';
                $table->unsignedInteger('total_interactions')->nullable()->default(0)->after($after)
                    ->comment('Reverb API total interactions / bump impressions');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reverb_products')) {
            return;
        }

        Schema::table('reverb_products', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('reverb_products', 'total_interactions')) {
                $drops[] = 'total_interactions';
            }
            if (Schema::hasColumn('reverb_products', 'api_recommended_bid')) {
                $drops[] = 'api_recommended_bid';
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
