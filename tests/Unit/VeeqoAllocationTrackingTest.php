<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\VeeqoAllocationTracking;
use PHPUnit\Framework\TestCase;

class VeeqoAllocationTrackingTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function fourLabelOrder(): array
    {
        return [
            'allocations' => [
                [
                    'sku' => 'WF140 DL04',
                    'shipment' => ['tracking_number' => '924810069027038250180', 'carrier' => ['name' => 'USPS']],
                ],
                [
                    'sku' => 'WF140 DL04',
                    'shipment' => ['tracking_number' => '924810069027038250193', 'carrier' => ['name' => 'USPS']],
                ],
                [
                    'sku' => 'WF140 D04',
                    'shipment' => ['tracking_number' => '924810069027038250206', 'carrier' => ['name' => 'USPS']],
                ],
                [
                    'sku' => 'WF140 D04',
                    'shipment' => ['tracking_number' => '924810069027038250219', 'carrier' => ['name' => 'USPS']],
                ],
            ],
        ];
    }

    public function test_each_sku_gets_its_own_label_not_the_first_allocation(): void
    {
        $order = $this->fourLabelOrder();

        $firstDl04 = VeeqoAllocationTracking::pick($order, 'WF140 DL04');
        $this->assertSame('924810069027038250180', $firstDl04['tracking'] ?? null);

        $secondDl04 = VeeqoAllocationTracking::pick($order, 'WF140 DL04', ['924810069027038250180']);
        $this->assertSame('924810069027038250193', $secondDl04['tracking'] ?? null);

        $firstD04 = VeeqoAllocationTracking::pick($order, 'WF140 D04');
        $this->assertSame('924810069027038250206', $firstD04['tracking'] ?? null);

        $secondD04 = VeeqoAllocationTracking::pick($order, 'WF140 D04', ['924810069027038250206']);
        $this->assertSame('924810069027038250219', $secondD04['tracking'] ?? null);
    }

    public function test_does_not_steal_another_sku_label(): void
    {
        $order = $this->fourLabelOrder();

        $hit = VeeqoAllocationTracking::pick($order, 'MISSING-SKU');

        $this->assertNull($hit);
    }
}
