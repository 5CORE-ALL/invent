<?php

namespace Tests\Feature;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Models\ChannelTabulatorColumnSetting;
use App\Support\DilRuleModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Save All (every page of one rule model) vs Save Only (this page). Runs on in-memory SQLite, never on the real DB.
 */
class DilRuleSaveAllOnlyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('channel_tabulator_column_settings', function ($t) {
            $t->id();
            $t->string('channel_name', 160)->unique();
            $t->json('visibility')->nullable();
            $t->json('column_order')->nullable();
            $t->timestamps();
        });
    }

    private function dilRow(string $channel): ?array
    {
        $r = ChannelTabulatorColumnSetting::query()->where('channel_name', $channel.'_dil_vs_groi')->first();

        return $r ? ['visibility' => $r->visibility, 'column_order' => $r->column_order] : null;
    }

    /** @return array<string,mixed> */
    private function seedRow(string $channel, float $groi, float $clearance): void
    {
        ChannelTabulatorColumnSetting::query()->create([
            'channel_name' => $channel.'_dil_vs_groi',
            'visibility' => [
                'rules' => [['key' => '0.1-5', 'label' => '0.1–5%', 'min' => 0.1, 'max' => 5, 'groi' => $groi]],
                'age_adj' => [['min' => 0, 'max' => 30, 'points' => 1]],
                'clearance_nroi' => $clearance,
            ],
            'column_order' => ['0.1-5'],
        ]);
    }

    private function savePayload(): array
    {
        return [
            'rules' => [
                ['key' => '0.1-5', 'label' => '0.1–5%', 'min' => 0.1, 'max' => 5, 'groi' => 61],
                ['key' => '5-10', 'label' => '5–10%', 'min' => 5, 'max' => 10, 'groi' => 62],
            ],
            'age_adj' => [['min' => 0, 'max' => 90, 'points' => 7]],
            'diff_adj' => ['gt_pct' => 25, 'points' => 4],
            'clearance_nroi' => 33,
        ];
    }

    private function savePage(string $method, string $channel): array
    {
        $req = Request::create('/x', 'POST', $this->savePayload());
        $res = app(ChannelPromoPricingController::class)->{$method}($req, $channel);

        return ['status' => $res->getStatusCode(), 'body' => $res->getData(true)];
    }

    public function test_save_all_writes_every_page_of_the_model_and_nothing_else(): void
    {
        $model = DilRuleModels::modelFor('ebay1');
        $others = ['purchasing_power', 'macys', 'ebay2op'];   // three other models
        foreach ($model['channels'] as $c) {
            $this->seedRow($c, 40, 11.5);
        }
        foreach ($others as $c) {
            $this->seedRow($c, 40, 11.5);
        }
        ChannelTabulatorColumnSetting::query()->create(['channel_name' => 'ebay1_something_else', 'visibility' => ['keep' => 1], 'column_order' => []]);
        $beforeOthers = array_map(fn ($c) => $this->dilRow($c), $others);

        $out = $this->savePage('saveDilGroiRulesAll', 'ebay1');

        $this->assertSame(200, $out['status']);
        $this->assertEqualsCanonicalizing($model['channels'], $out['body']['channels']);
        foreach ($model['channels'] as $c) {
            $row = $this->dilRow($c);
            $slabs = array_values(array_filter($row['visibility']['rules'], fn ($r) => $r['min'] > 0));
            $this->assertSame([61.0, 62.0], array_map(fn ($r) => (float) $r['groi'], $slabs), $c.' slabs');
            $this->assertSame(7, (int) $row['visibility']['age_adj'][0]['points'], $c.' age');
            $this->assertSame(25, (int) $row['visibility']['diff_adj']['gt_pct'], $c.' diff');
            $this->assertSame(['0.1-5', '5-10'], array_values(array_intersect($row['column_order'], ['0.1-5', '5-10'])), $c.' order');
            // The clicked page takes the value typed in the modal; every other page keeps its own.
            $this->assertEquals($c === 'ebay1' ? 33 : 11.5, $row['visibility']['clearance_nroi'], $c.' clearance');
        }
        // "After refresh": the GET each page loads its modal from returns the saved Save All rules.
        foreach ($model['channels'] as $c) {
            $loaded = app(ChannelPromoPricingController::class)->dilGroiRules(Request::create('/x'), $c)->getData(true);
            $this->assertFalse($loaded['is_default'], $c.' loads saved rules, not defaults');
            $this->assertSame(7, (int) $loaded['age_adj'][0]['points'], $c.' reload age');
            $this->assertContains(62.0, array_map(fn ($r) => (float) $r['groi'], $loaded['rules']), $c.' reload slabs');
        }
        foreach ($others as $i => $c) {
            $this->assertSame($beforeOthers[$i], $this->dilRow($c), $c.' belongs to another model and must not change');
        }
        $this->assertSame(['keep' => 1], ChannelTabulatorColumnSetting::query()->where('channel_name', 'ebay1_something_else')->first()->visibility);
    }

    public function test_save_only_writes_this_page_only(): void
    {
        $model = DilRuleModels::modelFor('ebay2');
        foreach ($model['channels'] as $c) {
            $this->seedRow($c, 40, 11.5);
        }
        $before = [];
        foreach ($model['channels'] as $c) {
            $before[$c] = $this->dilRow($c);
        }

        $out = $this->savePage('saveDilGroiRules', 'ebay2');

        $this->assertSame(200, $out['status']);
        foreach ($model['channels'] as $c) {
            if ($c === 'ebay2') {
                $this->assertNotSame($before[$c], $this->dilRow($c));
                $this->assertEquals(33, $this->dilRow($c)['visibility']['clearance_nroi']);
                continue;
            }
            $this->assertSame($before[$c], $this->dilRow($c), $c.' must not change on Save Only');
        }
    }

    public function test_save_all_creates_missing_rows_and_rejects_pages_outside_any_model(): void
    {
        $out = $this->savePage('saveDilGroiRulesAll', 'temu3');
        $this->assertSame(200, $out['status']);
        foreach (DilRuleModels::channelsFor('temu3') as $c) {
            $this->assertNotNull($this->dilRow($c), $c.' row created');
            if ($c !== 'temu3') {
                $this->assertNull($this->dilRow($c)['visibility']['clearance_nroi'] ?? null, $c.' gets no clearance it never had');
            }
        }
        $this->assertNull($this->dilRow('purchasing_power'));

        $bad = $this->savePage('saveDilGroiRulesAll', 'pef');
        $this->assertSame(422, $bad['status']);
    }
}
