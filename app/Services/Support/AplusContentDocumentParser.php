<?php

namespace App\Services\Support;

/**
 * Maps Amazon A+ Content API ContentDocument (contentModuleList) to Description Master 2.0 fields.
 *
 * @see https://developer-docs.amazon.com/sp-api/docs/aplus-content-api-v2020-11-01-reference
 */
final class AplusContentDocumentParser
{
    /**
     * @param  array<string, mixed>  $getContentDocumentResponse  Full JSON from getContentDocument
     * @return array{
     *   description_v2_bullets: string,
     *   description_v2_description: string,
     *   description_v2_images: list<string>,
     *   description_v2_features: list<array{title: string, body: string}>,
     *   description_v2_specifications: list<array{key: string, value: string}>,
     *   description_v2_package: string,
     *   description_v2_brand: string,
     *   modules_seen: list<string>
     * }
     */
    public static function parseToDm2(array $getContentDocumentResponse): array
    {
        $doc = $getContentDocumentResponse['contentRecord']['contentDocument']
            ?? $getContentDocumentResponse['contentDocument']
            ?? null;
        if (! is_array($doc)) {
            return self::emptyDm2();
        }

        $modules = $doc['contentModuleList'] ?? [];
        if (! is_array($modules)) {
            return self::emptyDm2();
        }

        $bullets = [];
        $descParts = [];
        $images = [];
        $features = array_fill(0, 4, ['title' => '', 'body' => '']);
        $featureIdx = 0;
        $specs = [];
        $packageLines = [];
        $brandParts = [];
        $modulesSeen = [];

        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }
            $type = (string) ($module['contentModuleType'] ?? '');
            if ($type !== '') {
                $modulesSeen[] = $type;
            }

