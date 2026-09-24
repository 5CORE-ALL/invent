<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ReverbTrackingSyncService;
use PHPUnit\Framework\TestCase;

class ReverbTrackingSyncDirectionTest extends TestCase
{
    public function test_reverb_shipping_code_is_copied_onto_shopify_when_shopify_is_empty(): void
    {
        $this->assertSame(
            'pull_from_reverb',
            ReverbTrackingSyncService::trackingSyncAction('', '9400111899223197428490')
        );
    }

    public function test_changed_reverb_tracking_replaces_the_shopify_number(): void
    {
        $this->assertSame(
            'pull_from_reverb',
            ReverbTrackingSyncService::trackingSyncAction('OLD123456789', '9400111899223197428490')
        );
    }

    public function test_matching_numbers_are_already_synced(): void
    {
        $this->assertSame(
            'already_synced',
            ReverbTrackingSyncService::trackingSyncAction('9400 1118 9922 3197 4284 90', '9400111899223197428490')
        );
    }

    public function test_shopify_number_is_pushed_only_when_reverb_has_none(): void
    {
        $this->assertSame(
            'push_to_reverb',
            ReverbTrackingSyncService::trackingSyncAction('9400111899223197428490', '')
        );
        $this->assertSame('none', ReverbTrackingSyncService::trackingSyncAction('', ''));
    }
}
