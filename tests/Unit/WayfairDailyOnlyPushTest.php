<?php

namespace Tests\Unit;

use App\Services\DilRuleSpriceApplyService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WayfairDailyOnlyPushTest extends TestCase
{
    public function test_wayfair_is_a_daily_only_channel(): void
    {
        $this->assertSame(['wayfair'], DilRuleSpriceApplyService::DAILY_ONLY_CHANNELS);
    }

    public function test_dil_rule_apply_skips_wayfair_without_daily_flag(): void
    {
        $code = Artisan::call('dil:rule-sprice-apply', ['channel' => 'wayfair', '--push' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('skipped', Artisan::output());
    }

    public function test_daily_push_command_skips_wayfair_without_daily_flag(): void
    {
        $code = Artisan::call('channel:push-sprice-daily', ['channel' => 'wayfair']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('skipped', Artisan::output());
    }

    public function test_wayfair_is_scheduled_once_a_day_at_midnight_pacific(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'wayfair --push --daily')
                || str_contains((string) $e->command, 'wayfair:daily-price-upload'));

        $push = $events->first(fn ($e) => str_contains((string) $e->command, 'wayfair --push --daily'));
        $this->assertNotNull($push, 'Wayfair daily push is not scheduled');
        $this->assertSame('0 0 * * *', $push->expression);
        $this->assertSame('America/Los_Angeles', (string) $push->timezone);

        $all = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'dil:rule-sprice-apply wayfair'));
        $this->assertCount(1, $all, 'Wayfair must have exactly one dil:rule-sprice-apply schedule');

        $upload = $events->first(fn ($e) => str_contains((string) $e->command, 'wayfair:daily-price-upload'));
        $this->assertNotNull($upload);
        $this->assertSame('30 0 * * *', $upload->expression);
    }
}
