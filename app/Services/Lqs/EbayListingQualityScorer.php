<?php

namespace App\Services\Lqs;

/**
 * Rule-based eBay Listing Quality Score (0-100) from a live Trading API GetItem "Item" array.
 *
 * eBay has no public "listing quality score" endpoint, so this scores the things eBay's own
 * guidance and search ranking reward and that the API exposes. It is deterministic (same listing,
 * same score), free to run in bulk, and returns a per-check breakdown so editors know what to fix.
 *
 * Weights (total 100):
 *   Title 20 · Photos 20 · Item specifics 25 · Description 15 · Condition 5 · Shipping & returns 10 · Price 5
 */
class EbayListingQualityScorer
{
    private const PROMO_WORDS = [
        'free shipping', 'best price', 'best seller', 'bestseller', 'hot sale', 'on sale', 'look', 'l@@k', 'wow',
        'must see', 'cheap', 'lowest price', 'limited time', 'buy now', '100%', 'guaranteed', 'new!!', '!!!',
    ];

    /**
     * @param  array<string, mixed>  $item  GetItem response "Item" node (xml -> json array)
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function score(array $item): array
    {
        $facts = $this->facts($item);
        $checks = [];

        // ── Title (20) ────────────────────────────────────────────
        $title = $facts['title'];
        $len = mb_strlen($title);
        $pts = 0.0;
        $notes = [];
        if ($len === 0) {
            $notes[] = 'Title is missing.';
        } else {
            // 12 pts for using the 80-char limit well (eBay shows/searches up to 80).
            $pts += match (true) {
                $len >= 70 => 12,
                $len >= 60 => 10,
                $len >= 45 => 7,
                $len >= 30 => 4,
                default => 2,
            };
            if ($len < 60) {
                $notes[] = "Title is only {$len}/80 characters. Add brand, model, size/color and 2-3 buyer search keywords.";
            }
            // 4 pts: not shouting / no promotional filler.
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
                $notes[] = 'Title is mostly ALL CAPS. Use normal title case.';
            } elseif ($promoHit !== null) {
                $notes[] = "Title contains promotional filler (\"{$promoHit}\"). Remove it; eBay discourages it and it wastes characters.";
            } else {
                $pts += 4;
            }
            // 4 pts: no keyword stuffing (same word 3+ times).
            $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
            $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
            $stuffed = array_filter($counts, fn ($c) => $c >= 3);
            if ($stuffed === []) {
                $pts += 4;
            } else {
                $notes[] = 'Title repeats "'.array_key_first($stuffed).'" several times. Use each keyword once.';
            }
        }
        $checks[] = $this->check('Title', $pts, 20, $notes);

        // ── Photos (20) ───────────────────────────────────────────
        $photos = $facts['photo_count'];
        $pts = match (true) {
            $photos >= 8 => 20,
            $photos >= 6 => 17,
            $photos >= 4 => 14,
            $photos >= 2 => 9,
            $photos === 1 => 4,
            default => 0,
        };
        $notes = [];
        if ($photos < 8) {
            $notes[] = $photos === 0
                ? 'Listing has no photos.'
                : "Only {$photos} photo(s). Aim for 8+ (front, back, sides, detail, in-use, scale, what's included).";
        }
        $checks[] = $this->check('Photos', $pts, 20, $notes);

        // ── Item specifics (25) ───────────────────────────────────
        $names = array_map('mb_strtolower', $facts['specifics_names']);
        $specCount = count($names);
        $pts = min(12.0, round($specCount * 1.5, 1)); // 8+ specifics = full 12
        $notes = [];
        if ($specCount < 8) {
            $notes[] = "Only {$specCount} item specific(s) filled. Fill every field eBay shows for this category (aim for 8+); buyers filter on them.";
        }
        $hasAny = fn (array $keys) => (bool) array_intersect($names, $keys);
        if ($hasAny(['brand'])) {
            $pts += 5;
        } else {
            $notes[] = 'Item specific "Brand" is missing.';
        }
        if ($hasAny(['mpn', 'manufacturer part number', 'model', 'model number'])) {
            $pts += 4;
        } else {
            $notes[] = 'Item specific "MPN / Model" is missing.';
        }
        if ($facts['has_product_identifier'] || $hasAny(['upc', 'ean', 'isbn', 'gtin'])) {
            $pts += 4;
        } else {
            $notes[] = 'No UPC/EAN/GTIN. Listings with product identifiers rank and match catalog products better.';
        }
        $checks[] = $this->check('Item specifics', $pts, 25, $notes);

        // ── Description (15) ──────────────────────────────────────
        $descLen = $facts['description_chars'];
        $pts = match (true) {
            $descLen >= 500 => 15,
            $descLen >= 300 => 12,
            $descLen >= 150 => 8,
            $descLen >= 50 => 4,
            default => 0,
        };
        $notes = [];
        if ($descLen < 300) {
            $notes[] = $descLen === 0
                ? 'Description is empty.'
                : "Description is only {$descLen} characters of text. Add benefits, dimensions, compatibility, what's included and condition details (300+).";
        }
        $checks[] = $this->check('Description', $pts, 15, $notes);

        // ── Condition (5) ─────────────────────────────────────────
        $pts = $facts['condition'] !== null ? 5 : 0;
        $checks[] = $this->check('Condition', $pts, 5, $pts ? [] : ['Item condition is not set.']);

        // ── Shipping & returns (10) ───────────────────────────────
        $pts = 0;
        $notes = [];
        if ($facts['returns_accepted']) {
            $pts += 5;
        } else {
            $notes[] = 'Returns are not accepted. Accepting returns improves search ranking and buyer confidence.';
        }
        if ($facts['has_shipping_service']) {
            $pts += 3;
        } else {
            $notes[] = 'No shipping service found on the listing.';
        }
        if ($facts['free_shipping']) {
            $pts += 2;
        } else {
            $notes[] = 'Free shipping is not offered. It is a ranking and conversion signal on eBay.';
        }
        $checks[] = $this->check('Shipping & returns', $pts, 10, $notes);

        // ── Price (5) ─────────────────────────────────────────────
        $pts = $facts['price'] > 0 ? 5 : 0;
        $checks[] = $this->check('Price', $pts, 5, $pts ? [] : ['Listing has no valid price.']);

        $total = round(array_sum(array_column($checks, 'points')), 1);

        return [
            'score' => max(0.0, min(100.0, $total)),
            'facts' => $facts,
            'checks' => $checks,
            'findings' => $this->findings($total, $checks),
            'suggestions' => $this->suggestions($checks),
        ];
    }

    /**
     * Normalised facts pulled from the GetItem node (also handed to the AI audit as grounding).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function facts(array $item): array
    {
        $pictures = $this->asList($item['PictureDetails']['PictureURL'] ?? []);
        $pictures = array_values(array_filter(array_map([$this, 'text'], $pictures)));

        $specifics = [];
        foreach ($this->asList($item['ItemSpecifics']['NameValueList'] ?? []) as $nv) {
            if (! is_array($nv)) {
                continue;
            }
            $name = trim($this->text($nv['Name'] ?? ''));
            $values = array_filter(array_map([$this, 'text'], $this->asList($nv['Value'] ?? [])));
            if ($name !== '' && $values !== []) {
                $specifics[$name] = implode(', ', $values);
            }
        }

        $description = html_entity_decode($this->text($item['Description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $description) ?? $description;
        $descText = trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? '');

        $shipOptions = $this->asList($item['ShippingDetails']['ShippingServiceOptions'] ?? []);
        $freeShipping = false;
        foreach ($shipOptions as $opt) {
            if (is_array($opt) && (
                strtolower($this->text($opt['FreeShipping'] ?? '')) === 'true'
                || (isset($opt['ShippingServiceCost']) && (float) $this->text($opt['ShippingServiceCost']) === 0.0)
            )) {
                $freeShipping = true;
            }
        }

        $identifiers = $item['ProductListingDetails'] ?? [];
        $hasIdentifier = false;
        foreach (['UPC', 'EAN', 'ISBN', 'BrandMPN'] as $key) {
            $v = $identifiers[$key] ?? null;
            if ($v !== null && $this->text($v) !== '' && strtolower($this->text($v)) !== 'does not apply') {
                $hasIdentifier = true;
            }
        }

        return [
            'item_id' => $this->text($item['ItemID'] ?? ''),
            'title' => trim($this->text($item['Title'] ?? '')),
            'photo_count' => count($pictures),
            'specifics_count' => count($specifics),
            'specifics_names' => array_keys($specifics),
            'specifics' => $specifics,
            'description_chars' => mb_strlen($descText),
            'condition' => ($c = $this->text($item['ConditionDisplayName'] ?? ($item['ConditionID'] ?? ''))) !== '' ? $c : null,
            'returns_accepted' => strtolower($this->text($item['ReturnPolicy']['ReturnsAcceptedOption'] ?? '')) === 'returnsaccepted',
            'has_shipping_service' => $shipOptions !== [],
            'free_shipping' => $freeShipping,
            'has_product_identifier' => $hasIdentifier,
            'price' => (float) $this->text($item['SellingStatus']['CurrentPrice'] ?? ($item['StartPrice'] ?? 0)),
            'listing_status' => $this->text($item['SellingStatus']['ListingStatus'] ?? ''),
        ];
    }

    /** @param  list<string>  $notes */
    private function check(string $name, float|int $points, int $max, array $notes): array
    {
        return [
            'name' => $name,
            'points' => round((float) $points, 1),
            'max' => $max,
            'notes' => $notes,
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
        // Biggest point gaps first, so editors fix the most valuable thing first.
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

    /** XML->JSON turns a single child into an object and repeated children into a list. */
    private function asList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }
        if (! is_array($value)) {
            return [$value];
        }

        return array_keys($value) === range(0, count($value) - 1) ? $value : [$value];
    }

    /** Scalars may arrive as ['@attributes' => [...], 0 => 'value'] when the XML node had attributes. */
    private function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? ($value['value'] ?? '');
            if (is_array($value)) {
                return '';
            }
        }

        return trim((string) $value);
    }
}
