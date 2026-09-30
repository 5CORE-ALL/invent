<?php

namespace Tests\Unit;

use App\Services\NeweggApiService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NeweggDeadCatalogMatchRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.newegg.seller_id' => 'BFJ5',
            'services.newegg.api_key' => 'k',
            'services.newegg.secret_key' => 's',
            'services.newegg.alternate_mpn_suffix' => '-5C',
        ]);
    }

    public function test_detects_dead_parent_item_rejection(): void
    {
        $msg = "Error(s). Item not created. ParentItemNumber: '0VU-00MH-000M8' does not exist.";

        $this->assertTrue(NeweggApiService::isDeadCatalogMatchError($msg));
        $this->assertSame('0VU-00MH-000M8', NeweggApiService::deadCatalogItemNumber($msg));
        $this->assertFalse(NeweggApiService::isDeadCatalogMatchError('Item not created. Manufacturer does not exist!'));
        $this->assertSame('3501 USB WB-5C', NeweggApiService::alternateManufacturerPartNumber('3501 USB WB', 1));
        $this->assertSame('3501 USB WB-5C2', NeweggApiService::alternateManufacturerPartNumber('3501 USB WB', 2));
    }

    public function test_dead_parent_rejection_resubmits_with_alternate_mpn_then_without_upc(): void
    {
        $api = new FakeNeweggCreateApi();
        $api->resolveResults = [
            'REQ-1' => ['success' => false, 'terminal' => true, 'request_id' => 'REQ-1',
                'message' => "Error(s). Item not created. ParentItemNumber: '0VU-00MH-000M8' does not exist."],
            'REQ-2' => ['success' => false, 'terminal' => true, 'request_id' => 'REQ-2',
                'message' => "Error(s). Item not created. ParentItemNumber: '0VU-00MH-000M8' does not exist."],
            'REQ-3' => ['success' => false, 'terminal' => true, 'request_id' => 'REQ-3',
                'message' => "Error(s). Item not created. ParentItemNumber: '0VU-00MH-000M8' does not exist."],
        ];
        $fields = [
            'sku' => '3501 USB WB',
            'title' => 'Megaphone',
            'upc' => '810099496918',
            'subcategory_id' => '1234',
            'price' => 30.37,
            'inventory' => 19,
            'images' => ['https://x/1.jpg'],
        ];

        // First publish: original data rejected -> retried at once with an alternate MPN.
        $first = $api->createListing($fields);
        $this->assertFalse($first['success']);
        $this->assertTrue($first['still_processing']);
        $this->assertSame('REQ-2', $first['request_id']);
        $this->assertStringContainsString('3501 USB WB-5C', $first['message']);
        $this->assertStringContainsString('0VU-00MH-000M8', $first['message']);
        $this->assertCount(2, $api->submitted);
        $this->assertSame('3501 USB WB', $api->submitted[0]['mpn'] ?? '3501 USB WB');
        $this->assertSame('3501 USB WB-5C', $api->submitted[1]['mpn']);
        $this->assertSame('810099496918', $api->submitted[1]['upc']);

        // Second publish resumes REQ-2: still rejected -> retry without the UPC.
        $second = $api->createListing($fields);
        $this->assertTrue($second['still_processing']);
        $this->assertSame('REQ-3', $second['request_id']);
        $this->assertStringContainsString('without the UPC', $second['message']);
        $this->assertSame('3501 USB WB-5C2', $api->submitted[2]['mpn']);
        $this->assertSame('', $api->submitted[2]['upc']);

        // Third publish resumes REQ-3: out of retries -> terminal message with guidance.
        $third = $api->createListing($fields);
        $this->assertFalse($third['success']);
        $this->assertTrue($third['terminal']);
        $this->assertStringContainsString('datafeeds@newegg.com', $third['message']);
        $this->assertCount(3, $api->submitted);
    }

    public function test_other_rejections_are_not_retried(): void
    {
        $api = new FakeNeweggCreateApi();
        $api->resolveResults = [
            'REQ-1' => ['success' => false, 'terminal' => true, 'request_id' => 'REQ-1',
                'message' => 'Item not created. Property: Color value error.'],
        ];
        $res = $api->createListing(['sku' => 'ABC', 'title' => 'T', 'subcategory_id' => '1', 'price' => 1, 'inventory' => 1]);

        $this->assertFalse($res['success']);
        $this->assertTrue($res['terminal']);
        $this->assertCount(1, $api->submitted);
    }
}

class FakeNeweggCreateApi extends NeweggApiService
{
    /** @var list<array<string, mixed>> */
    public array $submitted = [];

    /** @var array<string, array<string, mixed>> */
    public array $resolveResults = [];

    public function lookupSellerItem(string $sku): array
    {
        return ['success' => false, 'message' => 'Item is not on Newegg yet.', 'item_number' => '', 'blocked_by_cloudflare' => false];
    }

    protected function submitItemCreateFeed(string $sku, array $fields, string $platform): array
    {
        $this->submitted[] = $fields;

        return ['success' => true, 'message' => 'submitted', 'request_id' => 'REQ-'.count($this->submitted)];
    }

    protected function resolveSubmittedNeweggItem(string $sku, string $requestId, string $platform): array
    {
        return $this->resolveResults[$requestId] ?? ['success' => false, 'still_processing' => true, 'request_id' => $requestId, 'message' => 'processing'];
    }
}
