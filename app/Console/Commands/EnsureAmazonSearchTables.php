<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

class EnsureAmazonSearchTables extends Command
{
    protected $signature = 'repricer:ensure-amazon-search-tables';
    protected $description = 'Create/repair amazon search tables and columns (raw responses + competitor asins).';

    public function handle(): int
    {
        $created = [];
        $columnsAdded = [];
        $driver = DB::getDriverName();

        // --- amazon_search_raw_responses ---
        if (!Schema::hasTable('amazon_search_raw_responses')) {
            Schema::create('amazon_search_raw_responses', function (Blueprint $table) {
                $table->id();
                $table->string('search_query')->index();
                $table->string('marketplace', 50)->nullable();
                $table->unsignedSmallInteger('page')->nullable();
                $table->longText('raw_response');
                $table->unsignedSmallInteger('pages_count')->nullable();
                $table->timestamps();
                $table->index(['search_query', 'page']);
                $table->index('created_at');
            });
            $created[] = 'amazon_search_raw_responses';
        } else {
            Schema::table('amazon_search_raw_responses', function (Blueprint $table) use (&$columnsAdded) {
                if (!Schema::hasColumn('amazon_search_raw_responses', 'page')) {
                    $table->unsignedSmallInteger('page')->nullable()->after('marketplace');
                    $columnsAdded[] = 'amazon_search_raw_responses.page';
                }
                if (!Schema::hasColumn('amazon_search_raw_responses', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                    $columnsAdded[] = 'amazon_search_raw_responses.created_at';
                }
                if (!Schema::hasColumn('amazon_search_raw_responses', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                    $columnsAdded[] = 'amazon_search_raw_responses.updated_at';
                }
            });
        }

        // --- serp_api_raw_responses ---
        if (!Schema::hasTable('serp_api_raw_responses')) {
            Schema::create('serp_api_raw_responses', function (Blueprint $table) {
                $table->id();
                $table->string('search_query')->index();
                $table->unsignedSmallInteger('page')->default(1);
                $table->string('marketplace', 50)->nullable();
                $table->json('request_params')->nullable();
                $table->unsignedSmallInteger('http_status');
                $table->longText('raw_body');
                $table->boolean('success')->default(false);
                $table->timestamps();
                $table->index(['search_query', 'page']);
                $table->index('created_at');
            });
            $created[] = 'serp_api_raw_responses';
        } else {
            Schema::table('serp_api_raw_responses', function (Blueprint $table) use (&$columnsAdded) {
                if (!Schema::hasColumn('serp_api_raw_responses', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                    $columnsAdded[] = 'serp_api_raw_responses.created_at';
                }
                if (!Schema::hasColumn('serp_api_raw_responses', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                    $columnsAdded[] = 'serp_api_raw_responses.updated_at';
                }
            });
        }

        // --- amazon_competitor_asins (add missing columns if table exists) ---
        if (Schema::hasTable('amazon_competitor_asins')) {
            Schema::table('amazon_competitor_asins', function (Blueprint $table) use (&$columnsAdded) {
                if (!Schema::hasColumn('amazon_competitor_asins', 'seller_name')) {
                    $table->string('seller_name', 255)->nullable()->after('title');
                    $columnsAdded[] = 'amazon_competitor_asins.seller_name';
                }
                if (!Schema::hasColumn('amazon_competitor_asins', 'extracted_old_price')) {
                    $table->decimal('extracted_old_price', 10, 2)->nullable()->after('price');
                    $columnsAdded[] = 'amazon_competitor_asins.extracted_old_price';
                }
                if (!Schema::hasColumn('amazon_competitor_asins', 'delivery')) {
                    $table->json('delivery')->nullable()->after('reviews');
                    $columnsAdded[] = 'amazon_competitor_asins.delivery';
                }
            });
        }

        if (!empty($created)) {
            $this->info('Created table(s): ' . implode(', ', $created));
        }
        if (!empty($columnsAdded)) {
            $this->info('Added missing column(s): ' . implode(', ', $columnsAdded));
        }
        // Ensure id column is AUTO_INCREMENT (fixes MySQL 1364 "Field 'id' doesn't have a default value")
        $tables = ['amazon_search_raw_responses', 'serp_api_raw_responses', 'amazon_competitor_asins'];
        $fixed = [];
        if ($driver === 'mysql') {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }
                try {
                    $col = DB::selectOne("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'id'", [$table]);
                    if ($col && (strpos(strtoupper($col->EXTRA ?? ''), 'AUTO_INCREMENT') === false)) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                        $fixed[] = $table;
                    }
                } catch (\Throwable $e) {
                    $this->warn("Could not fix id on {$table}: " . $e->getMessage());
                }
            }
            if (!empty($fixed)) {
                $this->info('Fixed AUTO_INCREMENT on: ' . implode(', ', $fixed));
            }
        }

        if (empty($created) && empty($columnsAdded) && empty($fixed)) {
            $this->info('All tables and columns are present. Nothing to do.');
        }

        return 0;
    }
}
