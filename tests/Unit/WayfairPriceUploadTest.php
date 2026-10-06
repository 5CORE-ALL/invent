<?php

namespace Tests\Unit;

use App\Jobs\UploadWayfairPriceFile;
use App\Models\WayfairPriceUpload;
use App\Services\Wayfair\Upload\BrowserUploadAdapter;
use App\Services\Wayfair\Upload\MockUploadAdapter;
use App\Services\Wayfair\WayfairPriceFileGenerator;
use App\Services\Wayfair\WayfairPriceFileValidator;
use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use App\Services\Wayfair\WayfairPriceUploadPlanner;
use App\Services\Wayfair\WayfairUploadLogger;
use App\Services\Wayfair\WayfairUploadService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WayfairPriceUploadTest extends TestCase
{
    private string $dir = 'wayfair/test-uploads';

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for Wayfair upload tests.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::dropIfExists('wayfair_price_uploads');

        $migration = include database_path('migrations/2026_10_06_120000_create_wayfair_price_uploads_table.php');
        $migration->up();

        $this->dir = 'wayfair/test-uploads';
        config()->set('wayfair_upload.outgoing_directory', $this->dir);
        config()->set('wayfair_upload.only_changed', true);
        config()->set('wayfair_upload.max_price_change_percent', null);
        config()->set('wayfair_upload.mode', 'mock');
        config()->set('wayfair_upload.mock_result', 'success');
        config()->set('wayfair_upload.queue', 'wayfair-upload');
        config()->set('wayfair_upload.max_retries', 3);
        config()->set('wayfair_upload.job_timeout', 30);
        config()->set('wayfair_upload.stuck_after', 60);
    }

    protected function tearDown(): void
    {
        $path = storage_path('app/'.$this->dir);
        if (is_dir($path)) {
            foreach (glob($path.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($path);
        }
        parent::tearDown();
    }

    public function test_existing_wayfair_price_formula_is_unchanged(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/MarketPlace/WayfairController.php'));
        $this->assertStringContainsString('($price * $margin) - $lp', $source);
        $this->assertStringContainsString("round(\$sprice, 2)", $source);

        $map = (new WayfairPriceFileGenerator())->priceMapFromRows([
            ['sku' => 'ABC', 'is_parent' => false, 'sprice' => 12.34, 'price' => 99, 'lp' => 1, '_margin' => 0.8],
            ['sku' => 'PARENT ABC', 'is_parent' => true, 'sprice' => 50],
        ], 'sprice');

        $this->assertSame(['ABC' => 12.34], $map);
    }

    public function test_existing_download_button_still_exports_the_analytics_csv(): void
    {
        $blade = file_get_contents(resource_path('views/market-places/wayfair_pricing_view.blade.php'));
        $this->assertStringContainsString("table.download('csv', 'wayfair_analytics_data.csv');", $blade);
        $this->assertStringContainsString('id="wf-export-pricing"', $blade);
    }

    public function test_file_generation_uses_the_wayfair_price_template(): void
    {
        $written = (new WayfairPriceFileGenerator())->write([
            'WF-1' => 10.5,
            'WF-2' => 3,
        ], 'wayfair_price_test.csv');

        $parsed = (new WayfairPriceFileGenerator())->read($written['absolute_path']);
        $this->assertSame(['Supplier Part Number', 'New Base Cost'], $parsed['headers']);
        $this->assertSame(['WF-1' => '10.50', 'WF-2' => '3.00'], $parsed['rows']);
        $this->assertSame(hash_file('sha256', $written['absolute_path']), $written['file_sha256']);
        @unlink($written['absolute_path']);
    }

    public function test_validation_rejects_empty_negative_duplicate_and_bad_template(): void
    {
        $validator = new WayfairPriceFileValidator();
        $this->assertNotEmpty($validator->errors([
            'headers' => WayfairPriceFileGenerator::HEADERS,
            'rows' => [],
        ]));
        $this->assertNotEmpty($validator->errors([
            'headers' => ['SKU', 'Price'],
            'rows' => ['A' => '1.00'],
        ]));
        $negative = $validator->errors([
            'headers' => WayfairPriceFileGenerator::HEADERS,
            'rows' => ['A' => '-1.00'],
        ]);
        $this->assertTrue(collect($negative)->contains(fn ($e) => str_contains($e, 'Negative')));
        $duplicate = $validator->errors([
            'headers' => WayfairPriceFileGenerator::HEADERS,
            'rows' => ['A' => '1.00', 'a' => '2.00'],
        ]);
        $this->assertTrue(collect($duplicate)->contains(fn ($e) => str_contains($e, 'Duplicate')));
    }

    public function test_changed_prices_use_existing_cent_precision(): void
    {
        $planner = new WayfairPriceUploadPlanner();
        $changed = $planner->changedSkus(
            ['A' => 10.004, 'B' => 12.34],
            ['A' => 10.00, 'B' => 11.00]
        );
        $this->assertArrayNotHasKey('A', $changed);
        $this->assertSame(12.34, $changed['B']);
    }

    public function test_no_change_day_does_not_queue_an_upload(): void
    {
        Bus::fake();
        WayfairPriceUpload::query()->create([
            'status' => WayfairPriceUpload::SUCCESS,
            'price_snapshot' => ['A' => 10.00],
            'total_rows' => 1,
            'changed_rows' => 1,
            'file_type' => 'csv',
        ]);

        $record = app(WayfairPriceUploadOrchestrator::class)->generate(true, false, ['A' => 10.00], 'test');

        $this->assertSame(WayfairPriceUpload::NO_CHANGES, $record->status);
        $this->assertStringContainsString('No price changes', (string) $record->error_message);
        Bus::assertNotDispatched(UploadWayfairPriceFile::class);
    }

    public function test_queue_job_is_created_and_duplicate_file_is_not_queued_again(): void
    {
        Bus::fake();
        $orchestrator = app(WayfairPriceUploadOrchestrator::class);
        $first = $orchestrator->generate(true, false, ['A' => 15.25], 'test');
        $second = $orchestrator->generate(true, false, ['A' => 15.25], 'test');

        $this->assertSame(WayfairPriceUpload::QUEUED, $first->status);
        $this->assertSame(WayfairPriceUpload::NO_CHANGES, $second->status);
        Bus::assertDispatchedTimes(UploadWayfairPriceFile::class, 1);
    }

    public function test_successful_and_failed_uploads_are_recorded(): void
    {
        $upload = $this->queuedUpload(['A' => 9.99]);
        config()->set('wayfair_upload.mock_result', 'success');
        app(UploadWayfairPriceFile::class, ['uploadId' => $upload->id])->handle(
            app(WayfairUploadService::class),
            app(WayfairPriceFileGenerator::class),
            app(WayfairPriceFileValidator::class),
            app(WayfairUploadLogger::class),
            app(WayfairPriceUploadOrchestrator::class),
        );
        $upload->refresh();
        $this->assertSame(WayfairPriceUpload::SUCCESS, $upload->status);
        $this->assertSame('MOCK-'.$upload->id, $upload->wayfair_reference);
        $this->assertSame(1, $upload->successful_rows);

        $failed = $this->queuedUpload(['B' => 8.00]);
        config()->set('wayfair_upload.mock_result', 'permanent');
        app(UploadWayfairPriceFile::class, ['uploadId' => $failed->id])->handle(
            app(WayfairUploadService::class),
            app(WayfairPriceFileGenerator::class),
            app(WayfairPriceFileValidator::class),
            app(WayfairUploadLogger::class),
            app(WayfairPriceUploadOrchestrator::class),
        );
        $failed->refresh();
        $this->assertSame(WayfairPriceUpload::FAILED, $failed->status);
        $this->assertStringContainsString('Invalid template', (string) $failed->error_message);
    }

    public function test_retry_queues_a_failed_upload_and_stops_after_max_attempts(): void
    {
        Bus::fake();
        $failed = $this->queuedUpload(['C' => 4.50]);
        $failed->status = WayfairPriceUpload::FAILED;
        $failed->attempts = 1;
        $failed->error_message = 'Network timeout';
        $failed->save();

        $count = app(WayfairPriceUploadOrchestrator::class)->retryFailed($failed->id, false);
        $this->assertSame(1, $count);
        Bus::assertDispatched(UploadWayfairPriceFile::class);

        Bus::fake();
        $failed->refresh();
        $failed->status = WayfairPriceUpload::REQUIRES_REVIEW;
        $failed->save();
        $blocked = app(WayfairPriceUploadOrchestrator::class)->retryFailed($failed->id, false);
        $this->assertSame(0, $blocked);
        Bus::assertNotDispatched(UploadWayfairPriceFile::class);

        $approved = app(WayfairPriceUploadOrchestrator::class)->retryFailed($failed->id, true);
        $this->assertSame(1, $approved);
        Bus::assertDispatched(UploadWayfairPriceFile::class);
    }

    public function test_scheduler_lock_prevents_a_second_daily_run(): void
    {
        $orchestrator = app(WayfairPriceUploadOrchestrator::class);
        $first = $orchestrator->acquireDailyLock();
        $second = $orchestrator->acquireDailyLock();
        $this->assertNotNull($first);
        $this->assertNull($second);
        $first->release();
    }

    public function test_stuck_upload_can_be_recovered(): void
    {
        $upload = WayfairPriceUpload::query()->create([
            'status' => WayfairPriceUpload::UPLOADING,
            'filename' => 'stuck.csv',
            'file_type' => 'csv',
            'upload_started_at' => now()->subHours(2),
            'total_rows' => 1,
            'changed_rows' => 1,
        ]);

        $count = app(WayfairPriceUploadOrchestrator::class)->recoverStuck();
        $upload->refresh();
        $this->assertSame(1, $count);
        $this->assertSame(WayfairPriceUpload::STUCK, $upload->status);
    }

    public function test_abnormal_change_requires_review_and_does_not_queue(): void
    {
        Bus::fake();
        config()->set('wayfair_upload.max_price_change_percent', 10);
        WayfairPriceUpload::query()->create([
            'status' => WayfairPriceUpload::SUCCESS,
            'price_snapshot' => ['A' => 10, 'B' => 10],
            'total_rows' => 2,
            'changed_rows' => 2,
            'file_type' => 'csv',
        ]);

        $record = app(WayfairPriceUploadOrchestrator::class)->generate(true, false, [
            'A' => 20,
            'B' => 30,
        ], 'test');

        $this->assertSame(WayfairPriceUpload::REQUIRES_REVIEW, $record->status);
        $this->assertStringContainsString('abnormal price change', (string) $record->error_message);
        Bus::assertNotDispatched(UploadWayfairPriceFile::class);
    }

    public function test_credentials_are_not_written_to_logs_or_the_browser_script_output(): void
    {
        $logger = new WayfairUploadLogger();
        $scrubbed = $logger->scrub([
            'password' => 'super-secret',
            'upload_id' => 5,
            'error' => 'login failed password=super-secret',
        ]);
        $this->assertArrayNotHasKey('password', $scrubbed);
        $this->assertStringNotContainsString('super-secret', json_encode($scrubbed));
        $this->assertStringNotContainsString('super-secret', WayfairUploadLogger::redact('password=super-secret'));

        $script = file_get_contents(base_path('scripts/wayfair/upload-price-file.js'));
        $this->assertStringNotContainsString('console.log(input.password)', $script);
        $this->assertStringContainsString('input.password = \'\'', $script);

        config()->set('wayfair_upload.mode', 'mock');
        $this->assertInstanceOf(MockUploadAdapter::class, app(WayfairUploadService::class)->adapter());
        config()->set('wayfair_upload.mode', 'browser');
        $this->assertInstanceOf(BrowserUploadAdapter::class, app(WayfairUploadService::class)->adapter());
    }

    private function queuedUpload(array $prices): WayfairPriceUpload
    {
        $written = (new WayfairPriceFileGenerator())->write($prices, 'wayfair_price_'.md5(json_encode($prices).microtime(true)).'.csv');

        return WayfairPriceUpload::query()->create([
            'filename' => $written['filename'],
            'file_path' => $written['file_path'],
            'file_type' => 'csv',
            'file_sha256' => $written['file_sha256'],
            'status' => WayfairPriceUpload::QUEUED,
            'total_rows' => count($prices),
            'changed_rows' => count($prices),
            'price_snapshot' => $prices,
            'generated_at' => now(),
            'created_by' => 'test',
        ]);
    }
}
