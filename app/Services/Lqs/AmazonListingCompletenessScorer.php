<?php

namespace App\Services\Lqs;

/**
 * Amazon's 2024 Listing Completeness Rating for North America consumer goods.
 *
 * Points follow Amazon's published listing-rating table (browse node, search
 * terms, A+ content, brand, description, bullets, key attributes, title,
 * images, and main-image zoom). 80 or above is Amazon's acceptable grade.
 */
class AmazonListingCompletenessScorer
{
    /**
     * @param  array{
     *     title?: ?string,
     *     brand?: ?string,
     *     search_terms?: ?string,
     *     description?: ?string,
     *     aplus?: ?string,
     *     bullets?: mixed,
     *     browse_node?: ?string,
     *     images?: list<string>,
     *     attributes?: array<string, mixed>
     * }  $listing
     * @return array{score: int, grade: string, missing: list<string>}
     */
    public function score(array $listing): array
    {
        $title = trim((string) ($listing['title'] ?? ''));
        $brand = trim((string) ($listing['brand'] ?? ''));
        $bullets = $this->bullets($listing['bullets'] ?? null);
        $images = $this->cleanUrls($listing['images'] ?? []);
        $attributes = is_array($listing['attributes'] ?? null) ? $listing['attributes'] : [];
        $filledAttributes = 0;
        foreach ($attributes as $value) {
            if ($this->present($value)) {
                $filledAttributes++;
            }
        }
        $attributeTotal = max(1, count($attributes));
        $attributePoints = ($filledAttributes / $attributeTotal) * 25;
        $bulletCount = count($bullets);
        $imageCount = count($images);
        $titleLength = mb_strlen($title);

        $checks = [
            ['Browse node', $this->present($listing['browse_node'] ?? null), 10],
            ['Search terms', $this->present($listing['search_terms'] ?? null), 5],
            ['A+ content', $this->present($listing['aplus'] ?? null), 12.5],
            ['Brand', $this->present($brand), 5],
            ['Description', $this->present($listing['description'] ?? null), 5],
            ['Bullet point', $bulletCount >= 1, 5],
            ['3+ bullet points', $bulletCount >= 3, 2.5],
            ['Key attributes', $filledAttributes >= $attributeTotal, $attributePoints, $filledAttributes < $attributeTotal],
            ['Title length (10–200)', $titleLength >= 10 && $titleLength <= 200, 5],
            ['Title starts with brand', $this->titleStartsWithBrand($title, $brand), 5],
            ['Image set', $imageCount >= 4, 5],
            ['4+ images', $imageCount >= 4, 5],
            ['Main image zoom', $this->mainImageZooms($images), 10],
        ];

        $earned = 0.0;
        $missing = [];
        foreach ($checks as $check) {
            [$label, $passed, $points] = $check;
            $partialCredit = $check[3] ?? false;
            if ($partialCredit) {
                $earned += (float) $points;
                $missing[] = 'Key attributes ('.$filledAttributes.'/'.$attributeTotal.')';
            } elseif ($passed) {
                $earned += (float) $points;
            } else {
                $missing[] = $label;
            }
        }

        $score = (int) round($earned);

        return [
            'score' => max(0, min(100, $score)),
            'grade' => $this->grade($score),
            'missing' => $missing,
        ];
    }

    /**
     * @param  object|array<string, mixed>  $listing
     * @return array{score: int, grade: string, missing: list<string>}
     */
    public function scoreStored(object|array $listing, ?string $aplus = null): array
    {
        $get = static function (string $key) use ($listing): mixed {
            if (is_array($listing)) {
                return $listing[$key] ?? null;
            }

            return $listing->{$key} ?? null;
        };

        $material = $get('material');
        if (! $this->present($material)) {
            $material = $get('style');
        }
        $included = $get('included_components');
        if (! $this->present($included)) {
            $included = $get('number_of_items');
        }
        $model = $get('manufacturer');
        if (! $this->present($model)) {
            $model = $get('model_number');
        }
        if (! $this->present($model)) {
            $model = $get('model_name');
        }
        $browse = $get('item_type_keyword');
        if (! $this->present($browse)) {
            $browse = $get('product_type');
        }

        return $this->score([
            'title' => $get('item_name'),
            'brand' => $get('brand'),
            'search_terms' => $get('generic_keyword'),
            'description' => $get('product_description'),
            'aplus' => $aplus,
            'bullets' => $get('bullet_point'),
            'browse_node' => is_string($browse) ? $browse : null,
            'images' => $this->imageUrls($get('raw_data'), $get('thumbnail_image')),
            'attributes' => [
                'color' => $get('color'),
                'material' => $material,
                'size' => $get('size'),
                'dimensions' => $get('item_dimensions'),
                'included' => $included,
                'model' => $model,
            ],
        ]);
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    public function imageUrls(mixed $raw, mixed $thumbnail = null): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        $indexed = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key) || ! is_string($value) || trim($value) === '') {
                continue;
            }
            if (preg_match('/^image-url(?:-(\d+))?$/i', $key, $match)) {
                $indexed[(int) ($match[1] ?? 0)] = trim($value);
            }
        }
        ksort($indexed);
        $urls = array_values($indexed);

        $thumb = is_string($thumbnail) ? trim($thumbnail) : '';
        if ($urls === [] && $thumb !== '') {
            $urls[] = $thumb;
        }

        return $this->cleanUrls($urls);
    }

    /**
     * @return list<string>
     */
    private function bullets(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split("/\r\n|\n|\|/", $value) ?: [];
            }
        }
        if (! is_array($value)) {
            return [];
        }

        $bullets = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['value'] ?? $item['text'] ?? '';
            }
            $item = trim(strip_tags((string) $item));
            if ($item !== '') {
                $bullets[] = $item;
            }
        }

        return $bullets;
    }

    /**
     * @param  list<string>  $images
     */
    private function mainImageZooms(array $images): bool
    {
        $main = $images[0] ?? '';
        if ($main === '') {
            return false;
        }
        if (stripos($main, 'THUMB') !== false) {
            return false;
        }
        if (preg_match('/_(?:SL|SS|SX|SY|UL|AC_US)(\d+)_/i', $main, $match) && (int) $match[1] < 500) {
            return false;
        }

        return true;
    }

    private function titleStartsWithBrand(string $title, string $brand): bool
    {
        if (! $this->present($title) || ! $this->present($brand)) {
            return false;
        }

        return mb_stripos($title, $brand) === 0;
    }

    private function present(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->present($item)) {
                    return true;
                }
            }

            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value != 0.0;
        }
        $text = trim(strip_tags((string) $value));

        return $text !== '' && ! in_array(strtolower($text), ['null', 'n/a', 'na', '-', 'none'], true);
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function cleanUrls(array $urls): array
    {
        $clean = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url !== '' && ! in_array($url, $clean, true)) {
                $clean[] = $url;
            }
        }

        return $clean;
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            $score >= 10 => 'E',
            default => 'F',
        };
    }
}
