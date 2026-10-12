<?php

namespace Tests\Unit;

use App\Support\EbayPushVerification;
use PHPUnit\Framework\TestCase;

class EbayPushVerificationTest extends TestCase
{
    private function readings(array $seq): callable
    {
        $i = 0;

        return function () use (&$i, $seq) {
            $v = $seq[min($i, count($seq) - 1)];
            $i++;

            return $v;
        };
    }

    public function test_verified_when_listing_reports_the_requested_price(): void
    {
        $r = EbayPushVerification::confirm(68.07, $this->readings([68.07]), [0, 0, 0]);

        $this->assertSame(EbayPushVerification::VERIFIED, $r['state']);
        $this->assertSame(68.07, $r['live']);
    }

    public function test_verified_on_a_later_read_when_ebay_is_slow_to_show_the_new_price(): void
    {
        $r = EbayPushVerification::confirm(68.07, $this->readings([79.99, 79.99, 68.07]), [0, 0, 0]);

        $this->assertSame(EbayPushVerification::VERIFIED, $r['state']);
        $this->assertSame(68.07, $r['live']);
    }

    public function test_not_applied_when_listing_keeps_the_old_price(): void
    {
        // e.g. a multi-variation listing that ignored the item-level StartPrice: eBay said "success",
        // the listing still shows Std. The real price must come back so it is stored, not the requested one.
        $r = EbayPushVerification::confirm(68.07, $this->readings([79.99]), [0, 0, 0]);

        $this->assertSame(EbayPushVerification::NOT_APPLIED, $r['state']);
        $this->assertSame(79.99, $r['live']);
        $this->assertStringContainsString('79.99', $r['message']);
        $this->assertStringContainsString('68.07', $r['message']);
    }

    public function test_unverified_when_the_price_cannot_be_read_back_and_never_assumes_the_requested_price(): void
    {
        $r = EbayPushVerification::confirm(68.07, $this->readings([null]), [0, 0, 0]);

        $this->assertSame(EbayPushVerification::UNVERIFIED, $r['state']);
        $this->assertNull($r['live']);
    }

    public function test_last_good_reading_is_kept_when_a_later_read_fails(): void
    {
        $r = EbayPushVerification::confirm(68.07, $this->readings([79.99, null, null]), [0, 0, 0]);

        $this->assertSame(EbayPushVerification::NOT_APPLIED, $r['state']);
        $this->assertSame(79.99, $r['live']);
    }

    public function test_half_a_cent_is_the_same_price_but_a_cent_is_not(): void
    {
        $this->assertSame(
            EbayPushVerification::VERIFIED,
            EbayPushVerification::confirm(68.07, $this->readings([68.0749]), [0])['state']
        );
        $this->assertSame(
            EbayPushVerification::NOT_APPLIED,
            EbayPushVerification::confirm(68.07, $this->readings([68.08]), [0])['state']
        );
    }

    public function test_a_zero_reading_is_treated_as_unreadable(): void
    {
        $r = EbayPushVerification::confirm(68.07, $this->readings([0.0]), [0]);

        $this->assertSame(EbayPushVerification::UNVERIFIED, $r['state']);
    }

    public function test_reads_only_as_often_as_needed(): void
    {
        $calls = 0;
        $r = EbayPushVerification::confirm(10.00, function () use (&$calls) {
            $calls++;

            return 10.00;
        }, [0, 0, 0]);

        $this->assertSame(EbayPushVerification::VERIFIED, $r['state']);
        $this->assertSame(1, $calls, 'a confirmed price must not trigger extra GetItem calls');
    }
}
