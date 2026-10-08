<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ShopifyDuplicateOrderPlanner;
use App\Services\MarketplaceManager\ShopifyOrderCreateClaim;
use PHPUnit\Framework\TestCase;

class ShopifyDuplicateOrderPlannerTest extends TestCase
{
    private function order(string $id, string $name, ?string $fulfillment, string $createdAt, array $lines = [['sku' => 'SKU-1', 'quantity' => 1]]): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'tags' => 'amazon, amazon-113-7876038-6872205, fbm',
            'created_at' => $createdAt,
            'cancelled_at' => null,
            'fulfillment_status' => $fulfillment,
            'line_items' => $lines,
        ];
    }

    public function test_group_key_from_amazon_tag(): void
    {
        $keys = ShopifyDuplicateOrderPlanner::groupKeys(['tags' => 'amazon, amazon-113-7876038-6872205, fbm']);

        $this->assertSame(['amazon-113-7876038-6872205'], $keys);
    }

    public function test_generic_tags_do_not_group(): void
    {
        $this->assertSame([], ShopifyDuplicateOrderPlanner::groupKeys(['tags' => 'ebay2-motors, amazon, temu-us']));
    }

    public function test_numbered_channels_do_not_collide(): void
    {
        $this->assertSame(['temu3-po-12345'], ShopifyDuplicateOrderPlanner::groupKeys(['tags' => 'temu3, temu3-PO-12345']));
        $this->assertSame(['tiktok2-5799'], ShopifyDuplicateOrderPlanner::groupKeys(['tags' => 'tiktok2-5799']));
    }

    public function test_b5c_b2b_groups_by_source_identifier(): void
    {
        $keys = ShopifyDuplicateOrderPlanner::groupKeys([
            'tags' => 'Business 5 Core (B2B), B5C-10023',
            'source_identifier' => 'B5C-10023',
        ]);

        $this->assertSame(['b5cb2b:b5c-10023'], $keys);
    }

    public function test_keeps_fulfilled_copy_and_cancels_unfulfilled(): void
    {
        $plan = ShopifyDuplicateOrderPlanner::plan([
            $this->order('1', '#348416', null, '2026-10-08T12:55:01-04:00'),
            $this->order('2', '#348417', 'fulfilled', '2026-10-08T12:55:03-04:00'),
        ]);

        $this->assertSame('2', $plan['keeper']);
        $this->assertSame(['1'], $plan['cancel']);
        $this->assertNull($plan['review']);
    }

    public function test_keeps_locally_linked_copy_when_none_fulfilled(): void
    {
        $plan = ShopifyDuplicateOrderPlanner::plan([
            $this->order('1', '#1', null, '2026-10-08T12:55:01-04:00'),
            $this->order('2', '#2', null, '2026-10-08T12:55:03-04:00'),
        ], ['2' => true]);

        $this->assertSame('2', $plan['keeper']);
        $this->assertSame(['1'], $plan['cancel']);
    }

    public function test_keeps_oldest_when_nothing_else_decides(): void
    {
        $plan = ShopifyDuplicateOrderPlanner::plan([
            $this->order('2', '#2', null, '2026-10-08T12:55:03-04:00'),
            $this->order('1', '#1', null, '2026-10-08T12:55:01-04:00'),
        ]);

        $this->assertSame('1', $plan['keeper']);
        $this->assertSame(['2'], $plan['cancel']);
    }

    public function test_two_fulfilled_copies_need_review(): void
    {
        $plan = ShopifyDuplicateOrderPlanner::plan([
            $this->order('1', '#1', 'fulfilled', '2026-10-08T12:55:01-04:00'),
            $this->order('2', '#2', 'partial', '2026-10-08T12:55:03-04:00'),
        ]);

        $this->assertSame([], $plan['cancel']);
        $this->assertNotNull($plan['review']);
    }

    public function test_different_line_items_need_review(): void
    {
        $plan = ShopifyDuplicateOrderPlanner::plan([
            $this->order('1', '#1', null, '2026-10-08T12:55:01-04:00', [['sku' => 'A', 'quantity' => 1]]),
            $this->order('2', '#2', null, '2026-10-08T12:55:03-04:00', [['sku' => 'B', 'quantity' => 1]]),
        ]);

        $this->assertSame([], $plan['cancel']);
        $this->assertNotNull($plan['review']);
    }

    public function test_copies_days_apart_need_review(): void
    {
        $plan = ShopifyDuplicateOrderPlanner::plan([
            $this->order('1', '#1', null, '2026-10-01T12:00:00-04:00'),
            $this->order('2', '#2', null, '2026-10-08T12:00:00-04:00'),
        ]);

        $this->assertSame([], $plan['cancel']);
        $this->assertNotNull($plan['review']);
    }

    public function test_cancelled_copies_are_ignored(): void
    {
        $cancelled = $this->order('1', '#1', null, '2026-10-08T12:55:01-04:00');
        $cancelled['cancelled_at'] = '2026-10-08T13:00:00-04:00';

        $plan = ShopifyDuplicateOrderPlanner::plan([
            $cancelled,
            $this->order('2', '#2', 'fulfilled', '2026-10-08T12:55:03-04:00'),
        ]);

        $this->assertNull($plan['keeper']);
        $this->assertSame([], $plan['cancel']);
    }

    public function test_line_signature_ignores_variant_ids_and_order(): void
    {
        $a = ShopifyDuplicateOrderPlanner::lineItemSignature(['line_items' => [
            ['sku' => 'B', 'quantity' => 2, 'variant_id' => 9],
            ['sku' => 'a', 'quantity' => 1, 'variant_id' => 8],
        ]]);
        $b = ShopifyDuplicateOrderPlanner::lineItemSignature(['line_items' => [
            ['sku' => 'A', 'quantity' => 1],
            ['sku' => 'b', 'quantity' => 2],
        ]]);

        $this->assertSame($a, $b);
    }

    public function test_claim_keeps_lock_unless_shopify_answered_4xx(): void
    {
        $this->assertTrue(ShopifyOrderCreateClaim::definitelyNotCreated(422));
        $this->assertTrue(ShopifyOrderCreateClaim::definitelyNotCreated(429));
        $this->assertFalse(ShopifyOrderCreateClaim::definitelyNotCreated(500));
        $this->assertFalse(ShopifyOrderCreateClaim::definitelyNotCreated(503));
        $this->assertFalse(ShopifyOrderCreateClaim::definitelyNotCreated(null));
        $this->assertFalse(ShopifyOrderCreateClaim::definitelyNotCreated(201));
    }

    public function test_claim_refs_normalized(): void
    {
        $this->assertSame(
            ['113-7876038-6872205', 'amz-113'],
            ShopifyOrderCreateClaim::normalizeRefs(['#113-7876038-6872205', ' 113-7876038-6872205 ', 'AMZ-113', ''])
        );
        $this->assertSame('amazonorderpushservice', ShopifyOrderCreateClaim::channelKey('AmazonOrderPushService'));
    }
}
