<?php

namespace Tests\Unit;

use App\Console\Commands\EbayDilSbidAutoPushCommand;
use PHPUnit\Framework\TestCase;

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
}
