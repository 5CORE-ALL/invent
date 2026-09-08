<?php

namespace Tests\Unit;

use App\Models\ShopifySku;
use App\Services\Support\Concerns\MiraklMcmBulletImport;
use Tests\TestCase;

class MiraklMcmOfferSkuResolveTest extends TestCase
{
    public function test_spaced_macy_sku_includes_compact_offer_candidates(): void
    {
        $sku = 'SS HD 1PK 3FT BLK WOB';
        $candidates = $this->service()->candidates($sku);

        $this->assertContains($sku, $candidates);
        $this->assertContains('SSHD1PK3FTBLKWOB', $candidates);
        $this->assertContains('SSHD1PK3FTBLKWOB', [ShopifySku::compactSkuForLookup($sku)]);
        $this->assertContains(str_replace(' ', '', $sku), $candidates);
    }

    public function test_detects_mirakl_offer_not_found_csv_error(): void
    {
        $raw = "2,No existing offer with SKU 'SS HD 1PK 3FT BLK WOB' found,SS HD 1PK 3FT BLK WOB,20.99";

        $this->assertTrue($this->service()::isMiraklOfferNotFoundError($raw));
        $this->assertTrue($this->service()::isMiraklOfferNotFoundError(
            "Macy price push failed: No existing offer with SKU 'SS HD 1PK 3FT BLK WOB' found"
        ));
        $this->assertFalse($this->service()::isMiraklOfferNotFoundError('HTTP 500 timeout'));
    }

    public function test_pricing_queue_keeps_sheet_offer_case_and_product_sku(): void
    {
        $queue = $this->service()->queue('SS HD 1PK 3FT BLK WOB', [
            'live' => 'SS HD 1PK 3FT BLK WOB',
            'sheet_offer' => 'SS HD 1PK 3FT BLK WoB',
            'sheet_product' => '810144134161_20303673_12',
        ]);

        $this->assertSame('SS HD 1PK 3FT BLK WOB', $queue[0]);
        $this->assertContains('SS HD 1PK 3FT BLK WoB', $queue);
        $this->assertContains('810144134161_20303673_12', $queue);
        $this->assertContains('SSHD1PK3FTBLKWOB', $queue);
    }

    private function service(): object
    {
        return new class
        {
            use MiraklMcmBulletImport;

            protected function miraklMcmConfigKey(): string
            {
                return 'macy';
            }

            protected function miraklMcmMarketplaceLabel(): string
            {
                return 'Macy';
            }

            public function candidates(string $sku): array
            {
                return $this->miraklMcmOfferSkuCandidates($sku);
            }

            public function queue(string $sku, array $hints = []): array
            {
                return $this->miraklMcmPricingOfferSkuQueue($sku, $hints);
            }
        };
    }
}
