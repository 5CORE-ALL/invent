<?php

namespace App\Models;

use App\Support\AmazonAdsPauseRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

class AmazonAdsPauseRuleState extends Model
{
    protected $table = 'amazon_ads_pause_rule_states';

    protected $fillable = [
        'channel',
        'campaign_id',
        'campaign_name',
        'paused_reason',
        'paused_at',
        'reactivated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'paused_at' => 'datetime',
        'reactivated_at' => 'datetime',
    ];

    public static function ensureTable(): void
    {
        if (Schema::hasTable('amazon_ads_pause_rule_states')) {
            return;
        }
        try {
            Schema::create('amazon_ads_pause_rule_states', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 8);
                $table->string('campaign_id', 64);
                $table->string('campaign_name')->nullable();
                $table->string('paused_reason', 500)->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('reactivated_at')->nullable();
                $table->timestamps();
                $table->unique(['channel', 'campaign_id']);
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_pause_rule_states create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_pause_rule_states: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Open Pause Rule pauses: campaign_id => paused_at (Y-m-d H:i:s).
     *
     * @return array<string, string>
     */
    public static function openPauseAtByCampaignId(string $channel): array
    {
        self::ensureTable();
        $out = [];
        foreach (self::query()
            ->where('channel', $channel)
            ->whereNotNull('paused_at')
            ->whereNull('reactivated_at')
            ->get(['campaign_id', 'paused_at']) as $row
        ) {
            $cid = trim((string) $row->campaign_id);
            if ($cid === '' || $row->paused_at === null) {
                continue;
            }
            $out[$cid] = $row->paused_at->format('Y-m-d H:i:s');
        }

        return $out;
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, array{paused_reason: string, reactivated_at: string, paused_at: string}>
     */
    public static function mapForCampaignIds(array $campaignIds): array
    {
        self::ensureTable();
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => trim((string) $id), $campaignIds),
            static fn (string $id): bool => $id !== ''
        )));
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (self::query()->whereIn('campaign_id', $ids)->get() as $row) {
            $cid = preg_replace('/\D+/', '', trim((string) $row->campaign_id)) ?: trim((string) $row->campaign_id);
            if ($cid === '') {
                continue;
            }
            $entry = [
                'paused_reason' => trim((string) ($row->paused_reason ?? '')),
                'paused_at' => $row->paused_at ? $row->paused_at->format('j M Y H:i') : '',
                'reactivated_at' => $row->reactivated_at ? $row->reactivated_at->format('j M Y H:i') : '',
            ];
            $out[$cid] = $entry;
            $out['id:'.$cid] = $entry;
        }

        return $out;
    }

    /**
     * @param  list<string>  $campaignNames
     * @return array<string, array{paused_reason: string, reactivated_at: string, paused_at: string}>
     */
    public static function mapForCampaignNames(array $campaignNames): array
    {
        self::ensureTable();
        $want = [];
        foreach ($campaignNames as $name) {
            $key = AmazonAdsPauseRule::normalizeCampaignName((string) $name);
            if ($key !== '') {
                $want[$key] = true;
            }
        }
        if ($want === []) {
            return [];
        }

        $out = [];
        foreach (self::query()->whereNotNull('reactivated_at')->get(['campaign_name', 'paused_reason', 'paused_at', 'reactivated_at']) as $row) {
            $key = AmazonAdsPauseRule::normalizeCampaignName((string) ($row->campaign_name ?? ''));
            if ($key === '' || ! isset($want[$key])) {
                continue;
            }
            $out[$key] = [
                'paused_reason' => trim((string) ($row->paused_reason ?? '')),
                'paused_at' => $row->paused_at ? $row->paused_at->format('j M Y H:i') : '',
                'reactivated_at' => $row->reactivated_at ? $row->reactivated_at->format('j M Y H:i') : '',
            ];
        }

        return $out;
    }

    public static function recordPause(string $channel, string $campaignId, string $campaignName, string $reason): void
    {
        self::ensureTable();
        $cid = trim($campaignId);
        if ($cid === '' || ! in_array($channel, ['sp', 'sb'], true)) {
            return;
        }
        $reason = trim($reason) !== '' ? trim($reason) : AmazonAdsPauseRule::fallbackPauseReason();
        $existing = self::query()->where('channel', $channel)->where('campaign_id', $cid)->first();
        self::query()->updateOrCreate(
            ['channel' => $channel, 'campaign_id' => $cid],
            [
                'campaign_name' => $campaignName !== '' ? $campaignName : ($existing?->campaign_name),
                'paused_reason' => $reason,
                'paused_at' => $existing?->paused_at ?? now(),
                'reactivated_at' => null,
            ]
        );
    }

    public static function recordReactivate(string $channel, string $campaignId, string $campaignName, string $reason): void
    {
        self::ensureTable();
        $cid = trim($campaignId);
        if ($cid === '' || ! in_array($channel, ['sp', 'sb'], true)) {
            return;
        }
        $existing = self::query()->where('channel', $channel)->where('campaign_id', $cid)->first();
        $keep = trim((string) ($existing?->paused_reason ?? ''));
        if ($keep !== '' && strncasecmp($keep, 'Active', 6) === 0) {
            $keep = '';
        }
        $incoming = trim($reason);
        if ($incoming !== '' && strncasecmp($incoming, 'Active', 6) === 0) {
            $incoming = '';
        }
        $use = $keep !== '' ? $keep : ($incoming !== '' ? $incoming : AmazonAdsPauseRule::fallbackPauseReason());
        self::query()->updateOrCreate(
            ['channel' => $channel, 'campaign_id' => $cid],
            [
                'campaign_name' => $campaignName !== '' ? $campaignName : ($existing?->campaign_name),
                'paused_reason' => $use,
                'paused_at' => $existing?->paused_at ?? now(),
                'reactivated_at' => now(),
            ]
        );
    }
}
