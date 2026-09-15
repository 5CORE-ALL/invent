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

    public function test_second_same_sku_label_uses_sku_code_and_exclude(): void
    {
        $order = [
            'allocations' => [
                [
                    'sellable' => ['sku_code' => 'HOME10 2-MIC'],
                    'shipment' => ['tracking_number' => ['tracking_number' => '933461099037030819954']],
                ],
                [
                    'sellable' => ['sku_code' => 'HOME10 2-MIC'],
                    'shipment' => ['tracking_number' => ['tracking_number' => '933461099037030838415']],
                ],
            ],
        ];

        $first = VeeqoAllocationTracking::pick($order, 'HOME10 2-MIC');
        $this->assertSame('933461099037030819954', $first['tracking'] ?? null);

        $second = VeeqoAllocationTracking::pick($order, 'HOME10 2-MIC', ['933461099037030819954']);
        $this->assertSame('933461099037030838415', $second['tracking'] ?? null);
    }
}
