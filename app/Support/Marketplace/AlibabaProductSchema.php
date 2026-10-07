<?php

namespace App\Support\Marketplace;

/**
 * Rewrite an Alibaba ICBU product schema so a Missing L SKU can be added
 * from a listed sibling (or a category template).
 */
class AlibabaProductSchema
{
    public static function rewrite(string $xml, string $title, string $sku, string $price): string
    {
        $xml = trim($xml);
        $title = trim($title);
        $sku = trim($sku);
        $price = number_format((float) $price, 2, '.', '');
        if ($xml === '' || $title === '' || $sku === '') {
            return $xml;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded || $dom->documentElement === null) {
            return $xml;
        }

        $xp = new \DOMXPath($dom);
        foreach ($xp->query('//field[@id="productId" or @id="product_id"]') as $field) {
            $field->parentNode?->removeChild($field);
        }

        $titleSet = false;
        foreach (['productTitle', 'subject', 'productName'] as $id) {
            foreach ($xp->query('//field[@id="'.$id.'"]/value') as $value) {
                self::setText($value, $title);
                $titleSet = true;
            }
        }
        if (! $titleSet) {
            $field = $dom->createElement('field');
            $field->setAttribute('id', 'productTitle');
            $field->setAttribute('type', 'input');
            $value = $dom->createElement('value');
            self::setText($value, $title);
            $field->appendChild($value);
            $dom->documentElement->insertBefore($field, $dom->documentElement->firstChild);
        }

        $skuFields = $xp->query('//field[@id="sku"]/complex-values');
        if ($skuFields !== false && $skuFields->length > 1) {
            for ($i = $skuFields->length - 1; $i >= 1; $i--) {
                $extra = $skuFields->item($i);
                $extra?->parentNode?->removeChild($extra);
            }
        }

        foreach ($xp->query('//field[@id="skuCode" or @id="sku_code" or @id="skuOuterId"]/value') as $value) {
            self::setText($value, $sku);
        }
        foreach ($xp->query('//field[@id="price" or @id="range_min" or @id="range_max"]/value') as $value) {
            if (is_numeric(trim($value->textContent))) {
                self::setText($value, $price);
            }
        }

        $out = $dom->saveXML($dom->documentElement);

        return is_string($out) ? $out : $xml;
    }

    public static function xmlFromPayload(array $payload): string
    {
        $found = '';
        $walk = function (mixed $node) use (&$walk, &$found): void {
            if (is_string($node) && str_contains($node, '<field')) {
                if ($found === '' || str_contains($node, 'productTitle') || str_contains($node, 'itemSchema')) {
                    $found = $node;
                }

                return;
            }
            if (is_array($node)) {
                foreach ($node as $child) {
                    $walk($child);
                }
            }
        };
        $walk($payload);

        return trim($found);
    }

    public static function productIdFromPayload(array $payload): string
    {
        $found = '';
        $walk = function (mixed $node, ?string $key = null) use (&$walk, &$found): void {
            if ($found !== '') {
                return;
            }
            if (is_array($node)) {
                foreach ($node as $childKey => $child) {
                    $walk($child, is_string($childKey) ? $childKey : null);
                }

                return;
            }
            if (! is_scalar($node) || $key === null) {
                return;
            }
            $normalized = strtolower(str_replace(['-', '_'], '', $key));
            if (! in_array($normalized, ['productid', 'itemid', 'goodsid'], true)) {
                return;
            }
            $id = trim((string) $node);
            if ($id !== '' && preg_match('/^\d+$/', $id) === 1) {
                $found = $id;
            }
        };
        $walk($payload);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function categoryIdFromProduct(array $product): string
    {
        foreach (['categoryId', 'category_id', 'catId', 'cat_id'] as $key) {
            $id = trim((string) ($product[$key] ?? ''));
            if ($id !== '' && ctype_digit($id)) {
                return $id;
            }
        }

        return '';
    }

    private static function setText(\DOMNode $node, string $text): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($node->ownerDocument->createTextNode($text));
    }
}
