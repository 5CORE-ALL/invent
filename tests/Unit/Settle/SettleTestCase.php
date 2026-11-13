<?php

namespace Tests\Unit\Settle;

use App\Support\Marketplace\Settle\Verification\VerificationContext;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs every settle test on a private in-memory SQLite database. The app's real (MySQL) connection
 * is never used, and nothing here writes to a real table.
 */
abstract class SettleTestCase extends TestCase
{
    protected const DATE = '2026-11-12';

    protected function setUp(): void
    {
        // Booting the app parses the very large routes/web.php (~105 MB on its own); give the settle
        // suite headroom so it does not depend on the machine's php.ini.
        if ((int) ini_get('memory_limit') !== -1 && (int) ini_get('memory_limit') < 512) {
            ini_set('memory_limit', '512M');
        }
        parent::setUp();
        config([
            'database.default' => 'settle_test',
            'database.connections.settle_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'settle.alert_emails' => [],
            'settle.alert_webhook' => null,
            'settle.require_verification' => true,
        ]);
        DB::purge('settle_test');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'settle tests must never run on the real database');

        (include base_path('database/migrations/2026_11_13_060000_create_settle_tracking_tables.php'))->up();
    }

    protected function tearDown(): void
    {
        @unlink(Ledger::path(self::DATE));
        Carbon::setTestNow();
        DB::purge('settle_test');
        parent::tearDown();
        gc_collect_cycles(); // keep the whole settle suite under the default 128M php memory_limit
    }

    protected function ctx(string $key = 'amazon_close', bool $finalPassDone = true, ?string $doneAt = null, ?callable $taskDone = null): VerificationContext
    {
        return new VerificationContext($key, Ledger::tasks()[$key], $finalPassDone, $doneAt, Carbon::parse(self::DATE.' 06:00', 'America/Los_Angeles')->addDay(), $taskDone ?? fn () => true);
    }

    protected function makeAmazonTables(): void
    {
        Schema::create('amazon_orders', function (Blueprint $t) {
            $t->id();
            $t->string('amazon_order_id');
            $t->dateTime('order_date');
            $t->string('status')->nullable();
        });
        Schema::create('amazon_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('amazon_order_id');
            $t->integer('quantity')->default(1);
            $t->decimal('price', 10, 2)->nullable();
        });
    }

    /**
     * Inserts $n Amazon orders (stored in UTC) at 12:00 Pacific of $ymd, each with one item.
     *
     * @param  array{zeroPrice?: int, noItems?: int, dupIds?: int, hour?: int}  $o
     */
    protected function seedAmazonDay(string $ymd, int $n, array $o = []): void
    {
        $utc = Carbon::parse($ymd.' '.($o['hour'] ?? 12).':00', 'America/Los_Angeles')->utc()->format('Y-m-d H:i:s');
        for ($i = 0; $i < $n; $i++) {
            $id = DB::table('amazon_orders')->insertGetId(['amazon_order_id' => $ymd.'-'.$i, 'order_date' => $utc, 'status' => 'Shipped']);
            if ($i < ($o['noItems'] ?? 0)) {
                continue;
            }
            DB::table('amazon_order_items')->insert([
                'amazon_order_id' => $id,
                'price' => $i < ($o['zeroPrice'] ?? 0) ? 0 : 10,
            ]);
        }
        for ($i = 0; $i < ($o['dupIds'] ?? 0); $i++) {
            DB::table('amazon_orders')->insert(['amazon_order_id' => $ymd.'-'.$i, 'order_date' => $utc, 'status' => 'Shipped']);
        }
    }

    protected function seedAmazonBaseline(int $perDay = 20): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->seedAmazonDay(Carbon::parse(self::DATE)->subDays($i)->toDateString(), $perDay);
        }
    }

    protected function codes(array $checks, string $status): array
    {
        return array_values(array_map(fn ($c) => $c['code'], array_filter($checks, fn ($c) => $c['status'] === $status)));
    }
}
