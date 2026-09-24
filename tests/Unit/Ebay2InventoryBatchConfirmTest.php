<?php

namespace Tests\Unit;

use App\Services\Ebay2ApiService;
use PHPUnit\Framework\TestCase;

class Ebay2InventoryBatchConfirmTest extends TestCase
{
    public function test_batch_success_does_not_confirm_a_variation_left_at_the_old_qty(): void
    {
        $statuses = Ebay2ApiService::inventoryStatusesFromResponse([
            'Ack' => 'Success',
            'InventoryStatus' => [
                ['ItemID' => '3666584295112', 'SKU' => '1M BLACK OPD1 10X', 'Quantity' => '4'],
                ['ItemID' => '222', 'SKU' => 'OTHER', 'Quantity' => '10'],
            ],
        ]);

        $confirmed = Ebay2ApiService::confirmedRequestIndexes([
            ['item_id' => '3666584295112', 'sku' => '1M BLACK OPD1 10X', 'quantity' => 24],
            ['item_id' => '222', 'sku' => 'OTHER', 'quantity' => 10],
        ], $statuses);

        $this->assertSame([1], $confirmed);
    }

    public function test_matching_returned_quantity_is_confirmed(): void
    {
        $confirmed = Ebay2ApiService::confirmedRequestIndexes([
            ['item_id' => '3666584295112', 'sku' => '1M BLACK OPD1 10X', 'quantity' => 24],
        ], [
            ['item_id' => '3666584295112', 'sku' => '1M BLACK OPD1 10X', 'quantity' => 24],
        ]);

        $this->assertSame([0], $confirmed);
    }
}
