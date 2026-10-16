<?php

namespace Tests\Feature;

use App\Services\EbayApiService;
use App\Services\Ebay1VolumePricingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Runs the real push logic against a fake eBay: no network, no database.
 */
class EbayVolumePricingPushTest extends TestCase
{
    private const KEY_A = '2=2|3=8|4=15';

    private const KEY_B = '2=6|3=13|4=20';

    /** @var array<string, array<string, mixed>> */
    private array $promos = [];

    /** @var list<string> */
    private array $calls = [];

    private function service(): Ebay1VolumePricingService
    {
        $ebay = new class extends EbayApiService
        {
            public function __construct() {}

            public function generateBearerToken()
            {
                return 'test-token';
            }
        };

        return new Ebay1VolumePricingService($ebay);
    }

    private function rules(array $extra = []): array
    {
        return array_merge([
            'enabled' => true,
            'weight' => [],
            'npft' => [],
            'dil' => [
                ['min' => 0, 'max' => 50, 'buy2' => 2, 'buy3' => 8, 'buy4' => 15],
                ['min' => 50, 'max' => 100, 'buy2' => 6, 'buy3' => 13, 'buy4' => 20],
            ],
            'promotion_ids' => [],
            'promotion_state' => [],
        ], $extra);
    }

    private function promo(string $id, string $name, array $listings, string $status = 'RUNNING'): void
    {
        $this->promos[$id] = [
            'promotionId' => $id,
            'name' => $name,
            'promotionStatus' => $status,
            'startDate' => '2026-10-16T00:44:20.000Z',
            'inventoryCriterion' => ['inventoryCriterionType' => 'INVENTORY_BY_VALUE', 'listingIds' => $listings],
        ];
    }

