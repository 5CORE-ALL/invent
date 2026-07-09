<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\File;

/**
 * Persists marketplace master dry-run audit results for tracking before live pushes.
 */
class MarketplaceMasterAuditResultsStore
{
    public function jsonPath(): string
    {
        return storage_path('app/marketplace-master-audit/latest.json');
    }

    public function markdownPath(): string
    {
        return base_path('docs/MARKETPLACE_MASTER_DRY_RUN_RESULTS.md');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(array $payload): void
    {
        $dir = dirname($this->jsonPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        File::put($this->jsonPath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $md = $this->buildMarkdown($payload);
        File::put($this->markdownPath(), $md);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildMarkdown(array $payload): string
    {
        $sku = (string) ($payload['test_sku'] ?? '');
        $at = (string) ($payload['audited_at'] ?? now()->toIso8601String());
        $lines = [
            '# Marketplace Master Dry-Run Results',
            '',
            'Last updated: '.$at,
            'Test SKU: `'.$sku.'`',
            '',
            '> Dry-run only — no live API writes. Live pushes after all masters are complete.',
            '',
        ];

        foreach (['bullet', 'title', 'description', 'image', 'video'] as $master) {
            $block = $payload['masters'][$master] ?? null;
            if (! is_array($block)) {
                continue;
            }
            $ready = (int) ($block['ready_count'] ?? 0);
            $total = (int) ($block['total_count'] ?? 0);
            $lines[] = '## '.ucfirst($master).' Master ('.$ready.'/'.$total.' ready)';
            $lines[] = '';
            $lines[] = '| Marketplace | Ready | Notes |';
            $lines[] = '|-------------|-------|-------|';

            foreach ($block['marketplaces'] ?? [] as $mp => $r) {
                $ok = ($r['ready'] ?? false) ? 'Yes' : '**No**';
                $notes = array_merge($r['issues'] ?? [], $r['warnings'] ?? []);
                if ($notes === []) {
                    $notes = ['OK'];
                }
                $lines[] = '| '.$mp.' | '.$ok.' | '.str_replace('|', '/', implode('; ', $notes)).' |';
            }
            $lines[] = '';
        }

        $notWorking = $payload['not_working'] ?? [];
        $lines[] = '## Platforms not ready (dry-run failed)';
        $lines[] = '';
        if ($notWorking === []) {
            $lines[] = '_All audited platforms pass dry-run (credentials + service wiring)._';
        } else {
            $lines[] = '| Master | Marketplace | Reason |';
            $lines[] = '|--------|-------------|--------|';
            foreach ($notWorking as $row) {
                $lines[] = '| '.($row['master'] ?? '').' | '.($row['marketplace'] ?? '').' | '.str_replace('|', '/', (string) ($row['reason'] ?? '')).' |';
            }
        }
        $lines[] = '';

        $risks = $payload['live_risks'] ?? [];
        $lines[] = '## Live push risks (may fail until data synced)';
        $lines[] = '';
        if ($risks === []) {
            $lines[] = '_No elevated live risks flagged._';
        } else {
            $lines[] = '| Master | Marketplace | Risk |';
            $lines[] = '|--------|-------------|------|';
            foreach ($risks as $row) {
                $lines[] = '| '.($row['master'] ?? '').' | '.($row['marketplace'] ?? '').' | '.str_replace('|', '/', (string) ($row['reason'] ?? '')).' |';
            }
        }
        $lines[] = '';

        return implode("\n", $lines);
    }
}
