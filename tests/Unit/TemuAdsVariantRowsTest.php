<?php

namespace Tests\Unit;

use App\Services\TemuAdsApiReportService;
use PHPUnit\Framework\TestCase;

class TemuAdsVariantRowsTest extends TestCase
{
    public function test_unique_variants_dedupe_dotted_skus_by_sku_id(): void
    {
        $rows = [
            ['sku' => 'RACK STAND 9U', 'sku_id' => '170398532542521'],
            ['sku' => 'RACK STAND 12U', 'sku_id' => '170398532509753'],
            ['sku' => 'RACK STAND 16U', 'sku_id' => '170398532526137'],
            ['sku' => 'RACK STAND 12U.', 'sku_id' => '170398532509753'],
            ['sku' => 'RACK STAND 16U.', 'sku_id' => '170398532526137'],
            ['sku' => 'RACK STAND 9U.', 'sku_id' => '170398532542521'],
        ];

        $variants = TemuAdsApiReportService::uniqueVariantsFromRows($rows);

        $this->assertCount(3, $variants);
        $this->assertSame(['RACK STAND 9U', 'RACK STAND 12U', 'RACK STAND 16U'], array_column($variants, 'sku'));
        $this->assertSame([
            '170398532542521',
            '170398532509753',
            '170398532526137',
        ], array_column($variants, 'sku_id'));
    }
}