    private function fakeEbay(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            $method = $request->method();
            preg_match('#item_promotion/([^/]+)(?:/(pause|resume))?$#', rawurldecode($url), $m);
            $id = $m[1] ?? '';
            $action = $m[2] ?? '';

            if ($method === 'POST' && $id === '') {
                $new = 'NEW'.(count($this->promos) + 1).'@EBAY_US';
                $body = $request->data();
                $this->calls[] = 'POST create';
                $this->promos[$new] = [
                    'promotionId' => $new,
                    'name' => $body['name'],
                    'promotionStatus' => 'SCHEDULED',
                    'startDate' => $body['startDate'],
                    'inventoryCriterion' => $body['inventoryCriterion'],
                ];

                return Http::response('', 201, ['Location' => 'https://api.ebay.com/sell/marketing/v1/item_promotion/'.$new]);
            }
            if ($method === 'GET') {
                return isset($this->promos[$id]) ? Http::response($this->promos[$id], 200) : Http::response([], 404);
            }
            if ($method === 'POST' && $action === 'pause') {
                $this->calls[] = "pause {$id}";
                $this->promos[$id]['promotionStatus'] = 'PAUSED';

                return Http::response('', 204);
            }
            if ($method === 'POST' && $action === 'resume') {
                $this->calls[] = "resume {$id}";
                $this->promos[$id]['promotionStatus'] = 'RUNNING';

                return Http::response('', 204);
            }
            if ($method === 'PUT') {
                $ids = array_map('strval', $request->data()['inventoryCriterion']['listingIds']);
                $this->calls[] = 'PUT '.$id.' ['.implode(',', $ids).']';
                $this->promos[$id]['inventoryCriterion']['listingIds'] = $ids;

                return Http::response('', 204);
            }

            return Http::response([], 500);
        });
    }

    private function row(string $sku, string $item, float $dil): array
    {
        return ['sku' => $sku, 'item_id' => $item, 'weight_lb' => null, 'dil' => $dil, 'npft' => null];
    }

    public function test_moving_listing_leaves_its_old_promotion_before_joining_the_new_one(): void
    {
        $this->promo('A@EBAY_US', 'A', ['L1', 'L2']);
        $this->promo('B@EBAY_US', 'B', ['L3']);
        $this->fakeEbay();

        // L1's Dil went up (tier A -> tier B). L2 stays in A, L3 stays in B.
        $rows = [$this->row('s1', 'L1', 60), $this->row('s2', 'L2', 10), $this->row('s3', 'L3', 60)];
        $rules = $this->rules(['promotion_ids' => [self::KEY_A => 'A@EBAY_US', self::KEY_B => 'B@EBAY_US']]);

        $result = $this->service()->push($rules, $rows, false, false);

        $this->assertTrue($result['success'], json_encode($result));
        $puts = array_values(array_filter($this->calls, fn ($c) => str_starts_with($c, 'PUT ')));
        $this->assertSame('PUT A@EBAY_US [L2]', $puts[0], 'L1 must leave A first');
        $this->assertContains('PUT B@EBAY_US [L1,L3]', $puts, 'then L1 joins B');
        $this->assertSame(['L2'], $this->promos['A@EBAY_US']['inventoryCriterion']['listingIds']);
        $this->assertEqualsCanonicalizing(['L1', 'L3'], $this->promos['B@EBAY_US']['inventoryCriterion']['listingIds']);
        $this->assertSame('RUNNING', $this->promos['A@EBAY_US']['promotionStatus']);
        $this->assertSame('RUNNING', $this->promos['B@EBAY_US']['promotionStatus']);
        $this->assertEqualsCanonicalizing(['A@EBAY_US', 'B@EBAY_US'], array_values($result['promotion_ids']));
        $this->assertCount(2, $result['promotion_state']);
    }

    public function test_second_run_with_nothing_changed_sends_no_updates(): void
    {
        $this->promo('A@EBAY_US', 'A', ['L1', 'L2']);
        $this->promo('B@EBAY_US', 'B', ['L3']);
        $this->fakeEbay();
        $rows = [$this->row('s1', 'L1', 60), $this->row('s2', 'L2', 10), $this->row('s3', 'L3', 60)];
        $rules = $this->rules(['promotion_ids' => [self::KEY_A => 'A@EBAY_US', self::KEY_B => 'B@EBAY_US']]);

        $first = $this->service()->push($rules, $rows, false, false);
        $this->calls = [];
        $rules['promotion_ids'] = $first['promotion_ids'];
        $rules['promotion_state'] = $first['promotion_state'];
        $second = $this->service()->push($rules, $rows, false, true);

        $this->assertTrue($second['success']);
        $this->assertSame([], $this->calls, 'no pause/PUT/resume when nothing changed');
        $this->assertSame(2, $second['unchanged']);
        $this->assertStringContainsString('Nothing changed', $second['message']);
    }

    public function test_listing_whose_discount_drops_to_zero_is_taken_out_and_empty_promotion_is_paused(): void
    {
        $this->promo('A@EBAY_US', 'A', ['L1']);
        $this->fakeEbay();
        // Dil range with all zeros: L1 no longer has a volume discount.
        $rules = $this->rules([
            'dil' => [['min' => 0, 'max' => 100, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0]],
            'promotion_ids' => [self::KEY_A => 'A@EBAY_US'],
        ]);

        $result = $this->service()->push($rules, [$this->row('s1', 'L1', 10)], false, false);

        $this->assertContains('pause A@EBAY_US', $this->calls);
        $this->assertSame([], $result['promotion_ids']);
    }

    public function test_partial_push_keeps_other_promotions_and_adds_to_existing_one(): void
    {
        $this->promo('A@EBAY_US', 'A', ['L1', 'L2']);
        $this->promo('B@EBAY_US', 'B', ['L3']);
        $this->fakeEbay();
        $rules = $this->rules(['promotion_ids' => [self::KEY_A => 'A@EBAY_US', self::KEY_B => 'B@EBAY_US']]);

        // Only a new listing L9 (tier A) is pushed.
        $result = $this->service()->push($rules, [$this->row('s9', 'L9', 10)], true, false);

        $this->assertTrue($result['success'], json_encode($result));
        $this->assertEqualsCanonicalizing(['L1', 'L2', 'L9'], $this->promos['A@EBAY_US']['inventoryCriterion']['listingIds']);
        $this->assertSame(['L3'], $this->promos['B@EBAY_US']['inventoryCriterion']['listingIds']);
        $this->assertSame('RUNNING', $this->promos['B@EBAY_US']['promotionStatus']);
        $this->assertSame(self::KEY_B, array_search('B@EBAY_US', $result['promotion_ids'], true));
    }

    public function test_paused_promotion_is_resumed_after_update(): void
    {
        $this->promo('A@EBAY_US', 'A', ['L1'], 'PAUSED');
        $this->fakeEbay();
        $rules = $this->rules(['promotion_ids' => [self::KEY_A => 'A@EBAY_US']]);

        $result = $this->service()->push($rules, [$this->row('s1', 'L1', 10), $this->row('s2', 'L2', 10)], false, false);

        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame('RUNNING', $this->promos['A@EBAY_US']['promotionStatus']);
    }
}
