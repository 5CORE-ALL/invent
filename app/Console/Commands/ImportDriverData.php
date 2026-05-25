<?php

namespace App\Console\Commands;

use App\Models\DriverFolder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportDriverData extends Command
{
    protected $signature = 'resources:import-driver-data
                            {file? : Path to driver_data SQL file}
                            {--fresh : Truncate driver_data before import}';

    protected $description = 'Import driver_data rows safely (ignores invalid created_by user ids)';

    public function handle(): int
    {
        $path = $this->argument('file') ?: database_path('seeders/data/driver_data.sql');

        if (! File::exists($path)) {
            $this->error("SQL file not found: {$path}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('driver_data')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->info('Truncated driver_data.');
        }

        $sql = File::get($path);
        $sql = preg_replace(
            "/,\s*'(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})',\s*(\d+)\)/",
            ", '$1', NULL)",
            $sql
        );

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared($sql);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->sanitizeCreatedBy();

        $folderIds = DB::table('driver_data')
            ->where('folder_id', '>', 0)
            ->distinct()
            ->pluck('folder_id');

        foreach ($folderIds as $folderId) {
            DriverFolder::firstOrCreate(
                ['id' => $folderId],
                ['name' => 'Folder '.$folderId, 'parent_id' => 0]
            );
        }

        $count = DB::table('driver_data')->count();
        $this->info("Import complete: {$count} rows, ".count($folderIds).' folders.');

        return self::SUCCESS;
    }

    protected function sanitizeCreatedBy(): void
    {
        $validUserIds = DB::table('users')->pluck('id');

        if ($validUserIds->isEmpty()) {
            DB::table('driver_data')->whereNotNull('created_by')->update(['created_by' => null]);
            DB::table('driver_folders')->whereNotNull('created_by')->update(['created_by' => null]);

            return;
        }

        DB::table('driver_data')
            ->whereNotNull('created_by')
            ->whereNotIn('created_by', $validUserIds)
            ->update(['created_by' => null]);

        DB::table('driver_folders')
            ->whereNotNull('created_by')
            ->whereNotIn('created_by', $validUserIds)
            ->update(['created_by' => null]);
    }
}
