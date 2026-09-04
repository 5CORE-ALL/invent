<?php

namespace App\Support;

use App\Models\GoogleYoutubeVideoAudit;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube grid video-audit checkpoints. Pass / Fail / N/A answers
 * surface why a campaign under-performs.
 */
final class GoogleYoutubeVideoAuditChecklist
{
    /**
     * @return list<array{key: string, label: string, help: string}>
     */
    public static function items(): array
    {
        return [
            [
                'key' => 'hook_3s',
                'label' => 'Hook in the first 3 seconds',
                'help' => 'Does the opening grab attention before a skip?',
            ],
            [
                'key' => 'product_visible',
                'label' => 'Product is clearly on screen',
                'help' => 'Viewer can tell what is being sold without guessing.',
            ],
            [
                'key' => 'value_prop',
                'label' => 'Benefit / value proposition is stated',
                'help' => 'Why should the viewer care? Spoken or on-screen.',
            ],
            [
                'key' => 'offer_price',
                'label' => 'Offer or price is shown when relevant',
                'help' => 'Promo, price, or reason to act now is visible.',
            ],
            [
                'key' => 'cta_clear',
                'label' => 'Call to action is spoken and/or on-screen',
                'help' => 'Shop, learn more, or visit is unmistakable.',
            ],
            [
                'key' => 'brand_recall',
                'label' => 'Brand or logo is visible',
                'help' => 'Viewer can remember who ran the ad.',
            ],
            [
                'key' => 'audio_quality',
                'label' => 'Audio / voiceover is clear',
                'help' => 'No muddy mix, clipped levels, or missing VO.',
            ],
            [
                'key' => 'length_fit',
                'label' => 'Length fits the format',
                'help' => 'Bumper / skippable / Shorts length is appropriate.',
            ],
            [
                'key' => 'mobile_safe',
                'label' => 'Text and product stay in a mobile-safe frame',
                'help' => 'Nothing important is cropped on a phone.',
            ],
            [
                'key' => 'thumbnail',
                'label' => 'Opening frame / thumbnail matches the ad',
                'help' => 'Not misleading; matches the product and offer.',
            ],
            [
                'key' => 'landing_match',
                'label' => 'Landing page matches the ad promise',
                'help' => 'Same product, offer, and intent after the click.',
            ],
            [
                'key' => 'targeting_fit',
                'label' => 'Audience / targeting matches the creative',
                'help' => 'The video is talking to the people it is served to.',
            ],
            [
                'key' => 'end_card',
                'label' => 'End card or final CTA is present',
                'help' => 'The last seconds push a clear next step.',
            ],
            [
                'key' => 'performance',
                'label' => 'Spend vs sales / ACOS is acceptable',
                'help' => 'If Spend LT and ACOS LT fail the pause rule, mark Fail.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $checks
     * @return array<string, string>
     */
    public static function normalizeChecks(array $checks): array
    {
        $allowed = ['pass', 'fail', 'na'];
        $out = [];
        foreach (self::items() as $item) {
            $raw = strtolower(trim((string) ($checks[$item['key']] ?? '')));
            $out[$item['key']] = in_array($raw, $allowed, true) ? $raw : '';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $checks
     */
    public static function failCount(array $checks): int
    {
        return (int) (self::tally($checks)['fail'] ?? 0);
    }

    /**
     * Pass rate among judged checks (pass + fail). N/A and blanks are ignored.
     *
     * @param  array<string, string>  $checks
     * @return array{pass:int, fail:int, na:int, blank:int, scored:int, pct:?int}
     */
    public static function tally(array $checks): array
    {
        $pass = 0;
        $fail = 0;
        $na = 0;
        $blank = 0;
        foreach (self::items() as $item) {
            $v = (string) ($checks[$item['key']] ?? '');
            if ($v === 'pass') {
                $pass++;
            } elseif ($v === 'fail') {
                $fail++;
            } elseif ($v === 'na') {
                $na++;
            } else {
                $blank++;
            }
        }
        $scored = $pass + $fail;

        return [
            'pass' => $pass,
            'fail' => $fail,
            'na' => $na,
            'blank' => $blank,
            'scored' => $scored,
            'pct' => $scored > 0 ? (int) round(($pass / $scored) * 100) : null,
        ];
    }

    /**
     * @return array<string, array{filled:bool, pct:?int, fail:int}>
     */
    public static function latestMetaByCampaignId(): array
    {
        if (! Schema::hasTable('google_youtube_video_audits')) {
            return [];
        }

        $latestIds = GoogleYoutubeVideoAudit::query()
            ->selectRaw('MAX(id) as max_id')
            ->groupBy('campaign_id')
            ->pluck('max_id');

        $map = [];
        foreach (
            GoogleYoutubeVideoAudit::query()
                ->whereIn('id', $latestIds)
                ->get(['campaign_id', 'checks', 'comments']) as $row
        ) {
            $checks = self::normalizeChecks(is_array($row->checks) ? $row->checks : []);
            $tally = self::tally($checks);
            $map[(string) $row->campaign_id] = [
                'filled' => self::isFilled($checks, $row->comments),
                'pct' => $tally['pct'],
                'fail' => $tally['fail'],
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $checks
     */
    public static function isFilled(array $checks, ?string $comments = null): bool
    {
        foreach ($checks as $v) {
            if ($v === 'pass' || $v === 'fail' || $v === 'na') {
                return true;
            }
        }

        return trim((string) $comments) !== '';
    }

    /**
     * @return array<string, bool> campaign_id => filled
     */
    public static function filledByCampaignId(): array
    {
        $out = [];
        foreach (self::latestMetaByCampaignId() as $cid => $meta) {
            if (! empty($meta['filled'])) {
                $out[$cid] = true;
            }
        }

        return $out;
    }
}
