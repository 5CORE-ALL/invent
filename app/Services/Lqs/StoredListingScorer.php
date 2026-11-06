<?php

namespace App\Services\Lqs;

/**
 * Shared helpers for the rule-based 0-100 listing scorers that read already-synced listing data
 * (Newegg, PLS, Business 5 Core B2B). Each subclass defines its own checks and weights.
 */
abstract class StoredListingScorer
{
    /** Brand every 5 Core listing title should carry. */
    protected const BRAND = '5 Core';

    protected const PROMO_WORDS = [
        'free shipping', 'best price', 'best seller', 'bestseller', 'hot sale', 'on sale', 'wow', 'must see',
        'cheap', 'lowest price', 'limited time', 'buy now', '100%', 'guaranteed', '!!!',
    ];

    /**
     * @param  array<string, mixed>  $content
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    abstract public function score(array $content): array;

    /** Title check: length 50%, brand 15%, no promo/ALL CAPS 20%, no keyword stuffing 15% of $max. */
    protected function titleCheck(string $title, int $max, int $ideal, string $noun = 'Title'): array
    {
        $len = mb_strlen($title);
        $notes = [];
        $pts = 0.0;
        if ($len === 0) {
            $notes[] = "{$noun} is missing.";

            return $this->check('Title', 0, $max, $notes);
        }

        $ratio = $len >= $ideal ? 1.0 : ($len >= $ideal * 0.75 ? 0.8 : ($len >= $ideal * 0.5 ? 0.5 : ($len >= $ideal * 0.25 ? 0.3 : 0.1)));
        $pts += $max * 0.5 * $ratio;
        if ($len < $ideal) {
            $notes[] = "{$noun} is only {$len} characters. Aim for {$ideal}+: product type, key attribute (size, color, pack) and what shoppers search for.";
        }

        if (stripos($title, self::BRAND) !== false || stripos($title, '5core') !== false) {
            $pts += $max * 0.15;
        } else {
            $notes[] = "{$noun} does not carry the brand (".self::BRAND.').';
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $title) ?? '';
        $upperRatio = strlen($letters) > 0 ? strlen(preg_replace('/[^A-Z]/', '', $letters) ?? '') / strlen($letters) : 0;
        $lower = mb_strtolower($title);
        $promoHit = null;
        foreach (self::PROMO_WORDS as $word) {
            if (str_contains($lower, $word)) {
                $promoHit = $word;
                break;
            }
        }
        if ($upperRatio > 0.7 && strlen($letters) > 12) {
            $notes[] = "{$noun} is mostly ALL CAPS. Use normal title case.";
        } elseif ($promoHit !== null) {
            $notes[] = "{$noun} contains promotional filler (\"{$promoHit}\"). Marketplaces reject price/promo claims in titles.";
        } else {
            $pts += $max * 0.2;
        }

        $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
        $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
        $stuffed = array_filter($counts, fn ($c) => $c >= 3);
        if ($stuffed === []) {
            $pts += $max * 0.15;
        } else {
            $notes[] = "{$noun} repeats \"".array_key_first($stuffed).'" several times. Use each keyword once.';
        }

        return $this->check('Title', $pts, $max, $notes);
    }

    /** Text-length check: full marks at $ideal characters. */
    protected function textCheck(string $name, int $chars, int $max, int $ideal, string $emptyNote, string $shortNote): array
    {
        $ratio = match (true) {
            $chars >= $ideal => 1.0,
            $chars >= $ideal * 0.67 => 0.8,
            $chars >= $ideal * 0.42 => 0.55,
            $chars >= $ideal * 0.17 => 0.3,
            $chars > 0 => 0.1,
            default => 0.0,
        };
        $notes = [];
        if ($chars < $ideal) {
            $notes[] = $chars === 0 ? $emptyNote : sprintf($shortNote, $chars);
        }

        return $this->check($name, $max * $ratio, $max, $notes);
    }

    /** Image-count check: linear up to $ideal images. */
    protected function imageCheck(int $count, int $max, int $ideal): array
    {
        $notes = [];
        if ($count < $ideal) {
            $notes[] = "Only {$count} image(s). Use {$ideal}+ (main on white, angles, in-use, scale, what's included).";
        }

        return $this->check('Images', $max * min($count, $ideal) / $ideal, $max, $notes);
    }

    /** Plain text (tags, entities, whitespace removed) of an HTML fragment. */
    protected function plainText(string $html): string
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $text) ?? $text;
        $text = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|td|tr)>#i', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<string>
     */
    protected function cleanUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = is_string($url) ? trim($url) : '';
            if (preg_match('#^(https?:)?//#i', $url)) {
                $out[$url] = true;
            }
        }

        return array_keys($out);
    }

    /** @param  list<string>  $notes */
    protected function check(string $name, float|int $points, int $max, array $notes): array
    {
        return ['name' => $name, 'points' => round((float) $points, 1), 'max' => $max, 'notes' => $notes];
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return array{score: float, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    protected function finish(array $checks): array
    {
        $total = round(array_sum(array_column($checks, 'points')), 1);

        return [
            'score' => max(0.0, min(100.0, $total)),
            'checks' => $checks,
            'findings' => $this->findings($total, $checks),
            'suggestions' => $this->suggestions($checks),
        ];
    }

    /** @param  list<array<string, mixed>>  $checks */
    private function findings(float $total, array $checks): string
    {
        $lines = ["API score {$total}/100."];
        foreach ($checks as $c) {
            $lines[] = sprintf('%s: %s/%d%s', $c['name'], $c['points'], $c['max'], $c['notes'] ? ' – '.implode(' ', $c['notes']) : ' – OK.');
        }

        return implode("\n", $lines);
    }

    /** @param  list<array<string, mixed>>  $checks */
    private function suggestions(array $checks): string
    {
        $gaps = array_filter($checks, fn ($c) => $c['points'] < $c['max'] && $c['notes']);
        usort($gaps, fn ($a, $b) => ($b['max'] - $b['points']) <=> ($a['max'] - $a['points']));

        $out = [];
        $i = 1;
        foreach ($gaps as $c) {
            foreach ($c['notes'] as $note) {
                $out[] = $i++.'. ['.$c['name'].'] '.$note;
            }
        }

        return $out === [] ? 'No gaps found by the API checks.' : implode("\n", $out);
    }
}
