<?php

namespace Tests\Unit;

use App\Services\Support\AmazonPushPrcJobStore;
use Tests\TestCase;

class AmazonPushPrcJobStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/amazon-push-prc-'.bin2hex(random_bytes(4)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function test_cancel_does_not_block_the_same_s_prc(): void
    {
        $this->writeJob([
            'status' => 'running',
            'tasks' => [],
            'total' => 0,
            'failed_block' => [
                'DS CH BLU' => ['effective' => 29.99, 'error' => 'Cancelled by user.'],
            ],
        ]);

        $state = $this->store()->create([[
            'sku' => 'DS CH BLU',
            'std' => 34.99,
            'effective' => 29.99,
            'sale' => 29.99,
        ]]);

        $this->assertSame('running', $state['status']);
        $this->assertSame(1, $state['total']);
        $this->assertSame('pending', $state['tasks'][0]['status']);
        $this->assertSame(29.99, $state['tasks'][0]['effective']);
        $this->assertArrayNotHasKey('DS CH BLU', $state['failed_block']);
    }

    public function test_empty_running_job_does_not_stay_running(): void
    {
        $this->writeJob([
            'status' => 'running',
            'tasks' => [],
            'total' => 0,
            'last_message' => 'Push Prc queued (0 SKU(s)).',
        ]);

        $state = $this->store()->create([[
            'sku' => 'DS CH BLU',
            'std' => 0,
        ]]);

        $this->assertSame(0, $state['total']);
        $this->assertFalse($this->store()->isActive($this->store()->load()));
        $this->assertSame('idle', $this->store()->load()['status']);
    }

    public function test_amazon_reject_still_blocks_until_a_manual_retry(): void
    {
        $this->writeJob([
            'status' => 'failed',
            'tasks' => [[
                'sku' => 'DS CH BLU',
                'std' => 34.99,
                'effective' => 29.99,
                'status' => 'failed',
                'error' => 'Min Price cannot be higher than Sale Price',
            ]],
            'failed_block' => [
                'DS CH BLU' => ['effective' => 29.99, 'error' => 'Min Price cannot be higher than Sale Price'],
            ],
        ]);

        $blocked = $this->store()->create([[
            'sku' => 'DS CH BLU',
            'std' => 34.99,
            'effective' => 29.99,
        ]]);
        $this->assertSame(0, $blocked['total']);

        $store = $this->store();
        $store->forgetBlocked(['DS CH BLU']);
        $retried = $store->create([[
            'sku' => 'DS CH BLU',
            'std' => 34.99,
            'effective' => 29.99,
        ]]);

        $this->assertSame(1, $retried['total']);
        $this->assertSame('pending', $retried['tasks'][0]['status']);
    }

    private function store(): AmazonPushPrcJobStore
    {
        return AmazonPushPrcJobStore::atPath($this->path);
    }

    /** @param  array<string, mixed>  $state */
    private function writeJob(array $state): void
    {
        file_put_contents($this->path, json_encode($state));
    }
}
