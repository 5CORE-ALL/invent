<?php

namespace Tests\Unit;

use App\Support\TrackingCarrierGuesser;
use PHPUnit\Framework\TestCase;

class TrackingCarrierGuesserTest extends TestCase
{
    public function test_tracking_number_defines_carrier_over_marketplace_name(): void
    {
        $this->assertSame('FedEx', TrackingCarrierGuesser::fill('Seller Shipping local', '383404721719'));
        $this->assertSame('GOFO', TrackingCarrierGuesser::fill('Seller Shipping local', 'GFUS01069870592450'));
        $this->assertSame('UPS', TrackingCarrierGuesser::fill('Seller Shipping local', '1Z16D1R0YW57065449'));
        $this->assertSame('USPS', TrackingCarrierGuesser::fill('Seller Shipping local', '9400111899223856927644'));
    }

    public function test_marketplace_name_is_dropped_when_the_number_is_unknown(): void
    {
        $this->assertNull(TrackingCarrierGuesser::fill('Seller Shipping local', '3834332462375'));
        $this->assertNull(TrackingCarrierGuesser::fill("Seller's Own Logistics", ''));
    }

    public function test_known_carrier_is_kept_when_the_number_does_not_match(): void
    {
        $this->assertSame('FedEx', TrackingCarrierGuesser::fill('Federal Express', '3834332462375'));
        $this->assertSame('UPS', TrackingCarrierGuesser::fill('UPS', ''));
    }
}
