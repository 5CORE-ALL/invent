<?php

namespace Tests\Unit;

use App\Support\UserMissedYesterday;
use PHPUnit\Framework\TestCase;

class UserMissedYesterdayTest extends TestCase
{
    public function test_message_lists_yesterdays_missed_tasks(): void
    {
        $body = UserMissedYesterday::formatBody([
            'Verify shipping rates [Auto: 01-Oct-26]',
            'Handle customer care',
        ], '1 Oct');

        $this->assertStringContainsString('You missed 2 tasks yesterday (1 Oct).', $body);
        $this->assertStringContainsString('• Verify shipping rates', $body);
        $this->assertStringContainsString('• Handle customer care', $body);
        $this->assertStringNotContainsString('[Auto:', $body);
        $this->assertStringContainsString('/tasks', $body);
    }

    public function test_empty_list_sends_nothing(): void
    {
        $this->assertNull(UserMissedYesterday::formatBody(['', '   '], '1 Oct'));
    }

    public function test_long_list_is_capped(): void
    {
        $titles = [];
        for ($i = 1; $i <= 18; $i++) {
            $titles[] = 'Task '.$i;
        }

        $body = UserMissedYesterday::formatBody($titles, '1 Oct');

        $this->assertStringContainsString('You missed 18 tasks yesterday (1 Oct).', $body);
        $this->assertStringContainsString('• Task 15', $body);
        $this->assertStringNotContainsString('• Task 16', $body);
        $this->assertStringContainsString('• +3 more', $body);
    }
}
