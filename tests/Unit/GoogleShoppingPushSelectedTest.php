<?php

namespace Tests\Unit;

use App\Services\GoogleShoppingLiveMatchService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The select-and-push page: only the ticked campaigns are pushed, only where the live value differs,
 * SBGT 0 is never pushed, and every campaign gets a confirmed / not-matched result.
 */
class GoogleShoppingPushSelectedTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $grid = [];

    /** @var array<string, array{bid: ?float, bgt: ?float}> what Google Ads holds */
    private array $google = [];

    /** @var list<array{string, list<string>}> */
    private array $pushes = [];

    private bool $googleIgnoresPushes = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->grid = [
            '1' => ['sbid' => 0.75, 'sbgt' => 4, 'status' => 'ENABLED', 'name' => 'HW 18 WP', 'inventory' => 5],
            '2' => ['sbid' => null, 'sbgt' => 0, 'status' => 'ENABLED', 'name' => 'PARENT 18 FR', 'inventory' => 0],
            '3' => ['sbid' => 0.5, 'sbgt' => 3, 'status' => 'ENABLED', 'name' => 'ALREADY OK', 'inventory' => 5],
            '4' => ['sbid' => 0.5, 'sbgt' => 6, 'status' => 'PAUSED', 'name' => 'PAUSED ONE', 'inventory' => 5],
            '5' => ['sbid' => 0.5, 'sbgt' => 5, 'status' => 'ENABLED', 'name' => 'NOT SELECTED', 'inventory' => 5],
        ];
        $this->google = [
            '1' => ['bid' => 0.5, 'bgt' => 1.0],
            '2' => ['bid' => 0.5, 'bgt' => 5.0],
            '3' => ['bid' => 0.5, 'bgt' => 3.0],
            '4' => ['bid' => 0.1, 'bgt' => 1.0],
            '5' => ['bid' => 0.1, 'bgt' => 1.0],
        ];
        $this->pushes = [];
        $this->googleIgnoresPushes = false;
    }

    private function service(): GoogleShoppingLiveMatchService
    {
        $states = [];

        return new GoogleShoppingLiveMatchService([
            'targets' => fn () => $this->grid,
            'pull' => function (array $targets) use (&$states) {
                foreach ($targets as $cid => $_) {
                    $g = $this->google[$cid] ?? ['bid' => null, 'bgt' => null];
                    $states[(string) $cid] = ['live_bid' => $g['bid'], 'live_bgt' => $g['bgt'], 'bid_fetch_ok' => 1, 'bgt_fetch_ok' => 1];
                }
            },
            'states' => function (array $ids) use (&$states) {
                return array_intersect_key($states, array_flip($ids));
            },
            'pushBid' => function (array $ids) {
                $this->pushes[] = ['bid', $ids];
                foreach ($ids as $id) {
                    if (! $this->googleIgnoresPushes) {
                        $this->google[$id]['bid'] = (float) $this->grid[$id]['sbid'];
                    }
                }

                return ['ok' => true, 'message' => 'ok'];
            },
            'pushBgt' => function (array $ids) {
                $this->pushes[] = ['bgt', $ids];
                foreach ($ids as $id) {
                    if (! $this->googleIgnoresPushes) {
                        $this->google[$id]['bgt'] = (float) $this->grid[$id]['sbgt'];
                    }
                }

                return ['ok' => true, 'message' => 'ok'];
            },
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function byId(array $out): array
    {
        $map = [];
        foreach ($out['results'] as $r) {
            $map[$r['campaign_id']] = $r;
        }

        return $map;
    }

    public function test_only_selected_mismatching_campaigns_are_pushed_and_confirmed(): void
    {
        $out = $this->service()->pushSelected(['1', '2', '3', '4']);
        $res = $this->byId($out);

        $this->assertSame('matched', $res['1']['status']);
        $this->assertSame('blocked', $res['2']['status']);
        $this->assertSame('matched', $res['3']['status']);
        $this->assertSame('skipped', $res['4']['status']);
        $this->assertSame([['bid', ['1']], ['bgt', ['1']]], $this->pushes);
        $this->assertEqualsWithDelta(1.0, $this->google['5']['bgt'], 0.001, 'unselected campaign untouched');
        $this->assertEqualsWithDelta(5.0, $this->google['2']['bgt'], 0.001, 'SBGT 0 never pushed');
        $this->assertSame([], $out['push_errors']);
    }

    public function test_push_that_does_not_stick_is_reported_and_held_back_for_the_auto_job(): void
    {
        $this->googleIgnoresPushes = true;
        $res = $this->byId($this->service()->pushSelected(['1']));

        $this->assertSame('not_matched', $res['1']['status']);
        $this->assertTrue(Cache::has('google-shopping:match-failed:bgt:1'));
    }

    public function test_pushes_go_out_in_batches_of_ten(): void
    {
        $this->grid = [];
        $this->google = [];
        for ($i = 100; $i < 125; $i++) {
            $id = (string) $i;
            $this->grid[$id] = ['sbid' => null, 'sbgt' => 3, 'status' => 'ENABLED', 'name' => 'C'.$i, 'inventory' => 5];
            $this->google[$id] = ['bid' => null, 'bgt' => 1.0];
        }

        $this->service()->pushSelected(array_map('strval', array_keys($this->grid)));

        $this->assertSame([10, 10, 5], array_map(fn ($p) => count($p[1]), $this->pushes));
    }

    public function test_mismatch_rows_lists_what_the_message_lists(): void
    {
        $out = $this->service()->mismatchRows(true);
        $byCid = [];
        foreach ($out['rows'] as $r) {
            $byCid[$r['cid']] = $r;
        }

        $this->assertArrayNotHasKey('3', $byCid);
        $this->assertArrayNotHasKey('4', $byCid, 'PAUSED campaigns are not listed');
        $this->assertTrue($byCid['1']['can_push_bgt']);
        $this->assertTrue($byCid['2']['blocked']);
        $this->assertTrue($byCid['5']['can_push_bgt']);
    }
}
