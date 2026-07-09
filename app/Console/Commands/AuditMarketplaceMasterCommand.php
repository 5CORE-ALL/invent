<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use App\Services\Support\MarketplaceMasterAuditService;
use App\Services\Support\MarketplaceMasterAuditResultsStore;
use App\Services\Support\ProductMasterMarketplaceMaps;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class AuditMarketplaceMasterCommand extends Command
{
    protected $signature = 'marketplace:audit-master
                            {master=bullet : Master: bullet|title|description|image|video|all}
                            {--sku= : Test SKU (defaults from config/marketplace_testing.php)}
                            {--marketplace= : Limit to one marketplace}
                            {--dry-run : Validate only, no live API writes (default)}
                            {--live : Live push — bullet only, single marketplace}
                            {--force-live : Skip confirmation for --live}
                            {--save : Save results to storage + docs/MARKETPLACE_MASTER_DRY_RUN_RESULTS.md}
                            {--json : Output raw JSON}';

    protected $description = 'Audit Product Master marketplace API readiness (dry-run by default; --live for real push)';

    public function handle(MarketplaceMasterAuditService $audit, MarketplaceMasterAuditResultsStore $store): int
    {
        $master = strtolower(trim($this->argument('master')));
        $sku = $this->option('sku') ? trim((string) $this->option('sku')) : null;
        $marketplace = $this->option('marketplace') ? trim((string) $this->option('marketplace')) : null;
        $live = (bool) $this->option('live');
        $dryRun = ! $live;

        if ($sku === null || $sku === '') {
            $sku = $this->defaultSkuForMaster($master);
            if ($sku !== '') {
                $this->line("Using test SKU: <comment>{$sku}</comment> (config/marketplace_testing.php)");
            }
        }

        if ($live) {
            if (! $sku) {
                $this->error('--live requires --sku= (single SKU only).');

                return self::FAILURE;
            }
            if (! $marketplace) {
                $this->error('--live requires --marketplace= (one marketplace at a time).');

                return self::FAILURE;
            }
            if (! $this->option('force-live') && ! $this->confirm("LIVE PUSH will update the real listing on [{$marketplace}] for SKU [{$sku}]. Continue?")) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        return match ($master) {
            'bullet', 'bullets', 'bullet-point', 'bullet_points' => $this->auditBullet($audit, $sku, $marketplace, $dryRun),
            'title' => $this->auditGenericMaster($audit, 'title', $sku, $marketplace, $audit->auditTitle($sku, $marketplace)),
            'description' => $this->auditGenericMaster($audit, 'description', $sku, $marketplace, $audit->auditDescription($sku, $marketplace)),
            'image' => $this->auditGenericMaster($audit, 'image', $sku, $marketplace, $audit->auditImage($sku, $marketplace)),
            'video' => $this->auditGenericMaster($audit, 'video', $sku, $marketplace, $audit->auditVideo($sku, $marketplace)),
            'all' => $this->auditAll($audit, $store, $sku ?? ''),
            default => $this->invalidMaster($master),
        };
    }

    private function defaultSkuForMaster(string $master): string
    {
        $key = match ($master) {
            'title' => 'title_sku',
            'description' => 'description_sku',
            'image' => 'image_sku',
            'video' => 'video_sku',
            'all' => 'bullet_point_sku',
            default => 'bullet_point_sku',
        };

        return trim((string) config("marketplace_testing.{$key}", config('marketplace_testing.bullet_point_sku', '')));
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     */
    private function auditGenericMaster(
        MarketplaceMasterAuditService $audit,
        string $label,
        ?string $sku,
        ?string $marketplace,
        array $results,
    ): int {
        $this->info(ucfirst($label).' Master — DRY RUN (no API writes)');
        if ($sku) {
            $this->line("SKU: <comment>{$sku}</comment>");
        }

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->summarizeExitCode($results, true);
        }

        $headers = ['Marketplace', 'Creds', 'Service', 'Metrics', 'Ready', 'Notes'];
        $rows = [];
        foreach ($results as $mp => $r) {
            $notes = array_merge($r['issues'] ?? [], $r['warnings'] ?? []);
            if (isset($r['title_chars'])) {
                $notes[] = 'title '.$r['title_chars'].' chars';
            }
            if (isset($r['description_chars'])) {
                $notes[] = 'desc '.$r['description_chars'].' chars';
            }
            if (isset($r['image_count'])) {
                $notes[] = $r['image_count'].' image URL(s)';
            }
            if (isset($r['video_count'])) {
                $notes[] = $r['video_count'].' video URL(s)';
            }
            if (isset($r['would_push_lines'])) {
                $notes[] = $r['would_push_lines'].' bullet line(s)';
            }

            $rows[] = [
                $mp,
                ($r['credentials_configured'] ?? false) ? '✓' : '✗',
                ($r['has_update_method'] ?? false) ? '✓' : '✗',
                ($r['metrics_row_found'] ?? ($sku ? '✗' : '—')),
                ($r['ready'] ?? false) ? '✓' : '✗',
                $notes !== [] ? implode('; ', $notes) : 'OK',
            ];
        }
        $this->table($headers, $rows);
        $ready = collect($results)->where('ready', true)->count();
        $this->info("Ready: {$ready}/".count($results));

        return $this->summarizeExitCode($results, true);
    }

    private function auditAll(MarketplaceMasterAuditService $audit, MarketplaceMasterAuditResultsStore $store, string $sku): int
    {
        if ($sku === '') {
            $this->error('--sku required or set config/marketplace_testing.php');

            return self::FAILURE;
        }

        $this->info('Auditing all masters (dry-run) for SKU: '.$sku);
        $payload = $audit->auditAllMasters($sku);

        if ($this->option('save')) {
            $store->save($payload);
            $this->info('Saved: '.$store->markdownPath());
            $this->info('Saved: '.$store->jsonPath());
        }

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($payload['masters'] as $name => $block) {
                $this->newLine();
                $this->info(ucfirst($name).': '.($block['ready_count'] ?? 0).'/'.($block['total_count'] ?? 0).' ready');
            }
            $nw = count($payload['not_working'] ?? []);
            if ($nw > 0) {
                $this->warn("{$nw} marketplace/master combination(s) not ready — see docs/MARKETPLACE_MASTER_DRY_RUN_RESULTS.md");
            }
        }

        $allReady = collect($payload['not_working'] ?? [])->isEmpty();

        return $allReady ? self::SUCCESS : self::FAILURE;
    }

    private function auditBullet(
        MarketplaceMasterAuditService $audit,
        ?string $sku,
        ?string $marketplace,
        bool $dryRun,
    ): int {
        $this->info($dryRun
            ? 'Bullet Point Master — DRY RUN (no API writes)'
            : 'Bullet Point Master — LIVE PUSH');

        if ($sku) {
            $this->line("SKU: <comment>{$sku}</comment>");
        } else {
            $this->warn('No --sku provided: checking credentials/services only (no payload or listing mapping).');
        }

        $results = $audit->auditBullet($sku, $marketplace, true);

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->summarizeExitCode($results, $dryRun);
        }

        $headers = ['Marketplace', 'Creds', 'Service', 'Metrics', 'Ready', 'Notes'];
        $rows = [];

        foreach ($results as $mp => $r) {
            $notes = array_merge($r['issues'] ?? [], $r['warnings'] ?? []);
            if ($sku && isset($r['listing_found']) && $r['listing_found'] === false) {
                $notes[] = 'eBay item_id missing';
            }
            if ($sku && ($r['would_push_lines'] ?? 0) > 0) {
                $notes[] = 'would push '.$r['would_push_lines'].' line(s), '.($r['would_push_chars'] ?? 0).' chars';
            }

            $rows[] = [
                $mp,
                ($r['credentials_configured'] ?? false) ? '✓' : '✗',
                ($r['has_update_method'] ?? false) ? '✓' : '✗',
                ($r['metrics_row_found'] ?? ($sku ? '✗' : '—')),
                ($r['ready'] ?? false) ? '✓' : '✗',
                $notes !== [] ? implode('; ', $notes) : 'OK',
            ];
        }

        $this->table($headers, $rows);

        if (! $dryRun && $sku && $marketplace) {
            return $this->liveBulletPush($sku, $marketplace);
        }

        $ready = collect($results)->where('ready', true)->count();
        $total = count($results);
        $this->newLine();
        $this->info("Ready: {$ready}/{$total} marketplace(s).");

        if ($dryRun && $sku && $marketplace) {
            $key = app(\App\Services\Support\MarketplaceApiConfigService::class)->resolveKey($marketplace);
            $r = $results[$key] ?? $results[$marketplace] ?? null;
            if ($r && ($r['ready'] ?? false)) {
                $this->comment("Dry run passed for [{$marketplace}]. To push live: php artisan marketplace:audit-master bullet --sku={$sku} --marketplace={$marketplace} --live");
            }
        }

        return $this->summarizeExitCode($results, $dryRun);
    }

    private function liveBulletPush(string $sku, string $marketplace): int
    {
        $key = app(\App\Services\Support\MarketplaceApiConfigService::class)->resolveKey($marketplace);
        if (! isset(ProductMasterMarketplaceMaps::bulletServiceMap()[$key])) {
            $this->error("Unknown bullet marketplace: {$marketplace}");

            return self::FAILURE;
        }

        $this->warn('Calling live BulletPointMaster push...');

        $controller = app(BulletPointMasterController::class);
        $response = $controller->update(new Request([
            'sku' => $sku,
            'updates' => [
                ['marketplace' => $key, 'bullet_points' => ''],
            ],
        ]));

        $payload = $response->getData(true);
        $result = $payload['results'][$key] ?? $payload['results'][$marketplace] ?? $payload;

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Success: '.(($result['success'] ?? false) ? 'yes' : 'no'));
            $this->line('Message: '.($result['message'] ?? ''));
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     */
    private function summarizeExitCode(array $results, bool $dryRun): int
    {
        if ($results === []) {
            return self::FAILURE;
        }

        $anyReady = collect($results)->contains(fn ($r) => (bool) ($r['ready'] ?? false));
        $allReady = collect($results)->every(fn ($r) => (bool) ($r['ready'] ?? false));

        if ($dryRun) {
            return $anyReady ? self::SUCCESS : self::FAILURE;
        }

        return $allReady ? self::SUCCESS : self::FAILURE;
    }

    private function notImplementedYet(string $master, ?string $hint = null): int
    {
        $this->warn("Audit for [{$master}] is not in this command yet.");
        if ($hint) {
            $this->line($hint);
        }
        $this->line('Recommended order: bullet → title → description → image → video');

        return self::FAILURE;
    }

    private function invalidMaster(string $master): int
    {
        $this->error("Unknown master: {$master}. Use: bullet, title, description, image, video, all");

        return self::FAILURE;
    }
}
