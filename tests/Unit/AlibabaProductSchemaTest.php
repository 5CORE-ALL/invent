<?php

namespace Tests\Unit;

use App\Support\Marketplace\AlibabaProductSchema;
use PHPUnit\Framework\TestCase;

class AlibabaProductSchemaTest extends TestCase
{
    public function test_rewrite_keeps_one_sku_and_sets_title_and_std_prc(): void
    {
        $xml = <<<'XML'
<itemSchema>
  <field id="productId" type="input"><value>999</value></field>
  <field id="productTitle" type="input"><value>Old sibling</value></field>
  <field id="sku" type="multiComplex">
    <complex-values>
      <field id="skuCode" type="input"><value>OLD-1</value></field>
      <field id="price" type="input"><value>10.00</value></field>
    </complex-values>
    <complex-values>
      <field id="skuCode" type="input"><value>OLD-2</value></field>
      <field id="price" type="input"><value>11.00</value></field>
    </complex-values>
  </field>
  <field id="fob" type="complex"><complex-value>
    <field id="range_min" type="input"><value>10.00</value></field>
    <field id="range_max" type="input"><value>12.00</value></field>
  </complex-value></field>
</itemSchema>
XML;

        $out = AlibabaProductSchema::rewrite($xml, 'New mic stand', 'NEW-SKU', '19.99');

        $this->assertStringNotContainsString('productId', $out);
        $this->assertStringNotContainsString('OLD-1', $out);
        $this->assertStringNotContainsString('OLD-2', $out);
        $this->assertStringContainsString('New mic stand', $out);
        $this->assertStringContainsString('NEW-SKU', $out);
        $this->assertSame(3, substr_count($out, '19.99'));
    }

    public function test_payload_helpers_read_schema_xml_and_product_id(): void
    {
        $payload = [
            'data' => [
                'result' => [
                    'xml' => '<itemSchema><field id="productTitle" type="input"><value>Amp</value></field></itemSchema>',
                    'product_id' => '1600123',
                ],
            ],
        ];

        $this->assertStringContainsString('productTitle', AlibabaProductSchema::xmlFromPayload($payload));
        $this->assertSame('1600123', AlibabaProductSchema::productIdFromPayload($payload));
        $this->assertSame('44', AlibabaProductSchema::categoryIdFromProduct(['category_id' => '44']));
    }
}
