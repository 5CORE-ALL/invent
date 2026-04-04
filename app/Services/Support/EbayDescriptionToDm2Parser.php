<?php

namespace App\Services\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Best-effort mapping of eBay listing description HTML into DM2 fields.
 * Prefers Description Master 2.0 markup; falls back to generic extraction.
 */
final class EbayDescriptionToDm2Parser
{
    /**
     * @return array{
     *   description_v2_bullets: string,
     *   description_v2_description: string,
     *   description_v2_images: list<string>,
     *   description_v2_features: list<array{title: string, body: string}>,
     *   description_v2_specifications: list<array{key: string, value: string}>,
     *   description_v2_package: string,
     *   description_v2_brand: string,
     * }
     */
    public static function parse(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return self::empty();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $enc = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$enc);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $root = $dom->getElementsByTagName('body')->item(0);
        if (! $root instanceof DOMElement) {
            return self::fallbackPlain($html);
        }

        if ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' product-description-v2 ')]", $root)->length > 0) {
            return self::parseDm2Layout($xpath, $root);
        }

        return self::fallbackFromDom($xpath, $root, $html);
    }

    /**
     * @return array<string, mixed>
     */
    private static function empty(): array
    {
        return [
            'description_v2_bullets' => '',
            'description_v2_description' => '',
            'description_v2_images' => [],
            'description_v2_features' => array_fill(0, 4, ['title' => '', 'body' => '']),
            'description_v2_specifications' => [],
            'description_v2_package' => '',
            'description_v2_brand' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseDm2Layout(DOMXPath $xpath, DOMElement $root): array
    {
        $out = self::empty();

        $lis = $xpath->query("//ul[contains(@class,'bullet-list')]//li", $root);
        $bullets = [];
        if ($lis) {
            foreach ($lis as $li) {
                $t = self::innerText($li);
                if ($t !== '') {
                    $bullets[] = $t;
                }
            }
        }
        $out['description_v2_bullets'] = implode("\n", array_slice($bullets, 0, 5));

        $imgs = $xpath->query("//*[contains(@class,'image-gallery')]//img[@src]", $root);
        $images = [];
        if ($imgs) {
            foreach ($imgs as $img) {
                if ($img instanceof DOMElement) {
                    $src = trim((string) $img->getAttribute('src'));
                    if ($src !== '') {
                        $images[] = $src;
                    }
                }
            }
        }
        $out['description_v2_images'] = array_slice(array_values(array_unique($images)), 0, 12);

        $desc = $xpath->query("//div[contains(@class,'section')][h3[contains(.,'Product Description')]]/p[1]", $root);
        if ($desc && $desc->length > 0) {
            $out['description_v2_description'] = self::innerText($desc->item(0));
        }

        $featLis = $xpath->query("//ul[contains(@class,'features-list')]//li", $root);
        $fi = 0;
        if ($featLis) {
            foreach ($featLis as $li) {
                if ($fi >= 4) {
                    break;
                }
                $raw = self::innerText($li);
                if (preg_match('/^(.+?)\s*[-–—]\s*(.+)$/u', $raw, $m)) {
                    $out['description_v2_features'][$fi] = ['title' => trim($m[1]), 'body' => trim($m[2])];
                } else {
                    $out['description_v2_features'][$fi] = ['title' => '', 'body' => $raw];
                }
                $fi++;
            }
        }

        $rows = $xpath->query("//table[contains(@class,'spec-table')]//tr", $root);
        $specs = [];
        if ($rows) {
            foreach ($rows as $tr) {
                $cells = $xpath->query('.//td', $tr);
                if (! $cells || $cells->length < 2) {
                    continue;
                }
                $k = self::innerText($cells->item(0));
                $v = self::innerText($cells->item(1));
                if ($k !== '' || $v !== '') {
                    $specs[] = ['key' => $k, 'value' => $v];
                }
            }
        }
        $out['description_v2_specifications'] = $specs;

        $pkg = $xpath->query("//ul[contains(@class,'package-list')]//li", $root);
        $pkgLines = [];
        if ($pkg) {
            foreach ($pkg as $li) {
                $t = self::innerText($li);
                if ($t !== '') {
                    $pkgLines[] = $t;
                }
            }
        }
        $out['description_v2_package'] = implode("\n", $pkgLines);

        $brand = $xpath->query("//div[contains(@class,'section')][.//h3[contains(.,'About Brand')]]//p", $root);
        if ($brand && $brand->length > 0) {
            $out['description_v2_brand'] = self::innerText($brand->item(0));
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallbackFromDom(DOMXPath $xpath, DOMElement $root, string $rawHtml): array
    {
        $out = self::empty();
        $imgs = $xpath->query('//img[@src]', $root);
        $images = [];
        if ($imgs) {
            foreach ($imgs as $img) {
                if ($img instanceof DOMElement) {
                    $src = trim((string) $img->getAttribute('src'));
                    if ($src !== '' && preg_match('#^https?://#i', $src)) {
                        $images[] = $src;
                    }
                }
            }
        }
        $out['description_v2_images'] = array_slice(array_values(array_unique($images)), 0, 12);

        $lis = $xpath->query('//ul/li', $root);
        $lines = [];
        if ($lis && $lis->length > 0) {
            foreach ($lis as $li) {
                $t = self::innerText($li);
                if ($t !== '' && count($lines) < 5) {
                    $lines[] = $t;
                }
            }
        }
        $out['description_v2_bullets'] = implode("\n", $lines);

        $plain = trim(html_entity_decode(strip_tags($rawHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $out['description_v2_description'] = mb_substr($plain, 0, 8000);

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallbackPlain(string $html): array
    {
        $out = self::empty();
        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $out['description_v2_description'] = mb_substr($plain, 0, 8000);

        return $out;
    }

    private static function innerText(?\DOMNode $node): string
    {
        if (! $node) {
            return '';
        }
        $t = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');

        return $t;
    }
}
