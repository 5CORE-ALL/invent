<?php

namespace App\Services\Support;

/**
 * Builds Shopify/eBay-safe HTML for Description Master 2.0 (structured layout, optional sections omitted when empty).
 */
final class ProductDescriptionV2HtmlBuilder
{
    /**
     * @param  array<int, string>  $imageUrls
     * @param  array<int, array{title?: string, body?: string}>  $features
     * @param  array<int, array{key?: string, value?: string}>  $specs
     * @return array{html: string, spec_heading: string}
     */
    public static function build(
        array $bullets,
        array $imageUrls,
        string $productDescription,
        array $features,
        array $specs,
        string $packageIncludes,
        string $aboutBrand,
        string $specTableHeading = 'Specification',
    ): array {
        $bullets = array_values(array_filter(array_map('trim', $bullets), fn ($b) => $b !== ''));
        $imageUrls = array_slice(array_values(array_filter(array_map('trim', $imageUrls), fn ($u) => $u !== '')), 0, 12);

        $esc = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $blocks = [];

        if ($bullets !== []) {
            $bulletHtml = '';
            foreach ($bullets as $line) {
                if (preg_match('/^(.+?)\s*[-–—]\s*(.+)$/u', $line, $m)) {
                    $bulletHtml .= '<li><strong>'.$esc(trim($m[1])).'</strong> - '.$esc(trim($m[2])).'</li>';
                } else {
                    $bulletHtml .= '<li>'.$esc($line).'</li>';
                }
            }
            $blocks[] = <<<HTML
  <div class="section">
    <h3>About Item</h3>
    <ul class="bullet-list">
{$bulletHtml}
    </ul>
  </div>
HTML;
        }

        if ($imageUrls !== []) {
            $gallery = '';
            foreach ($imageUrls as $url) {
                $gallery .= '<img src="'.$esc($url).'" alt="" loading="lazy" />';
            }
            $blocks[] = <<<HTML
  <div class="image-gallery">
{$gallery}
  </div>
HTML;
        }

        $descTrim = trim($productDescription);
        if ($descTrim !== '') {
            $descP = '<p>'.nl2br($esc($descTrim)).'</p>';
            $blocks[] = "  <div class=\"section\">\n    <h3>Product Description</h3>\n    {$descP}\n  </div>";
        }

        $featureItems = '';
        foreach (array_slice($features, 0, 4) as $f) {
            $t = isset($f['title']) ? trim((string) $f['title']) : '';
            $b = isset($f['body']) ? trim((string) $f['body']) : '';
            if ($t === '' && $b === '') {
                continue;
            }
            if ($t !== '' && $b !== '') {
                $featureItems .= '<li><strong>'.$esc($t).'</strong> - '.$esc($b).'</li>';
            } elseif ($t !== '') {
                $featureItems .= '<li><strong>'.$esc($t).'</strong></li>';
            } else {
                $featureItems .= '<li>'.$esc($b).'</li>';
            }
        }
        if ($featureItems !== '') {
            $blocks[] = <<<HTML
  <div class="section">
    <h3>Features</h3>
    <ul class="features-list">
{$featureItems}
    </ul>
  </div>
HTML;
        }

        $specRows = '';
        foreach ($specs as $row) {
            $k = isset($row['key']) ? trim((string) $row['key']) : '';
            $v = isset($row['value']) ? trim((string) $row['value']) : '';
            if ($k === '' && $v === '') {
                continue;
            }
            $specRows .= '<tr><td>'.$esc($k).'</td><td>'.$esc($v).'</td></tr>';
        }
        if ($specRows !== '') {
            $h = $esc($specTableHeading);
            $blocks[] = <<<HTML
  <div class="section">
    <h3>{$h}</h3>
    <table class="spec-table">
{$specRows}
    </table>
  </div>
HTML;
        }

        $packageLines = preg_split('/\r\n|\r|\n/', $packageIncludes) ?: [];
        $packageHtml = '';
        foreach ($packageLines as $ln) {
            $ln = trim($ln);
            if ($ln === '') {
                continue;
            }
            $text = preg_replace('/^[•\-\*]\s*/u', '', $ln) ?? $ln;
            $packageHtml .= '<li>'.$esc($text).'</li>';
        }
        if ($packageHtml !== '') {
            $blocks[] = <<<HTML
  <div class="section">
    <h3>Package Includes</h3>
    <ul class="package-list">
{$packageHtml}
    </ul>
  </div>
HTML;
        }

        $brandTrim = trim($aboutBrand);
        if ($brandTrim !== '') {
            $brandP = '<p>'.nl2br($esc($brandTrim)).'</p>';
            $blocks[] = "  <div class=\"section\">\n    <h3>About Brand</h3>\n    {$brandP}\n  </div>";
        }

        if ($blocks === []) {
            return ['html' => '', 'spec_heading' => $specTableHeading];
        }

        $inner = implode("\n", $blocks);
        $html = <<<HTML
<div class="product-description-v2">
{$inner}
</div>
<style>
  .product-description-v2 { max-width: 1200px; margin: 0 auto; font-family: system-ui, -apple-system, sans-serif; color: #333; }
  .product-description-v2 .section { margin-bottom: 28px; }
  .product-description-v2 h3 { font-size: 1.15rem; margin: 0 0 12px 0; }
  .product-description-v2 .bullet-list { margin: 0; padding-left: 1.25rem; line-height: 1.55; }
  .product-description-v2 .bullet-list li { margin-bottom: 8px; }
  .product-description-v2 .features-list { list-style: none; padding: 0; margin: 0; }
  .product-description-v2 .features-list li { margin-bottom: 15px; line-height: 1.5; }
  .product-description-v2 .features-list li strong { color: #333; }
  .product-description-v2 .spec-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
  .product-description-v2 .spec-table td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
  .product-description-v2 .spec-table td:first-child { font-weight: 600; width: 38%; }
  .product-description-v2 .image-gallery { display: flex; flex-wrap: wrap; gap: 10px; margin: 8px 0; }
  .product-description-v2 .image-gallery img { max-width: 30%; height: auto; border-radius: 6px; }
  .product-description-v2 .package-list { margin: 0; padding-left: 1.25rem; line-height: 1.5; }
</style>
HTML;

        return ['html' => $html, 'spec_heading' => $specTableHeading];
    }
}
