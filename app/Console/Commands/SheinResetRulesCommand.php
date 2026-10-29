<?php

namespace App\Console\Commands;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Delete every saved Shein pricing rule: the Std prc vs dil rules and the Sprc Dil slabs.
 *
 * Only the two Shein rows are touched (Amazon and the other channels keep their own rules). A JSON backup is written
 * first so a mistake can be undone with --restore. With no saved rules the Shein background push saves and pushes
 * nothing until new rules are entered on /shein-pricing (see SheinSprcDilAutoPushService::hasSavedRules).
 */
class SheinResetRulesCommand extends Command
{
    protected $signature = 'shein:reset-rules
        {--force : Actually delete (without it only a dry run is shown)}
        {--restore= : Restore the rules from a backup file written by an earlier run}';

    protected $description = 'Back up and delete all saved Shein rules (Std prc vs dil + Sprc Dil slabs).';

    /** @var list<string> */
    private const NAMES = ['shein_std_prc_vs_dil', 'shein_dil_vs_groi'];

    public function handle(): int
    {
        if ($file = $this->option('restore')) {
            return $this->restore((string) $file);
        }

        $rows = ChannelTabulatorColumnSetting::query()->whereIn('channel_name', self::NAMES)->get();
        if ($rows->isEmpty()) {
            $this->info('No saved Shein rules. Nothing to delete.');

            return self::SUCCESS;
        }
        foreach ($rows as $row) {
            $this->line($row->channel_name.': '.strlen((string) json_encode($row->visibility)).' bytes, updated '.$row->updated_at);
        }
        if (! $this->option('force')) {
            $this->warn('Dry run. Add --force to back up and delete these rows.');

            return self::SUCCESS;
        }

        $path = 'backups/shein-rules-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode(
            $rows->map(fn ($r) => ['channel_name' => $r->channel_name, 'visibility' => $r->visibility])->values(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
        $this->info('Backup: '.Storage::disk('local')->path($path));

        $n = ChannelTabulatorColumnSetting::query()->whereIn('channel_name', self::NAMES)->delete();
        $this->info("Deleted {$n} Shein rule row(s). Enter the new rules on /shein-pricing; nothing is pushed until then.");

        return self::SUCCESS;
    }

    private function restore(string $file): int
    {
        if (! is_file($file)) {
            $this->error('Backup file not found: '.$file);

            return self::FAILURE;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            $this->error('Backup file is not valid JSON.');

            return self::FAILURE;
        }
        foreach ($data as $item) {
            if (! in_array($item['channel_name'] ?? '', self::NAMES, true)) {
                continue;
            }
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => $item['channel_name']],
                ['visibility' => $item['visibility']]
            );
            $this->info('Restored '.$item['channel_name']);
        }

        return self::SUCCESS;
    }
}
