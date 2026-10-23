<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductMaster\CategoryMastersController;
use App\Services\AmazonSpApiService;
use App\Services\CategoryMasters\CategoryChannelSyncService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class CategoryChannelSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Service whose listing lookup reads a fixed map instead of the metrics tables.
     *
     * @param  array<string, string>  $ids  "table|column" => remote id
     */
    private function service(array $ids): CategoryChannelSyncService
    {
        return new class($ids) extends CategoryChannelSyncService
        {
            public function __construct(private array $ids) {}

            protected function listingId(string $table, string $column, string $sku): ?string
            {
                return $this->ids[$table.'|'.$column] ?? null;
            }

            protected function temuNameFromPricing(string $catId, ?string $preferredTable): string
            {
                return $catId === '4459' ? 'Electronics > Subwoofers' : '';
            }
        };
    }

    private function ebayItem(string $id, string $name): array
    {
        return ['Item' => ['PrimaryCategory' => ['CategoryID' => $id, 'CategoryName' => $name]]];
    }

    private function fakeEbay(string $class, ?array $item, ?string $updateExpectId = null, bool $updateOk = true): void
    {
        $mock = Mockery::mock($class);
        $mock->lastGetItemError = '';
        $mock->shouldReceive('getItem')->andReturn($item);
        if ($updateExpectId !== null) {
            $mock->shouldReceive('updateCategory')->once()->with('ITEM', $updateExpectId)
                ->andReturn(['success' => $updateOk, 'message' => $updateOk ? 'moved' : 'eBay said no']);
        } else {
            $mock->shouldReceive('updateCategory')->never();
        }
        $this->app->instance($class, $mock);
    }

    public function test_id_from_text_reads_typed_ids(): void
    {
        $this->assertSame('12345', CategoryChannelSyncService::idFromText('12345'));
        $this->assertSame('38072', CategoryChannelSyncService::idFromText('Musical > Amps (38072)'));
        $this->assertSame('99', CategoryChannelSyncService::idFromText('Amps [99]'));
        $this->assertSame('', CategoryChannelSyncService::idFromText('Musical > Amps'));
        $this->assertSame('', CategoryChannelSyncService::idFromText('Model 3000 Amps'));
    }

    public function test_temu_detail_category_is_found_in_nested_response(): void
    {
        $found = CategoryChannelSyncService::temuCategoryFromDetail([
            'goodsBasic' => ['goodsName' => 'x', 'catId' => 4459],
            'skuList' => [],
        ]);
        $this->assertSame('4459', $found['id']);
        $this->assertSame('', $found['path']);

        $found = CategoryChannelSyncService::temuCategoryFromDetail(['catIdList' => [1, 2, 3459], 'catPath' => 'A/B/C']);
        $this->assertSame('3459', $found['id']);
        $this->assertSame('A > B > C', $found['path']);

        $this->assertSame('', CategoryChannelSyncService::temuCategoryFromDetail(['catId' => 0])['id']);
    }

    public function test_amazon_classification_becomes_path_and_leaf_id(): void
    {
        $catalog = ['classifications' => [[
            'marketplaceId' => 'ATVPDKIKX0DER',
            'classifications' => [[
                'displayName' => 'Car Subwoofers',
                'classificationId' => '3',
                'parent' => ['displayName' => 'Car Audio', 'classificationId' => '2', 'parent' => ['displayName' => 'Electronics', 'classificationId' => '1']],
            ]],
        ]]];

        $this->assertSame(
            ['id' => '3', 'path' => 'Electronics > Car Audio > Car Subwoofers'],
            AmazonSpApiService::classificationFromCatalog($catalog, 'ATVPDKIKX0DER')
        );
        $this->assertSame(['id' => '', 'path' => ''], AmazonSpApiService::classificationFromCatalog([], 'ATVPDKIKX0DER'));
    }

    public function test_ebay_pull_takes_first_listed_account_and_notes_differences(): void
    {
        $this->fakeEbay(EbayApiService::class, $this->ebayItem('111', 'Musical Instruments:Amps:Guitar Amps'));
        $this->fakeEbay(Ebay2ApiService::class, $this->ebayItem('222', 'Other:Thing'));
        $this->fakeEbay(EbayThreeApiService::class, null);

        $service = $this->service([
            'ebay_metrics|item_id' => 'ITEM',
            'ebay_2_metrics|item_id' => 'ITEM',
            // eBay 3 not listed
        ]);
        $out = $service->pull('ebay', 'SKU 1');

        $this->assertTrue($out['success']);
        $this->assertSame('111', $out['id']);
        $this->assertSame('Musical Instruments > Amps > Guitar Amps', $out['path']);
        $this->assertStringContainsString('eBay 2 differs', $out['message']);
        $this->assertSame('not_listed', $out['accounts'][2]['status']);
    }

    public function test_ebay_pull_fails_when_not_listed_anywhere(): void
    {
        $out = $this->service([])->pull('ebay', 'SKU 1');

        $this->assertFalse($out['success']);
        $this->assertSame('Not listed on eBay.', $out['message']);
    }

    public function test_temu_pull_uses_pricing_sheet_name_for_the_category_id(): void
    {
        $api = Mockery::mock(\App\Services\Temu3ApiService::class);
        $api->shouldReceive('callOpenApi')->with('bg.local.goods.detail.query', ['goodsId' => 610])
            ->andReturn(['success' => true, 'result' => ['goodsBasic' => ['catId' => 4459]]]);
        $this->app->instance(\App\Services\Temu3ApiService::class, $api);

        $out = $this->service(['temu3_metrics|goods_id' => '610'])->pull('temu', 'SKU 1');

        $this->assertTrue($out['success']);
        $this->assertSame('4459', $out['id']);
        $this->assertSame('Electronics > Subwoofers', $out['path']);
    }

    public function test_ebay_push_dry_run_changes_nothing_and_reports_the_plan(): void
    {
        $this->fakeEbay(EbayApiService::class, $this->ebayItem('111', 'Old:Cat'));
        $this->fakeEbay(Ebay2ApiService::class, $this->ebayItem('222', 'New:Cat'));
        $this->fakeEbay(EbayThreeApiService::class, null);

        $service = $this->service(['ebay_metrics|item_id' => 'ITEM', 'ebay_2_metrics|item_id' => 'ITEM']);
        $out = $service->push('ebay', 'SKU 1', 'New > Cat', '222', '', true);

        $this->assertSame('ready', $out['status']);
        $this->assertSame('222', $out['target_id']);
        $this->assertSame('ready', $out['accounts'][0]['status']);
        $this->assertSame('unchanged', $out['accounts'][1]['status']);
    }

    public function test_ebay_live_push_only_touches_accounts_in_a_different_category(): void
    {
        $this->fakeEbay(EbayApiService::class, $this->ebayItem('111', 'Old:Cat'), '222');
        $this->fakeEbay(Ebay2ApiService::class, $this->ebayItem('222', 'New:Cat'));
        $this->fakeEbay(EbayThreeApiService::class, null);

        $service = $this->service(['ebay_metrics|item_id' => 'ITEM', 'ebay_2_metrics|item_id' => 'ITEM']);
        $out = $service->push('ebay', 'SKU 1', 'New > Cat', '', '222', false);

        $this->assertSame('pushed', $out['status']);
        $this->assertSame('pushed', $out['accounts'][0]['status']);
        $this->assertSame('unchanged', $out['accounts'][1]['status']);
    }

    public function test_ebay_live_push_surfaces_an_ebay_rejection(): void
    {
        $this->fakeEbay(EbayApiService::class, $this->ebayItem('111', 'Old:Cat'), '222', false);

        $out = $this->service(['ebay_metrics|item_id' => 'ITEM'])->push('ebay', 'SKU 1', 'New > Cat', '', '222', false);

        $this->assertSame('error', $out['status']);
        $this->assertFalse($out['success']);
        $this->assertStringContainsString('eBay said no', $out['message']);
    }

    public function test_push_with_no_saved_category_or_unknown_amazon_id_is_an_error(): void
    {
        $service = $this->service([]);

        $this->assertSame('error', $service->push('ebay', 'SKU 1', '', '', '', true)['status']);

        $amazon = $service->push('amz', 'SKU 1', 'Car Audio > Subwoofers', '', '', true);
        $this->assertSame('error', $amazon['status']);
        $this->assertStringContainsString('browse node ID', $amazon['message']);
    }

    public function test_amazon_push_validates_first_then_applies_on_live(): void
    {
        $amazon = Mockery::mock(AmazonSpApiService::class);
        $amazon->shouldReceive('fetchListingCategory')->andReturn(['success' => true, 'id' => '1', 'path' => 'Old', 'message' => 'OK']);
        $amazon->shouldReceive('updateBrowseNode')->once()->with('SKU 1', '3', true)->andReturn(['success' => true, 'message' => 'Amazon accepts this browse node.']);
        $amazon->shouldReceive('updateBrowseNode')->once()->with('SKU 1', '3', false)->andReturn(['success' => true, 'message' => 'Amazon browse node updated.']);
        $this->app->instance(AmazonSpApiService::class, $amazon);

        $service = $this->service([]);
        $this->assertSame('ready', $service->push('amz', 'SKU 1', 'New', '3', '', true)['status']);
        $this->assertSame('pushed', $service->push('amz', 'SKU 1', 'New', '3', '3', false)['status']);
    }

    public function test_temu_push_is_reported_as_unsupported_and_sends_nothing(): void
    {
        $out = $this->service([])->push('temu', 'SKU 1', 'Electronics > Subwoofers', '4459', '4459', false);

        $this->assertSame('unsupported', $out['status']);
        $this->assertFalse($out['success']);
        $this->assertSame([], $out['accounts']);
    }

    public function test_controller_refuses_a_live_push_that_was_not_dry_run_first(): void
    {
        $request = Request::create('/category-masters/push', 'POST', [
            'channel' => 'ebay',
            'dry_run' => false,
            'items' => [['sku' => 'SKU 1']],
        ]);

        $response = app(CategoryMastersController::class)->push($request, $this->service([]));

        $this->assertSame(422, $response->getStatusCode());
    }
}
