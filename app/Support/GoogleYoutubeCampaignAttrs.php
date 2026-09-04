<?php

namespace App\Support;

use App\Models\GoogleYoutubeAttrOption;
use App\Models\GoogleYoutubeCampaignAttr;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube grid Category / Audience / Landing values and dropdown options.
 */
final class GoogleYoutubeCampaignAttrs
{
    public const FIELDS = [
        'yt_category' => 'category',
        'yt_audience' => 'audience',
        'yt_landing' => 'landing',
    ];

    public const CATEGORY_OPTIONS = ['B2B', 'B2C'];

    public const AUDIENCE_DEFAULTS = [
        'shops',
        'music schools',
        'drumers',
        'Guitarist',
        'DJ',
    ];

    /**
     * @return array<string, array{category:?string, audience:?string, landing:?string}>
     */
    public static function mapByCampaignId(): array
    {
        if (! Schema::hasTable('google_youtube_campaign_attrs')) {
            return [];
        }

        $map = [];
        foreach (GoogleYoutubeCampaignAttr::query()->get(['campaign_id', 'category', 'audience', 'landing']) as $row) {
            $map[(string) $row->campaign_id] = [
                'category' => $row->category,
                'audience' => $row->audience,
                'landing' => $row->landing,
            ];
        }

        return $map;
    }

    /**
     * @return array{category: list<string>, audience: list<string>, landing: list<string>}
     */
    public static function options(): array
    {
        $audience = self::AUDIENCE_DEFAULTS;
        $landing = [];
        if (Schema::hasTable('google_youtube_attr_options')) {
            foreach (GoogleYoutubeAttrOption::query()->orderBy('id')->get(['kind', 'label']) as $row) {
                $label = trim((string) $row->label);
                if ($label === '') {
                    continue;
                }
                if ($row->kind === 'audience') {
                    $audience[] = $label;
                } elseif ($row->kind === 'landing') {
                    $landing[] = $label;
                }
            }
        }
        if (Schema::hasTable('google_youtube_campaign_attrs')) {
            foreach (GoogleYoutubeCampaignAttr::query()->whereNotNull('audience')->pluck('audience') as $label) {
                $audience[] = (string) $label;
            }
            foreach (GoogleYoutubeCampaignAttr::query()->whereNotNull('landing')->pluck('landing') as $label) {
                $landing[] = (string) $label;
            }
        }

        return [
            'category' => self::CATEGORY_OPTIONS,
            'audience' => self::uniqueLabels($audience),
            'landing' => self::uniqueLabels($landing),
        ];
    }

    /**
     * @return array{category:?string, audience:?string, landing:?string, options: array{category: list<string>, audience: list<string>, landing: list<string>}}
     */
    public static function saveValue(string $campaignId, string $field, string $value): array
    {
        if (! Schema::hasTable('google_youtube_campaign_attrs')) {
            throw new \RuntimeException('Table google_youtube_campaign_attrs does not exist. Run migrations.');
        }
        $column = self::FIELDS[$field] ?? null;
        if ($column === null) {
            throw new \InvalidArgumentException('Unknown field.');
        }
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            throw new \InvalidArgumentException('Missing campaign_id.');
        }
        $value = trim($value);
        if ($value === '') {
            $value = null;
        } elseif ($column === 'category' && ! in_array($value, self::CATEGORY_OPTIONS, true)) {
            throw new \InvalidArgumentException('Category must be B2B or B2C.');
        } elseif (strlen($value) > 160) {
            throw new \InvalidArgumentException('Value is too long.');
        }

        $row = GoogleYoutubeCampaignAttr::query()->firstOrNew(['campaign_id' => $campaignId]);
        $row->{$column} = $value;
        $row->save();

        return [
            'category' => $row->category,
            'audience' => $row->audience,
            'landing' => $row->landing,
            'options' => self::options(),
        ];
    }

    /**
     * @return array{kind: string, label: string, options: array{category: list<string>, audience: list<string>, landing: list<string>}}
     */
    public static function addOption(string $kind, string $label): array
    {
        if (! in_array($kind, ['audience', 'landing'], true)) {
            throw new \InvalidArgumentException('Can only add Audience or Landing options.');
        }
        $label = trim($label);
        if ($label === '' || strcasecmp($label, 'add') === 0 || $label === '+ Add…') {
            throw new \InvalidArgumentException('Enter an option name.');
        }
        if (strlen($label) > 160) {
            throw new \InvalidArgumentException('Option is too long.');
        }
        if (! Schema::hasTable('google_youtube_attr_options')) {
            throw new \RuntimeException('Table google_youtube_attr_options does not exist. Run migrations.');
        }
        GoogleYoutubeAttrOption::query()->firstOrCreate([
            'kind' => $kind,
            'label' => $label,
        ]);

        return [
            'kind' => $kind,
            'label' => $label,
            'options' => self::options(),
        ];
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private static function uniqueLabels(array $labels): array
    {
        $seen = [];
        $out = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $key = strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $label;
        }

        return $out;
    }
}
