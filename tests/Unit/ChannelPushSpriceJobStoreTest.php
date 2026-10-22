<?php

namespace Tests\Unit;

use App\Services\Support\ChannelPushSpriceJobStore;
use Tests\TestCase;

class ChannelPushSpriceJobStoreTest extends TestCase
{
    private string $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = 'wayfairtest'.bin2hex(random_bytes(3));
    }

    protected function tearDown(): void
    {
        $dir = storage_path('app/'.$this->channel.'-push-sprice');
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    public function test_create_keeps_one_task_per_sku(): void
    {
        $store = ChannelPushSpriceJobStore::for($this->channel);

        $state = $store->create([
            ['sku' => 'AB 1', 'price' => 10.00],
            ['sku' => 'ab 1', 'price' => 11.00],
            ['sku' => 'CD 2', 'price' => 5.00],
        ]);

        $this->assertSame(2, $state['total']);
    }

    public function test_append_does_not_requeue_a_sku_already_pushed_at_the_same_price(): void
    {
        $store = ChannelPushSpriceJobStore::for($this->channel);
        $store->create([
            ['sku' => 'AB 1', 'price' => 10.00],
            ['sku' => 'CD 2', 'price' => 5.00],
        ]);
        $store->update(function (array $s) {
            $s['tasks'][0]['status'] = 'ok';
            $s['tasks'][1]['status'] = 'pending';

            return $s;
        });

        // The reload scan runs again while the job works: AB 1 still looks blue on the page.
        $state = $store->append([
            ['sku' => 'AB 1', 'price' => 10.00],
            ['sku' => 'CD 2', 'price' => 5.00],
        ]);

        $this->assertSame(2, $state['total']);
    }

    public function test_append_requeues_a_pushed_sku_when_the_s_prc_changed(): void
    {
        $store = ChannelPushSpriceJobStore::for($this->channel);
        $store->create([['sku' => 'AB 1', 'price' => 10.00]]);
        $store->update(function (array $s) {
            $s['tasks'][0]['status'] = 'ok';

            return $s;
        });

        $state = $store->append([['sku' => 'AB 1', 'price' => 12.50]]);

        $this->assertSame(2, $state['total']);
    }
}
