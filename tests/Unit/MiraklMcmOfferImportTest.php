<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\ListingManagerController;
use App\Services\Support\Concerns\MiraklMcmBulletImport;
use App\Support\Marketplace\ListingManagerPublishStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiraklMcmOfferImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.purchasingpower.mcm_api_key', 'test-key');
        config()->set('services.purchasingpower.mcm_base_url', 'https://pp.example.mirakl.net');
        config()->set('services.purchasingpower.shop_id', 4242);
        config()->set('services.purchasingpower.mcm_offer_state_code', '11');
        config()->set('services.purchasingpower.mcm_offer_import_poll_attempts', 2);
        config()->set('services.purchasingpower.mcm_offer_import_poll_delay_seconds', 1);
        Cache::flush();
    }

    public function test_of01_sends_semicolon_csv_and_reads_of02_complete(): void
    {
        Http::fake([
            'pp.example.mirakl.net/api/offers/imports?*' => Http::response(['import_id' => 9001], 201),
            'pp.example.mirakl.net/api/offers/imports/9001*' => Http::response([
                'import_id' => 9001,
                'status' => 'COMPLETE',
                'lines_read' => 1,
                'lines_in_error' => 0,
                'lines_in_pending' => 0,
                'lines_in_success' => 1,
                'offer_inserted' => 1,
                'offer_updated' => 0,
                'has_error_report' => false,
            ]),
            'pp.example.mirakl.net/api/offers?*' => Http::response([
                'offers' => [['offer_id' => 555001, 'shop_sku' => 'LS 100-6 RED', 'state_code' => '11']],
            ]),
        ]);

        $result = $this->service()->upsertMiraklMcmOffer('LS 100-6 RED', 10.64, 7);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(9001, $result['import_id']);
        $this->assertSame('555001', $result['offer_id']);
        $this->assertFalse($result['offer_pending_product']);
        $this->assertStringContainsString('created', $result['message']);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/api/offers/imports?')) {
                return false;
            }
            $this->assertSame('POST', $request->method());
            $this->assertStringContainsString('shop_id=4242', $request->url());
            $this->assertSame('test-key', $request->header('Authorization')[0] ?? null);
            $this->assertTrue($request->isMultipart());
            $file = collect($request->data())->first(fn ($part) => ($part['name'] ?? '') === 'file');
            $mode = collect($request->data())->first(fn ($part) => ($part['name'] ?? '') === 'import_mode');
            $this->assertSame('NORMAL', $mode['contents'] ?? null);
            $csv = (string) ($file['contents'] ?? '');
            $this->assertStringContainsString('sku;product-id;product-id-type;price;quantity;state', $csv);
            $this->assertStringContainsString('"LS 100-6 RED";"LS 100-6 RED";SHOP_SKU;10.64;7;11', $csv);

            return true;
        });
    }

    public function test_of01_waiting_for_product_is_accepted_as_pending(): void
    {
        Http::fake([
            'pp.example.mirakl.net/api/offers/imports?*' => Http::response(['import_id' => 9002], 201),
            'pp.example.mirakl.net/api/offers/imports/9002*' => Http::response([
                'import_id' => 9002,
                'status' => 'WAITING_SYNCHRONIZATION_PRODUCT',
                'lines_in_error' => 0,
                'has_error_report' => false,
            ]),
        ]);

        $result = $this->service()->upsertMiraklMcmOffer('LS 100-6 RED', 10.64, 0);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['offer_pending_product']);
        $this->assertSame('', $result['offer_id']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/api/offers?'));
    }

    public function test_of01_line_errors_surface_the_of03_report(): void
    {
        Http::fake([
            'pp.example.mirakl.net/api/offers/imports?*' => Http::response(['import_id' => 9003], 201),
            'pp.example.mirakl.net/api/offers/imports/9003/error_report*' => Http::response(
                "sku;error\nLS 100-6 RED;The logistic-class field is mandatory",
                200,
                ['Content-Type' => 'text/csv']
            ),
            'pp.example.mirakl.net/api/offers/imports/9003*' => Http::response([
                'import_id' => 9003,
                'status' => 'COMPLETE',
                'lines_in_error' => 1,
                'lines_in_success' => 0,
                'has_error_report' => true,
                'reason_status' => '',
            ]),
        ]);

        $result = $this->service()->upsertMiraklMcmOffer('LS 100-6 RED', 10.64, 3);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('1 line error', $result['message']);
        $this->assertStringContainsString('logistic-class field is mandatory', $result['message']);
    }

    public function test_of01_http_error_is_reported(): void
    {
        Http::fake([
            'pp.example.mirakl.net/api/offers/imports?*' => Http::response('{"message":"Access denied"}', 401),
        ]);

        $result = $this->service()->upsertMiraklMcmOffer('LS 100-6 RED', 10.64, 3);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('HTTP 401', $result['message']);
    }

    public function test_failed_draft_shows_failed_not_ready(): void
    {
        $details = ['description' => 'x', 'images' => ['https://img/a.jpg'], 'primary_category_id' => 'CAT1'];
        $ready = ListingManagerPublishStatus::readiness('Title', 10.0, 1, $details, 'failed', 'Purchasing Power');
        $this->assertSame('Failed', $ready['ui_status']);

        $queued = ListingManagerPublishStatus::readiness('Title', 10.0, 1, $details, 'queued', 'Purchasing Power');
        $this->assertSame('Publishing…', $queued['ui_status']);
    }

    public function test_last_publish_error_reads_the_latest_failure_only(): void
    {
        $notes = "Publishing to Macys in background (2026-10-01 03:10).\nPublish failed: Macy's P41 import FAILED with 1 transform error(s).";
        $this->assertStringContainsString('P41 import FAILED', ListingManagerController::lastPublishError($notes));

        $recovered = $notes."\nPublished to Macys via Listing Manager.";
        $this->assertSame('', ListingManagerController::lastPublishError($recovered));

        $inFlight = $notes."\nPublishing to Macys in background (2026-10-01 03:30).";
        $this->assertSame('', ListingManagerController::lastPublishError($inFlight));
        $this->assertSame('', ListingManagerController::lastPublishError(null));
    }

    private function service(): object
    {
        return new class
        {
            use MiraklMcmBulletImport;

            protected function miraklMcmConfigKey(): string
            {
                return 'purchasingpower';
            }

            protected function miraklMcmMarketplaceLabel(): string
            {
                return 'Purchasing Power';
            }
        };
    }
}