            switch ($type) {
                case 'STANDARD_TEXT':
                case 'STANDARD_HEADLINE':
                    self::absorbStandardText($module, $bullets, $descParts);
                    break;
                case 'STANDARD_PRODUCT_DESCRIPTION':
                    self::absorbProductDescription($module, $descParts);
                    break;
                case 'STANDARD_FOUR_IMAGE_TEXT':
                    self::absorbFourImageText($module, $features, $featureIdx, $images);
                    break;
                case 'STANDARD_IMAGE_SIDEBAR':
                    self::absorbImageSidebar($module, $images, $descParts);
                    break;
                case 'STANDARD_SINGLE_IMAGE_SPECS':
                    self::absorbSingleImageSpecs($module, $images, $bullets, $descParts);
                    break;
                case 'STANDARD_COMPARISON_TABLE':
                    self::absorbComparisonTable($module, $specs);
                    break;
                case 'STANDARD_TECH_SPECS':
                    self::absorbTechSpecs($module, $specs);
                    break;
                case 'STANDARD_COMPANY_LOGO':
                    self::absorbCompanyLogo($module, $brandParts, $images);
                    break;
                default:
                    self::absorbGenericModule($module, $bullets, $descParts, $images);
                    break;
            }
        }

        $images = array_values(array_slice(array_unique(array_filter(array_map('trim', $images))), 0, 12));

        return [
            'description_v2_bullets' => self::joinBullets($bullets),
            'description_v2_description' => trim(implode("\n\n", array_filter(array_map('trim', $descParts)))),
            'description_v2_images' => $images,
            'description_v2_features' => self::padFeatures($features),
            'description_v2_specifications' => $specs,
            'description_v2_package' => trim(implode("\n", array_filter(array_map('trim', $packageLines)))),
            'description_v2_brand' => trim(implode("\n\n", array_filter(array_map('trim', $brandParts)))),
            'modules_seen' => array_values(array_unique($modulesSeen)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyDm2(): array
    {
        return [
            'description_v2_bullets' => '',
            'description_v2_description' => '',
            'description_v2_images' => [],
            'description_v2_features' => array_fill(0, 4, ['title' => '', 'body' => '']),
            'description_v2_specifications' => [],
            'description_v2_package' => '',
            'description_v2_brand' => '',
            'modules_seen' => [],
        ];
    }

    /**
     * @param  list<string>  $bullets
     * @param  list<string>  $descParts
     */
    private static function absorbStandardText(array $module, array &$bullets, array &$descParts): void
    {
        $st = $module['standardText'] ?? $module['standardHeadline'] ?? null;
        if (! is_array($st)) {
            return;
        }
        $headline = self::textFromValueNode($st['headline'] ?? null);
        $body = self::textFromBodyBlock($st['body'] ?? null);
        if ($headline !== '' && $body !== '') {
            $bullets[] = $headline.' - '.$body;
        } elseif ($body !== '') {
            $descParts[] = $body;
        } elseif ($headline !== '') {
            $bullets[] = $headline;
        }
    }

    /**
     * @param  list<string>  $descParts
     */
    private static function absorbProductDescription(array $module, array &$descParts): void
    {
        $pd = $module['standardProductDescription'] ?? $module['standard_product_description'] ?? null;
        if (! is_array($pd)) {
            return;
        }
        $body = self::textFromBodyBlock($pd['body'] ?? $pd['text'] ?? null);
        if ($body !== '') {
            $descParts[] = $body;
        }
    }

    /**
     * @param  array<int, array{title: string, body: string}>  $features
     */
    private static function absorbFourImageText(array $module, array &$features, int &$featureIdx, array &$images): void
    {
        $b = $module['standardFourImageText'] ?? null;
        if (! is_array($b)) {
            return;
        }
        $blocks = $b['blocks'] ?? $b['blockList'] ?? [];
        if (! is_array($blocks)) {
            return;
        }
        foreach ($blocks as $block) {
            if (! is_array($block) || $featureIdx >= 4) {
                break;
            }
            $title = self::textFromValueNode($block['headline'] ?? $block['title'] ?? null);
            $text = self::textFromBodyBlock($block['body'] ?? $block['text'] ?? null);
            $img = self::firstImageUrl($block);
            if ($img !== '') {
                $images[] = $img;
            }
            $features[$featureIdx] = ['title' => $title, 'body' => $text];
            $featureIdx++;
        }
    }

    /**
     * @param  list<string>  $images
     * @param  list<string>  $descParts
     */
    private static function absorbImageSidebar(array $module, array &$images, array &$descParts): void
    {
        $b = $module['standardImageSidebar'] ?? null;
        if (! is_array($b)) {
            return;
        }
        $img = self::firstImageUrl($b);
        if ($img !== '') {
            $images[] = $img;
        }
        $side = self::textFromBodyBlock($b['sidebarBody'] ?? $b['sidebar'] ?? $b['body'] ?? null);
        if ($side !== '') {
            $descParts[] = $side;
        }
    }

    /**
     * @param  list<string>  $bullets
     * @param  list<string>  $descParts
     */
    private static function absorbSingleImageSpecs(array $module, array &$images, array &$bullets, array &$descParts): void
    {
        $b = $module['standardSingleImageSpecs'] ?? null;
        if (! is_array($b)) {
            return;
        }
        $img = self::firstImageUrl($b);
        if ($img !== '') {
            $images[] = $img;
        }
        $list = $b['descriptionTextList'] ?? $b['specificationTextList'] ?? $b['bulletList'] ?? [];
        if (is_array($list)) {
            foreach ($list as $row) {
                $t = self::extractTextDeep($row);
                if ($t !== '') {
                    $bullets[] = $t;
                }
            }
        }
        $desc = self::textFromBodyBlock($b['description'] ?? null);
        if ($desc !== '') {
            $descParts[] = $desc;
        }
    }

    /**
     * @param  list<array{key: string, value: string}>  $specs
     */
    private static function absorbComparisonTable(array $module, array &$specs): void
    {
        $t = $module['standardComparisonTable'] ?? null;
        if (! is_array($t)) {
            return;
        }
        $rows = $t['productColumns'] ?? $t['metricRowLabels'] ?? $t['rows'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = self::extractTextDeep($row['metric'] ?? $row['label'] ?? $row['name'] ?? null);
                $val = self::extractTextDeep($row['value'] ?? $row['productValue'] ?? $row['values'] ?? null);
                if ($label !== '' || $val !== '') {
                    $specs[] = ['key' => $label, 'value' => $val];
                }
            }
        }
        $headers = $t['metricRowLabels'] ?? [];
        if (is_array($headers)) {
            foreach ($headers as $h) {
                if (! is_array($h)) {
                    continue;
                }
                $label = self::extractTextDeep($h['label'] ?? $h['metric'] ?? null);
                $vals = $h['productValues'] ?? [];
                $first = is_array($vals) && $vals !== [] ? self::extractTextDeep($vals[0] ?? null) : '';
                if ($label !== '' || $first !== '') {
                    $specs[] = ['key' => $label, 'value' => $first];
                }
            }
        }
    }

    /**
     * @param  list<array{key: string, value: string}>  $specs
     */
    private static function absorbTechSpecs(array $module, array &$specs): void
    {
        $b = $module['standardTechSpecs'] ?? null;
        if (! is_array($b)) {
            return;
        }
        $rows = $b['specificationList'] ?? $b['specifications'] ?? [];
        if (! is_array($rows)) {
            return;
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $k = self::extractTextDeep($row['label'] ?? $row['name'] ?? null);
            $v = self::extractTextDeep($row['value'] ?? null);
            if ($k !== '' || $v !== '') {
                $specs[] = ['key' => $k, 'value' => $v];
            }
        }
    }

    /**
     * @param  list<string>  $brandParts
     * @param  list<string>  $images
     */
    private static function absorbCompanyLogo(array $module, array &$brandParts, array &$images): void
    {
        $b = $module['standardCompanyLogo'] ?? null;
        if (! is_array($b)) {
            return;
        }
        $img = self::firstImageUrl($b);
        if ($img !== '') {
            $images[] = $img;
        }
        $txt = self::textFromBodyBlock($b['description'] ?? $b['body'] ?? null);
        if ($txt !== '') {
            $brandParts[] = $txt;
        }
        $company = self::textFromValueNode($b['companyName'] ?? $b['company'] ?? null);
        if ($company !== '') {
            $brandParts[] = $company;
        }
    }

    /**
     * @param  list<string>  $bullets
     * @param  list<string>  $descParts
     * @param  list<string>  $images
     */
    private static function absorbGenericModule(array $module, array &$bullets, array &$descParts, array &$images): void
    {
        $text = self::extractTextDeep($module);
        if ($text !== '' && mb_strlen($text) < 400) {
            $bullets[] = $text;
        } elseif ($text !== '') {
            $descParts[] = $text;
        }
        foreach (self::collectImageUrls($module) as $u) {
            $images[] = $u;
        }
    }

    private static function textFromValueNode(mixed $node): string
    {
        if ($node === null) {
            return '';
        }
        if (is_string($node)) {
            return trim($node);
        }
        if (is_array($node)) {
            return trim((string) ($node['value'] ?? $node['text'] ?? ''));

        }

        return '';
    }

    private static function textFromBodyBlock(mixed $body): string
    {
        if ($body === null) {
            return '';
        }
        if (is_string($body)) {
            return trim($body);
        }
        if (! is_array($body)) {
            return '';
        }
        $textList = $body['textList'] ?? $body['textListBlock'] ?? null;
        if (is_array($textList)) {
            $parts = [];
            foreach ($textList as $item) {
                $t = self::textFromValueNode($item);
                if ($t !== '') {
                    $parts[] = $t;
                }
            }

            return trim(implode("\n", $parts));
        }

        return trim((string) ($body['value'] ?? $body['text'] ?? ''));
    }

    private static function firstImageUrl(array $block): string
    {
        foreach (self::collectImageUrls($block) as $u) {
            if ($u !== '') {
                return $u;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private static function collectImageUrls(mixed $data): array
    {
        $out = [];
        if (! is_array($data)) {
            return $out;
        }
        foreach ($data as $k => $v) {
            $kl = strtolower((string) $k);
            if (in_array($kl, ['url', 'imageurl', 'image_url', 'src', 'uri'], true) && is_string($v) && preg_match('#^https?://#i', $v)) {
                $out[] = trim($v);
            }
            if (is_array($v)) {
                $out = array_merge($out, self::collectImageUrls($v));
            }
        }

        return $out;
    }

    private static function extractTextDeep(mixed $data): string
    {
        if (is_string($data)) {
            return trim($data);
        }
        if (! is_array($data)) {
            return '';
        }
        $parts = [];
        foreach ($data as $k => $v) {
            $kl = strtolower((string) $k);
            if (in_array($kl, ['value', 'text', 'label', 'headline', 'title', 'body'], true)) {
                if (is_string($v)) {
                    $parts[] = trim($v);
                } elseif (is_array($v)) {
                    $parts[] = self::extractTextDeep($v);
                }
            } elseif (is_array($v)) {
                $sub = self::extractTextDeep($v);
                if ($sub !== '') {
                    $parts[] = $sub;
                }
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /**
     * @param  list<string>  $bullets
     */
    private static function joinBullets(array $bullets): string
    {
        $lines = array_values(array_filter(array_map('trim', $bullets), fn ($b) => $b !== ''));

        return implode("\n", array_slice($lines, 0, 5));
    }

    /**
     * @param  array<int, array{title: string, body: string}>  $features
     * @return list<array{title: string, body: string}>
     */
    private static function padFeatures(array $features): array
    {
        while (count($features) < 4) {
            $features[] = ['title' => '', 'body' => ''];
        }

        return array_slice($features, 0, 4);
    }
}
