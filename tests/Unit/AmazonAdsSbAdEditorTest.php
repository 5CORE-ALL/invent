<?php

namespace Tests\Unit;

use App\Support\AmazonAdsSbAdEditor;
use PHPUnit\Framework\TestCase;

class AmazonAdsSbAdEditorTest extends TestCase
{
    public function test_normalize_asin(): void
    {
        $this->assertSame('B0CKPMCDWW', AmazonAdsSbAdEditor::normalizeAsin('b0ckpmcdww'));
        $this->assertSame('', AmazonAdsSbAdEditor::normalizeAsin('MUS FLD PNK'));
    }

    public function test_merge_and_remove_asins(): void
    {
        $merged = AmazonAdsSbAdEditor::mergeAsins(['B0CKPMCDWW'], ['b0dzctkgsn', 'B0CKPMCDWW']);
        sort($merged);
        $this->assertSame(['B0CKPMCDWW', 'B0DZCTKGSN'], $merged);

        $left = AmazonAdsSbAdEditor::withoutAsins($merged, ['B0DZCTKGSN']);
        $this->assertSame(['B0CKPMCDWW'], $left);
    }

    public function test_copy_creative_keeps_brand_fields_and_new_asins(): void
    {
        $copied = AmazonAdsSbAdEditor::copyCreativeForAsins(
            [
                'creativeType' => 'PRODUCT_COLLECTION',
                'creativeProperties' => [
                    'brandName' => '5 Core',
                    'headline' => 'Shop stands',
                    'brandLogoAssetID' => 'amzn1.asset.1',
                    'asins' => ['B0CKPMCDWW'],
                ],
            ],
            [],
            ['B0CKPMCDWW', 'B0DZCTKGSN']
        );
        $this->assertSame(['B0CKPMCDWW', 'B0DZCTKGSN'], $copied['asins']);
        $this->assertSame('5 Core', $copied['brandName']);
        $this->assertSame('Shop stands', $copied['headline']);
        $this->assertSame('amzn1.asset.1', $copied['brandLogoAssetId']);
    }
}
