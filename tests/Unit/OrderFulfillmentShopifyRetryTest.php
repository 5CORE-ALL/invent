<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use App\Services\OrderFulfillment\OrderFulfillmentShopifyPushService as Push;
use Carbon\Carbon;
use Tests\TestCase;

class OrderFulfillmentShopifyRetryTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_failures_wait_longer_each_time_but_never_stop(): void
    {
        $this->assertSame(45, Push::retryDelayMinutes(1));
        $this->assertSame(90, Push::retryDelayMinutes(2));
        $this->assertSame(180, Push::retryDelayMinutes(3));
        $this->assertSame(360, Push::retryDelayMinutes(4));
        $this->assertSame(360, Push::retryDelayMinutes(200));
    }

    public function test_mismatch_is_retried_twice_a_day(): void
    {
        $this->assertSame(720, Push::retryDelayMinutes(1, true));
        $this->assertSame(720, Push::retryDelayMinutes(50, true));
    }

    public function test_reasons_group_without_order_numbers(): void
    {
        $this->assertSame('not tried yet', Push::reasonLabel(''));
        $this->assertSame(
            Push::reasonLabel('Shopify fulfill failed (HTTP 422): order 6312345678901 is closed'),
            Push::reasonLabel('Shopify fulfill failed (HTTP 422): order 6398765432109 is closed')
        );
    }

    public function test_recent_orders_are_rechecked_for_tracking_first(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

        $this->assertSame(60, OrderFulfillmentController::trackingMissRecheckMinutes('2026-10-06 09:00:00'));
        $this->assertSame(240, OrderFulfillmentController::trackingMissRecheckMinutes('2026-09-30 09:00:00'));
        $this->assertSame(720, OrderFulfillmentController::trackingMissRecheckMinutes('2026-09-15 09:00:00'));
        $this->assertSame(60, OrderFulfillmentController::trackingMissRecheckMinutes(''));
    }

    public function test_throttling_and_outages_are_not_counted_as_channel_failures(): void
    {
        $this->assertTrue(Push::isTransientChannelFailure('Shopify order fetch failed.'));
        $this->assertTrue(Push::isTransientChannelFailure('Api access frequency exceeds the limit. this ban will last 1 seconds'));
        $this->assertTrue(Push::isTransientChannelFailure('Could not load Shopify fulfillments to update tracking (HTTP 429).'));
        $this->assertTrue(Push::isTransientChannelFailure('Reverb ship HTTP 503: unavailable'));
        $this->assertFalse(Push::isTransientChannelFailure('Reverb ship HTTP 422: tracking number is invalid'));
        $this->assertFalse(Push::isTransientChannelFailure('No tracking number on Shopify yet.'));
        $this->assertFalse(Push::isTransientChannelFailure(''));
    }

    public function test_newegg_second_parameter_is_a_bool_so_the_hub_must_not_pass_the_known_array(): void
    {
        $second = (new \ReflectionMethod(\App\Services\MarketplaceManager\NeweggTrackingSyncService::class, 'pushTrackingForOrder'))->getParameters()[1];
        $this->assertSame('bool', $second->getType()->getName());

        $b5c = (new \ReflectionMethod(\App\Services\MarketplaceManager\B5cB2bTrackingSyncService::class, 'pushTrackingForOrder'))->getParameters()[1];
        $this->assertSame('array', $b5c->getType()->getName());
    }
}