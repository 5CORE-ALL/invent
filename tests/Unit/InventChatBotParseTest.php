<?php

namespace Tests\Unit;

use App\Support\InventChatBot;
use PHPUnit\Framework\TestCase;

class InventChatBotParseTest extends TestCase
{
    public function test_parses_create_task_natural_language(): void
    {
        $parsed = InventChatBot::parse('Create a task Buy packing tape @aman tomorrow');

        $this->assertSame('task', $parsed['command']);
        $this->assertStringContainsString('Buy packing tape', $parsed['args']);
    }

    public function test_parses_complete_assign_and_deadline(): void
    {
        $this->assertSame('complete', InventChatBot::parse('Complete task 123')['command']);
        $this->assertSame('assign', InventChatBot::parse('Assign task 123 @aman')['command']);
        $this->assertSame('deadline', InventChatBot::parse('Change deadline 123 tomorrow')['command']);
    }

    public function test_parses_overdue_and_today_dar(): void
    {
        $this->assertSame('overdue', InventChatBot::parse('Show my overdue')['command']);
        $this->assertSame('dar', InventChatBot::parse("Show today's DAR")['command']);
        $this->assertSame('overdue', InventChatBot::parse('Show @aman overdue')['command']);
    }

    public function test_slash_commands_still_work(): void
    {
        $this->assertSame('task', InventChatBot::parse('/task buy tape')['command']);
        $this->assertSame('help', InventChatBot::parse('/help')['command']);
    }
}
