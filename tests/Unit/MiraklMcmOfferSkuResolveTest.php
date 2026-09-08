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
        };
    }
}
