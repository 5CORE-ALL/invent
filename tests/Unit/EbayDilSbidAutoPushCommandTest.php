<?php

namespace Tests\Unit;

use App\Console\Commands\EbayDilSbidAutoPushCommand;
use App\Support\DilVsSbidAutoPush;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class EbayDilSbidAutoPushCommandTest extends TestCase
{
    public function test_each_account_uses_its_own_lock(): void
    {
        $this->assertSame('ebay-dil-sbid-auto-push-ebay1', EbayDilSbidAutoPushCommand::lockName('ebay1'));
        $this->assertSame('ebay-dil-sbid-auto-push-ebay2', EbayDilSbidAutoPushCommand::lockName('ebay2'));
        $this->assertNotSame(
            EbayDilSbidAutoPushCommand::lockName('ebay1'),
            EbayDilSbidAutoPushCommand::lockName('ebay2')
        );
    }

    public function test_command_can_push_only_stored_c_bid_mismatches(): void
    {
        $signature = new ReflectionProperty(EbayDilSbidAutoPushCommand::class, 'signature');
        $signature->setAccessible(true);

        $this->assertStringContainsString('--mismatch', (string) $signature->getValue(new EbayDilSbidAutoPushCommand()));
    }

    public function test_data_change_spawn_lock_is_per_account(): void
    {
        $this->assertSame('ebay-dil-sbid-spawn-ebay2', DilVsSbidAutoPush::spawnLockName('ebay2'));
        $this->assertNotSame(
            DilVsSbidAutoPush::spawnLockName('ebay1'),
            DilVsSbidAutoPush::spawnLockName('ebay2')
        );
    }
}
