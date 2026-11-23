<?php

namespace App\Console\Commands;

use App\Http\Controllers\FacebookAllAdsSheetController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes /facebook-all-ads-sheet (table facebook_all_ads_sheet) and refills it
 * from the Meta API (meta_campaigns).
 *
 *   1. BACKUP  - copies the table to facebook_all_ads_sheet_backup_<timestamp>
 *                and verifies the row counts match. Aborts if not.
 *   2. WIPE + REFILL - one DB transaction: tags (Ad Type / CH / B2B-B2C) and Link are
 *                read first, every old row is deleted, the API campaigns are inserted
 *                with those tags carried over by Campaign ID. Any error rolls back.
 *
 * Restore: INSERT INTO facebook_all_ads_sheet SELECT * FROM <backup table>
 * (after emptying the live table).
 */
class RebuildFacebookSheetFromApi extends Command
{
    protected $signature = 'facebook:rebuild-sheet-from-api {--force : Skip the confirmation prompt}';

    protected $description = 'Backup, wipe and refill the Facebook all-ads sheet from the Meta API';

    public function handle(): int
    {
        $table = 'facebook_all_ads_sheet';

        if (! Schema::hasTable($table) || ! Schema::hasTable('meta_campaigns')) {
            $this->error('Missing table(s). Run: php artisan migrate');
            return 1;
        }

        $apiCount = DB::table('meta_campaigns')->whereNotNull('meta_id')->count();
        if ($apiCount === 0) {
            $this->error('meta_campaigns is empty - nothing to refill from. Run meta-ads:sync first. Nothing was changed.');
            return 1;
        }

        $rows = DB::table($table)->count();
        $this->info("Sheet rows now: {$rows}. Meta campaigns available: {$apiCount}.");

        if (! $this->option('force') && ! $this->confirm('Backup, then delete ALL sheet rows and refill from the API?')) {
            $this->line('Cancelled. Nothing was changed.');
            return 0;
        }

        // 1. Backup
        $backup = $table . '_backup_' . now()->format('Ymd_His');
        DB::statement("CREATE TABLE `{$backup}` LIKE `{$table}`");
        DB::statement("INSERT INTO `{$backup}` SELECT * FROM `{$table}`");
        $backedUp = DB::table($backup)->count();
        if ($backedUp !== $rows) {
            $this->error("Backup row count mismatch ({$backedUp} vs {$rows}). Aborted, nothing was deleted. Backup table: {$backup}");
            return 1;
        }
        $this->info("Backup OK: {$backedUp} rows in table {$backup}");

        // 2. Wipe + refill (transactional inside the controller method)
        $res = app(FacebookAllAdsSheetController::class)->syncCampaignBatchFromApi(true);
        if (! $res['success']) {
            $this->error($res['message'] . " The sheet is unchanged. Backup kept: {$backup}");
            return 1;
        }

        $this->info($res['message']);
        $this->line('Now in sheet: ' . DB::table($table)->count() . ' rows; tagged (Ad Type): '
            . DB::table($table)->whereNotNull('ad_type')->where('ad_type', '!=', '')->count());
        $this->line("Backup table kept: {$backup}");

        return 0;
    }
}
