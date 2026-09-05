<?php

namespace Tests\Feature;

use App\Models\Crm\Customer;
use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use App\Models\User;
use App\Services\Crm\Contracts\ShopifyServiceInterface;
use App\Services\Crm\ShopifyCustomerClassifier;
use App\Services\Crm\WhatsAppAvailabilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Tests for Shopify customer sync + customers data API.
 *
 * Two groups:
 *  A) Unit-style  — mock the ShopifyServiceInterface so NO real HTTP call is made.
 *                   Safe to run in CI / without real Shopify credentials.
 *
 *  B) Feature     — hit the /crm/shopify/customers/data endpoint against the
 *                   real DB and verify shape + tags extraction.
 *
 * Run all:
 *   php artisan test tests/Feature/ShopifyCustomerSyncTest.php
 *
 * Run single test:
 *   php artisan test --filter test_sync_upserts_customer_with_tags
 */
class ShopifyCustomerSyncTest extends TestCase
{
    use DatabaseTransactions;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function actingUser(): User
    {
        $user = User::query()->first();

        return $user ?? User::factory()->create();
    }

    private function createTypedShopifyCustomer(
        int $shopifyCustomerId,
        string $email,
        string $customerType,
        ?string $marketplaceChannel = null,
        string $tags = '',
        ?string $phone = null
    ): ShopifyCustomer {
        $attributes = [
            'shopify_customer_id' => $shopifyCustomerId,
            'email' => $email,
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => $phone,
            'sync_status' => 'synced',
            'customer_type' => $customerType,
            'marketplace_channel' => $marketplaceChannel,
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => $shopifyCustomerId,
                'email' => $email,
                'tags' => $tags,
            ]),
        ];

        $existing = ShopifyCustomer::query()->where('shopify_customer_id', $shopifyCustomerId)->first();
        if ($existing) {
            $existing->fill($attributes)->save();

            return $existing;
        }

        $customer = new ShopifyCustomer($attributes);
        $customer->incrementing = false;
        $customer->id = $shopifyCustomerId;
        $customer->save();

        return $customer;
    }

    private function createShopifyOrder(
        int $shopifyOrderId,
        int $shopifyCustomerId,
        float $totalPrice,
        int $lineItemsCount
    ): ShopifyOrder {
        $order = new ShopifyOrder([
            'shopify_order_id' => $shopifyOrderId,
            'shopify_customer_id' => $shopifyCustomerId,
            'total_price' => $totalPrice,
            'line_items_count' => $lineItemsCount,
            'currency' => 'USD',
            'order_status' => 'paid',
            'order_date' => now(),
            'raw_payload' => [
                'line_items' => [['quantity' => $lineItemsCount]],
            ],
        ]);
        $order->incrementing = false;
        $order->id = $shopifyOrderId;
        $order->save();

        return $order;
    }

    /** Sample Shopify REST customer object (same shape as real API response). */
    private function sampleApiCustomer(array $overrides = []): array
    {
        return array_merge([
            'id'                        => 9_900_000_001,
            'email'                     => 'test.shopify@example.com',
            'first_name'                => 'Test',
            'last_name'                 => 'Customer',
            'phone'                     => '+1-555-0100',
            'tags'                      => 'VIP, wholesale, repeat-buyer',
            'verified_email'            => true,
            'orders_count'              => 3,
            'total_spent'               => '299.00',
            'currency'                  => 'USD',
            'created_at'                => '2024-01-15T10:00:00-05:00',
            'updated_at'                => '2024-06-01T12:00:00-05:00',
            'note'                      => null,
            'state'                     => 'enabled',
            'default_address'           => [
                'address1'  => '123 Main St',
                'city'      => 'New York',
                'country'   => 'United States',
                'phone'     => '+1-555-0100',
                'zip'       => '10001',
            ],
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // A) Sync tests — mock HTTP, test upsert logic + raw_payload storage
    // -----------------------------------------------------------------------

    /**
     * syncCustomers() upserts a row and stores the full API payload including tags.
     */
    public function test_sync_upserts_customer_with_tags(): void
    {
        $apiRow = $this->sampleApiCustomer();

        // Mock the service so no real HTTP call happens
        $mock = $this->mock(ShopifyServiceInterface::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('syncCustomers')->once()->andReturnUsing(function () use ($apiRow) {
            // Replicate what ShopifyService::upsertCustomerRow does
            ShopifyCustomer::query()->updateOrCreate(
                ['shopify_customer_id' => (int) $apiRow['id']],
                [
                    'email'          => $apiRow['email'],
                    'first_name'     => $apiRow['first_name'],
                    'last_name'      => $apiRow['last_name'],
                    'phone'          => $apiRow['phone'],
                    'sync_status'    => 'synced',
                    'last_synced_at' => now(),
                    'raw_payload'    => $apiRow,
                ]
            );

            return 1;
        });

        $service = app(ShopifyServiceInterface::class);
        $count   = $service->syncCustomers();

        $this->assertSame(1, $count);

        $record = ShopifyCustomer::query()
            ->where('shopify_customer_id', $apiRow['id'])
            ->first();

        $this->assertNotNull($record, 'ShopifyCustomer row should be created.');
        $this->assertSame($apiRow['email'], $record->email);
        $this->assertSame('synced', $record->sync_status);

        // raw_payload is cast to array — tags must be present
        $this->assertIsArray($record->raw_payload);
        $this->assertArrayHasKey('tags', $record->raw_payload);
        $this->assertSame('VIP, wholesale, repeat-buyer', $record->raw_payload['tags']);
    }

    /**
     * syncCustomers() handles a customer with NO tags gracefully (empty string).
     */
    public function test_sync_handles_customer_with_no_tags(): void
    {
        $apiRow = $this->sampleApiCustomer([
            'id'    => 9_900_000_002,
            'email' => 'notags@example.com',
            'tags'  => '',
        ]);

        $mock = $this->mock(ShopifyServiceInterface::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('syncCustomers')->once()->andReturnUsing(function () use ($apiRow) {
            ShopifyCustomer::query()->updateOrCreate(
                ['shopify_customer_id' => (int) $apiRow['id']],
                [
                    'email'          => $apiRow['email'],
                    'first_name'     => $apiRow['first_name'],
                    'last_name'      => $apiRow['last_name'],
                    'phone'          => $apiRow['phone'],
                    'sync_status'    => 'synced',
                    'last_synced_at' => now(),
                    'raw_payload'    => $apiRow,
                ]
            );

            return 1;
        });

        app(ShopifyServiceInterface::class)->syncCustomers();

        $record = ShopifyCustomer::query()
            ->where('shopify_customer_id', $apiRow['id'])
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('', $record->raw_payload['tags']);
    }

    // -----------------------------------------------------------------------
    // B) Feature tests — real DB, no Shopify HTTP call needed
    // -----------------------------------------------------------------------

    /**
     * Guest cannot access the customers data endpoint.
     */
    public function test_guest_cannot_access_customers_data_endpoint(): void
    {
        $response = $this->getJson(route('crm.shopify.customers.data'));

        $response->assertStatus(401);
    }

    /**
     * Authenticated user gets a paginated JSON response with the correct shape.
     */
    public function test_customers_data_endpoint_returns_correct_shape(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);
        $this->assertIsArray($response->json('data'));
    }

    /**
     * Every row returned by the data endpoint has a 'tags' key (array).
     */
    public function test_customers_data_endpoint_rows_contain_tags_key(): void
    {
        // Seed one fake customer with tags in raw_payload
        ShopifyCustomer::query()->updateOrCreate(
            ['shopify_customer_id' => 9_900_000_003],
            [
                'email'          => 'tagged@example.com',
                'first_name'     => 'Tag',
                'last_name'      => 'User',
                'phone'          => null,
                'sync_status'    => 'synced',
                'last_synced_at' => now(),
                'raw_payload'    => $this->sampleApiCustomer([
                    'id'    => 9_900_000_003,
                    'email' => 'tagged@example.com',
                    'tags'  => 'VIP, wholesale',
                ]),
            ]
        );

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?q=tagged%40example.com');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Seeded customer should appear in results.');

        foreach ($data as $row) {
            $this->assertArrayHasKey('tags', $row, 'Each row must have a tags key.');
            $this->assertIsArray($row['tags'], 'tags must be an array.');
        }

        // The seeded row should have exactly 2 tags
        $seededRow = collect($data)->firstWhere('email', 'tagged@example.com');
        $this->assertNotNull($seededRow);
        $this->assertSame(['VIP', 'wholesale'], $seededRow['tags']);
    }

    public function test_customers_data_includes_orders_qty_and_revenue(): void
    {
        $withOrders = $this->createTypedShopifyCustomer(
            9_900_000_290,
            'metrics.orders@example.com',
            'wholesale'
        );
        $payload = is_array($withOrders->raw_payload) ? $withOrders->raw_payload : [];
        $payload['orders_count'] = 99;
        $payload['total_spent'] = '1.00';
        $withOrders->forceFill(['raw_payload' => $payload])->save();

        $this->createShopifyOrder(9_900_010_290, (int) $withOrders->shopify_customer_id, 100, 3);
        $this->createShopifyOrder(9_900_010_291, (int) $withOrders->shopify_customer_id, 50.50, 2);

        $payloadOnly = $this->createTypedShopifyCustomer(
            9_900_000_292,
            'metrics.payload@example.com',
            'wholesale'
        );
        $payloadOnlyData = is_array($payloadOnly->raw_payload) ? $payloadOnly->raw_payload : [];
        $payloadOnlyData['orders_count'] = 3;
        $payloadOnlyData['total_spent'] = '299.00';
        $payloadOnly->forceFill(['raw_payload' => $payloadOnlyData])->save();

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data').'?customer_type=wholesale&per_page=100&q=metrics.&sort_by=revenue&sort_dir=desc');
        $response->assertOk()->assertJsonPath('meta.sort_by', 'revenue');

        $rows = collect($response->json('data'));
        $withOrdersRow = $rows->firstWhere('email', 'metrics.orders@example.com');
        $payloadRow = $rows->firstWhere('email', 'metrics.payload@example.com');

        $this->assertNotNull($withOrdersRow);
        $this->assertSame(2, $withOrdersRow['orders_count']);
        $this->assertSame(5, $withOrdersRow['qty']);
        $this->assertEquals(150.5, $withOrdersRow['revenue']);

        $this->assertNotNull($payloadRow);
        $this->assertSame(3, $payloadRow['orders_count']);
        $this->assertSame(0, $payloadRow['qty']);
        $this->assertEquals(299.0, $payloadRow['revenue']);
        $this->assertSame('metrics.payload@example.com', $rows->value('email'));
    }

    /**
     * Tags from a comma-separated string are correctly split into an array.
     */
    public function test_tags_comma_string_is_split_correctly(): void
    {
        ShopifyCustomer::query()->updateOrCreate(
            ['shopify_customer_id' => 9_900_000_004],
            [
                'email'          => 'multitag@example.com',
                'first_name'     => 'Multi',
                'last_name'      => 'Tag',
                'phone'          => null,
                'sync_status'    => 'synced',
                'last_synced_at' => now(),
                'raw_payload'    => $this->sampleApiCustomer([
                    'id'    => 9_900_000_004,
                    'email' => 'multitag@example.com',
                    'tags'  => 'A,  B ,C',  // spaces around commas
                ]),
            ]
        );

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?q=multitag%40example.com');

        $response->assertOk();
        $seededRow = collect($response->json('data'))->firstWhere('email', 'multitag@example.com');

        $this->assertNotNull($seededRow);
        $this->assertSame(['A', 'B', 'C'], $seededRow['tags'], 'Spaces around commas should be trimmed.');
    }

    public function test_classifier_marks_wholesale_customer_from_tags(): void
    {
        $record = ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_105,
            'email' => 'wholesale.customer@example.com',
            'first_name' => 'Wholesale',
            'last_name' => 'Buyer',
            'phone' => null,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_105,
                'email' => 'wholesale.customer@example.com',
                'tags' => 'VIP, wholesale',
            ]),
        ]);

        app(ShopifyCustomerClassifier::class)->classify($record);
        $record->refresh();

        $this->assertSame('wholesale', $record->customer_type);
        $this->assertNull($record->marketplace_channel);
        $this->assertSame('tag', $record->classification_source);
    }

    public function test_classifier_marks_requested_business_tags_as_wholesale(): void
    {
        $classifier = app(ShopifyCustomerClassifier::class);
        $requestedTags = [
            'Car stereo store',
            'DJ Service 0 orders',
            'Dj shop',
            'DJ Store',
            'DJ supply store',
            'Drum School',
            'Drum Store',
            'Guitar store',
            'Home audio store',
            'Musical instrument store',
            'Musician',
            'Music School',
            'Music Store',
            'Piano Store',
            'Recording studio',
            'Record store',
            'Resellers 0 Orders',
            'Shop',
            'VerifiedByWholesaleAllInOne',
            'Violin Shop',
            'wholesaler 0 orders',
            'Wholesaler less orders',
            'wholeseller',
        ];

        foreach ($requestedTags as $index => $tag) {
            $id = 9_900_000_150 + $index;
            $record = ShopifyCustomer::query()->create([
                'shopify_customer_id' => $id,
                'email' => 'business.tag.'.$index.'@example.com',
                'first_name' => 'Business',
                'last_name' => 'Tag',
                'phone' => null,
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'raw_payload' => $this->sampleApiCustomer([
                    'id' => $id,
                    'email' => 'business.tag.'.$index.'@example.com',
                    'tags' => $tag,
                ]),
            ]);

            $classifier->classify($record);
            $record->refresh();

            $this->assertSame('wholesale', $record->customer_type, "Tag [{$tag}] should classify as wholesale.");
            $this->assertSame('tag', $record->classification_source);
        }
    }

    public function test_classifier_marks_dropshipper_customer_from_tags(): void
    {
        $record = ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_170,
            'email' => 'dropshipper.customer@example.com',
            'first_name' => 'Dropshipper',
            'last_name' => 'Customer',
            'phone' => null,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_170,
                'email' => 'dropshipper.customer@example.com',
                'tags' => 'Dropshipper',
            ]),
        ]);

        app(ShopifyCustomerClassifier::class)->classify($record);
        $record->refresh();

        $this->assertSame('dropshipper', $record->customer_type);
        $this->assertNull($record->marketplace_channel);
        $this->assertSame('tag', $record->classification_source);
    }

    public function test_classifier_marks_requested_marketplace_tags_as_marketplace(): void
    {
        $classifier = app(ShopifyCustomerClassifier::class);
        $tags = [
            'Aliexpress' => 'aliexpress',
            'Best Buy USA' => 'bestbuy-usa',
            'Faire' => 'faire',
            'Inbox Shop Chat' => 'inbox-shop-chat',
            'Mercari' => 'mercari-w-ship',
        ];

        $index = 0;
        foreach ($tags as $tag => $channel) {
            $id = 9_900_000_190 + $index;
            $record = ShopifyCustomer::query()->create([
                'shopify_customer_id' => $id,
                'email' => 'marketplace.tag.'.$index.'@example.com',
                'first_name' => 'Marketplace',
                'last_name' => 'Tag',
                'phone' => null,
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'raw_payload' => $this->sampleApiCustomer([
                    'id' => $id,
                    'email' => 'marketplace.tag.'.$index.'@example.com',
                    'tags' => $tag,
                ]),
            ]);

            $classifier->classify($record);
            $record->refresh();

            $this->assertSame('marketplace', $record->customer_type, "Tag [{$tag}] should classify as marketplace.");
            $this->assertSame($channel, $record->marketplace_channel);
            $this->assertSame('tag', $record->classification_source);
            $index++;
        }
    }

    public function test_classifier_marks_marketplace_customer_from_email_domain(): void
    {
        $record = ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_106,
            'email' => 'buyer@marketplace.amazon.com',
            'first_name' => 'Amazon',
            'last_name' => 'Buyer',
            'phone' => null,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_106,
                'email' => 'buyer@marketplace.amazon.com',
                'tags' => '',
            ]),
        ]);

        app(ShopifyCustomerClassifier::class)->classify($record);
        $record->refresh();

        $this->assertSame('marketplace', $record->customer_type);
        $this->assertSame('amazon', $record->marketplace_channel);
        $this->assertSame('email_domain', $record->classification_source);
    }

    public function test_classifier_marks_marketplace_customer_from_latest_order_source(): void
    {
        $record = ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_107,
            'email' => 'order.source@example.com',
            'first_name' => 'Order',
            'last_name' => 'Source',
            'phone' => null,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_107,
                'email' => 'order.source@example.com',
                'tags' => '',
            ]),
        ]);

        ShopifyOrder::query()->create([
            'shopify_order_id' => 9_900_010_107,
            'shopify_customer_id' => $record->shopify_customer_id,
            'total_price' => 10,
            'currency' => 'USD',
            'order_status' => 'paid',
            'order_date' => now(),
            'raw_payload' => [
                'source_name' => 'eBay',
                'tags' => '',
            ],
        ]);

        app(ShopifyCustomerClassifier::class)->classify($record);
        $record->refresh();

        $this->assertSame('marketplace', $record->customer_type);
        $this->assertSame('ebay', $record->marketplace_channel);
        $this->assertSame('order_source', $record->classification_source);
    }

    public function test_main_customer_data_defaults_to_all_b2b(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_108, 'direct.customer@example.com', 'direct');
        $this->createTypedShopifyCustomer(9_900_000_109, 'marketplace.customer@marketplace.amazon.com', 'marketplace', 'amazon');
        $this->createTypedShopifyCustomer(9_900_000_112, 'wholesale.customer@example.com', 'wholesale');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?per_page=100');
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('wholesale.customer@example.com', $emails);
        $this->assertNotContains('direct.customer@example.com', $emails);
        $this->assertNotContains('marketplace.customer@marketplace.amazon.com', $emails);
    }

    public function test_customers_data_all_includes_every_type(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_200, 'all.direct@example.com', 'direct');
        $this->createTypedShopifyCustomer(9_900_000_201, 'all.marketplace@example.com', 'marketplace', 'amazon');
        $this->createTypedShopifyCustomer(9_900_000_202, 'all.wholesale@example.com', 'wholesale');
        $this->createTypedShopifyCustomer(9_900_000_203, 'all.dropshipper@example.com', 'dropshipper');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?customer_type=all&per_page=100');
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('all.direct@example.com', $emails);
        $this->assertContains('all.marketplace@example.com', $emails);
        $this->assertContains('all.wholesale@example.com', $emails);
        $this->assertContains('all.dropshipper@example.com', $emails);
    }

    public function test_customers_data_b2c_filters_direct_customers(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_210, 'b2c.direct@example.com', 'direct');
        $this->createTypedShopifyCustomer(9_900_000_211, 'b2c.wholesale@example.com', 'wholesale');
        $this->createTypedShopifyCustomer(9_900_000_212, 'b2c.marketplace@example.com', 'marketplace', 'amazon');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?customer_type=b2c&per_page=100');
        $response->assertOk();

        $rows = collect($response->json('data'));
        $emails = $rows->pluck('email');
        $this->assertContains('b2c.direct@example.com', $emails);
        $this->assertNotContains('b2c.wholesale@example.com', $emails);
        $this->assertNotContains('b2c.marketplace@example.com', $emails);
        $this->assertTrue($rows->every(fn ($row) => ($row['customer_type'] ?? null) === 'direct'));
    }

    public function test_customers_data_marketplace_type_filter(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_230, 'type.marketplace@example.com', 'marketplace', 'amazon');
        $this->createTypedShopifyCustomer(9_900_000_231, 'type.wholesale@example.com', 'wholesale');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?customer_type=marketplace&per_page=100');
        $response->assertOk();

        $rows = collect($response->json('data'));
        $emails = $rows->pluck('email');
        $this->assertContains('type.marketplace@example.com', $emails);
        $this->assertNotContains('type.wholesale@example.com', $emails);
        $this->assertTrue($rows->every(fn ($row) => ($row['customer_type'] ?? null) === 'marketplace'));
    }

    public function test_customers_data_includes_address_from_default_address(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_220, 'address.column@example.com', 'wholesale');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?q=address.column%40example.com');
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('email', 'address.column@example.com');
        $this->assertNotNull($row);
        $this->assertSame('123 Main St, New York', $row['address']);
    }

    public function test_customers_data_filters_by_multiple_tags(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_240, 'tags.shop@example.com', 'wholesale', null, 'Shop, extra');
        $this->createTypedShopifyCustomer(9_900_000_241, 'tags.vip@example.com', 'wholesale', null, 'VIP');
        $this->createTypedShopifyCustomer(9_900_000_242, 'tags.login@example.com', 'wholesale', null, 'Login with Shop');
        $this->createTypedShopifyCustomer(9_900_000_243, 'tags.other@example.com', 'wholesale', null, 'other');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data', [
            'tags' => ['Shop', 'VIP'],
            'per_page' => 100,
        ]));
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('tags.shop@example.com', $emails);
        $this->assertContains('tags.vip@example.com', $emails);
        $this->assertNotContains('tags.login@example.com', $emails);
        $this->assertNotContains('tags.other@example.com', $emails);
    }

    public function test_customers_data_filters_email_duplicates(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_280, 'dup.email.one@example.com', 'wholesale');
        $two = $this->createTypedShopifyCustomer(9_900_000_281, 'dup.email.two@example.com', 'wholesale');
        $this->createTypedShopifyCustomer(9_900_000_282, 'dup.email.unique@example.com', 'wholesale');
        $one->forceFill(['email' => 'shared.dup@example.com'])->save();
        $two->forceFill(['email' => 'shared.dup@example.com'])->save();

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data', [
            'customer_type' => 'all',
            'duplicate_by' => 'email',
            'q' => 'shared.dup@example.com',
            'per_page' => 100,
        ]));
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('shared.dup@example.com', $emails);
        $this->assertGreaterThanOrEqual(2, $emails->filter(fn ($email) => $email === 'shared.dup@example.com')->count());
        $this->assertNotContains('dup.email.unique@example.com', $emails);
    }

    public function test_customers_data_filters_phone_duplicates(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_283, 'dup.phone.one@example.com', 'wholesale', null, '', '+1 (787) 667-1861');
        $this->createTypedShopifyCustomer(9_900_000_284, 'dup.phone.two@example.com', 'wholesale', null, '', '17876671861');
        $this->createTypedShopifyCustomer(9_900_000_285, 'dup.phone.unique@example.com', 'wholesale', null, '', '+50250163971');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data', [
            'customer_type' => 'all',
            'duplicate_by' => 'phone',
            'q' => 'dup.phone',
            'per_page' => 100,
        ]));
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('dup.phone.one@example.com', $emails);
        $this->assertContains('dup.phone.two@example.com', $emails);
        $this->assertNotContains('dup.phone.unique@example.com', $emails);
    }

    public function test_customers_data_filters_name_duplicates(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_286, 'dup.name.one@example.com', 'wholesale');
        $two = $this->createTypedShopifyCustomer(9_900_000_287, 'dup.name.two@example.com', 'wholesale');
        $unique = $this->createTypedShopifyCustomer(9_900_000_288, 'dup.name.unique@example.com', 'wholesale');
        $one->forceFill(['first_name' => 'Ada', 'last_name' => 'Duplicate'])->save();
        $two->forceFill(['first_name' => 'Ada', 'last_name' => 'Duplicate'])->save();
        $unique->forceFill(['first_name' => 'Unique', 'last_name' => 'Person'])->save();

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data', [
            'customer_type' => 'all',
            'duplicate_by' => 'name',
            'q' => 'dup.name',
            'per_page' => 100,
        ]));
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('dup.name.one@example.com', $emails);
        $this->assertContains('dup.name.two@example.com', $emails);
        $this->assertNotContains('dup.name.unique@example.com', $emails);
    }

    public function test_customers_data_filters_address_duplicates(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_289, 'dup.addr.one@example.com', 'wholesale');
        $two = $this->createTypedShopifyCustomer(9_900_000_290, 'dup.addr.two@example.com', 'wholesale');
        $unique = $this->createTypedShopifyCustomer(9_900_000_291, 'dup.addr.unique@example.com', 'wholesale');

        $shared = ['address1' => '400 Pine Court lot 10', 'city' => 'Stanley', 'country' => 'United States'];
        foreach ([$one, $two] as $customer) {
            $payload = is_array($customer->raw_payload) ? $customer->raw_payload : [];
            $payload['default_address'] = $shared;
            $customer->forceFill(['raw_payload' => $payload])->save();
        }
        $payload = is_array($unique->raw_payload) ? $unique->raw_payload : [];
        $payload['default_address'] = ['address1' => '1 Unique Lane', 'city' => 'Elsewhere', 'country' => 'United States'];
        $unique->forceFill(['raw_payload' => $payload])->save();

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data', [
            'customer_type' => 'all',
            'duplicate_by' => 'address',
            'q' => 'dup.addr',
            'per_page' => 100,
        ]));
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('dup.addr.one@example.com', $emails);
        $this->assertContains('dup.addr.two@example.com', $emails);
        $this->assertNotContains('dup.addr.unique@example.com', $emails);
    }

    public function test_customer_tags_endpoint_includes_counts(): void
    {
        $this->createTypedShopifyCustomer(9_900_000_270, 'tag.count.a@example.com', 'wholesale', null, 'CountTagAlpha, CountTagBeta');
        $this->createTypedShopifyCustomer(9_900_000_271, 'tag.count.b@example.com', 'wholesale', null, 'CountTagAlpha');
        $this->createTypedShopifyCustomer(9_900_000_272, 'tag.count.c@example.com', 'wholesale', null, 'CountTagGamma');
        $this->createTypedShopifyCustomer(9_900_000_273, 'tag.count.d@example.com', 'wholesale', null, 'Login with CountTagAlpha');

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.tags', [
            'customer_type' => 'wholesale',
        ]));
        $response->assertOk();

        $tags = $response->json('tags');
        $counts = $response->json('counts');
        $this->assertIsArray($tags);
        $this->assertIsArray($counts);
        $this->assertContains('CountTagAlpha', $tags);
        $this->assertContains('CountTagBeta', $tags);
        $this->assertContains('CountTagGamma', $tags);
        $this->assertContains('Login with CountTagAlpha', $tags);
        $this->assertSame(2, $counts['CountTagAlpha']);
        $this->assertSame(1, $counts['CountTagBeta']);
        $this->assertSame(1, $counts['CountTagGamma']);
        $this->assertSame(1, $counts['Login with CountTagAlpha']);
    }

    public function test_add_tags_to_selected_customers(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_250, 'add.tags.one@example.com', 'wholesale', null, 'Shop');
        $two = $this->createTypedShopifyCustomer(9_900_000_251, 'add.tags.two@example.com', 'wholesale', null, '');
        $seen = [];

        $this->mock(ShopifyServiceInterface::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('addTagsToShopifyCustomer')->twice()->andReturnUsing(function (ShopifyCustomer $record, array $tags) use (&$seen) {
                $seen[] = [
                    'shopify_customer_id' => (int) $record->shopify_customer_id,
                    'tags' => $tags,
                ];

                return $record;
            });
        });

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.tags.add'), [
            'ids' => [(int) $one->getKey(), (int) $two->getKey()],
            'tags' => ['VIP', 'Shop'],
        ]);
        $response->assertOk()->assertJsonPath('updated', 2);

        $ids = collect($seen)->pluck('shopify_customer_id')->all();
        $this->assertContains(9_900_000_250, $ids);
        $this->assertContains(9_900_000_251, $ids);
        $this->assertSame(['VIP', 'Shop'], $seen[0]['tags']);
    }

    public function test_delete_tag_from_selected_customers(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_260, 'delete.tag.one@example.com', 'wholesale', null, 'wholesale, Shop');
        $two = $this->createTypedShopifyCustomer(9_900_000_261, 'delete.tag.two@example.com', 'wholesale', null, 'wholesale');
        $seen = [];

        $this->mock(ShopifyServiceInterface::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('removeTagsFromShopifyCustomer')->twice()->andReturnUsing(function (ShopifyCustomer $record, array $tags) use (&$seen) {
                $seen[] = [
                    'shopify_customer_id' => (int) $record->shopify_customer_id,
                    'tags' => $tags,
                ];

                return $record;
            });
        });

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.tags.delete'), [
            'ids' => [(int) $one->getKey(), (int) $two->getKey()],
            'tag' => 'wholesale',
        ]);
        $response->assertOk()->assertJsonPath('updated', 2);

        $ids = collect($seen)->pluck('shopify_customer_id')->all();
        $this->assertContains(9_900_000_260, $ids);
        $this->assertContains(9_900_000_261, $ids);
        $this->assertSame(['wholesale'], $seen[0]['tags']);
    }

    public function test_merge_tag_on_selected_customers(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_270, 'merge.tag.one@example.com', 'wholesale', null, 'wholesale, Shop');
        $two = $this->createTypedShopifyCustomer(9_900_000_271, 'merge.tag.two@example.com', 'wholesale', null, 'wholesale');
        $seen = [];

        $this->mock(ShopifyServiceInterface::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('mergeCustomerTag')->twice()->andReturnUsing(function (ShopifyCustomer $record, string $from, string $to) use (&$seen) {
                $seen[] = [
                    'shopify_customer_id' => (int) $record->shopify_customer_id,
                    'from' => $from,
                    'to' => $to,
                ];

                return $record;
            });
        });

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.tags.merge'), [
            'ids' => [(int) $one->getKey(), (int) $two->getKey()],
            'from' => 'wholesale',
            'to' => 'VIP',
        ]);
        $response->assertOk()->assertJsonPath('updated', 2);

        $ids = collect($seen)->pluck('shopify_customer_id')->all();
        $this->assertContains(9_900_000_270, $ids);
        $this->assertContains(9_900_000_271, $ids);
        $this->assertSame('wholesale', $seen[0]['from']);
        $this->assertSame('VIP', $seen[0]['to']);
    }

    public function test_merge_tag_rejects_the_same_source_and_target(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_280, 'merge.same@example.com', 'wholesale', null, 'wholesale');

        $this->mock(ShopifyServiceInterface::class, function ($mock) {
            $mock->shouldReceive('mergeCustomerTag')->never();
        });

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.tags.merge'), [
            'ids' => [(int) $one->getKey()],
            'from' => 'Wholesale',
            'to' => 'wholesale',
        ]);
        $response->assertStatus(422);
    }

    public function test_update_selected_customers(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_300, 'edit.one@example.com', 'wholesale', null, 'Shop');
        $two = $this->createTypedShopifyCustomer(9_900_000_301, 'edit.two@example.com', 'wholesale', null, 'Shop');
        $seen = [];

        $this->mock(ShopifyServiceInterface::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('updateShopifyCustomerFromCrm')->twice()->andReturnUsing(function (ShopifyCustomer $record, array $data) use (&$seen) {
                $seen[] = [
                    'shopify_customer_id' => (int) $record->shopify_customer_id,
                    'data' => $data,
                ];

                return $record;
            });
        });

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.update'), [
            'ids' => [(int) $one->getKey(), (int) $two->getKey()],
            'tags' => 'VIP',
        ]);
        $response->assertOk()->assertJsonPath('updated', 2);
        $this->assertSame('VIP', $seen[0]['data']['tags']);
        $this->assertSame('VIP', $seen[1]['data']['tags']);
        $this->assertSame('edit.one@example.com', $seen[0]['data']['email']);
        $this->assertSame('edit.two@example.com', $seen[1]['data']['email']);
    }

    public function test_delete_selected_customers(): void
    {
        $one = $this->createTypedShopifyCustomer(9_900_000_310, 'delete.one@example.com', 'wholesale');
        $two = $this->createTypedShopifyCustomer(9_900_000_311, 'delete.two@example.com', 'wholesale');
        $seen = [];

        $this->mock(ShopifyServiceInterface::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('deleteShopifyCustomer')->twice()->andReturnUsing(function (ShopifyCustomer $record) use (&$seen) {
                $seen[] = (int) $record->shopify_customer_id;
                $record->delete();

                return null;
            });
        });

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.delete'), [
            'ids' => [(int) $one->getKey(), (int) $two->getKey()],
        ]);
        $response->assertOk()->assertJsonPath('updated', 2);
        $this->assertContains(9_900_000_310, $seen);
        $this->assertContains(9_900_000_311, $seen);
    }

    public function test_marketplace_customer_data_filters_by_channel(): void
    {
        ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_110,
            'email' => 'amazon.customer@marketplace.amazon.com',
            'first_name' => 'Amazon',
            'last_name' => 'Customer',
            'phone' => null,
            'sync_status' => 'synced',
            'customer_type' => 'marketplace',
            'marketplace_channel' => 'amazon',
            'classification_source' => 'email_domain',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_110,
                'email' => 'amazon.customer@marketplace.amazon.com',
                'tags' => '',
            ]),
        ]);

        ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_111,
            'email' => 'ebay.customer@members.ebay.com',
            'first_name' => 'Ebay',
            'last_name' => 'Customer',
            'phone' => null,
            'sync_status' => 'synced',
            'customer_type' => 'marketplace',
            'marketplace_channel' => 'ebay',
            'classification_source' => 'email_domain',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_111,
                'email' => 'ebay.customer@members.ebay.com',
                'tags' => '',
            ]),
        ]);

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.others.data') . '?marketplace_channel=amazon&per_page=100');
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('amazon.customer@marketplace.amazon.com', $emails);
        $this->assertNotContains('ebay.customer@members.ebay.com', $emails);
        $this->assertSame('marketplace', $response->json('data.0.customer_type'));
    }

    public function test_manual_create_pushes_customer_to_shopify_and_stores_returned_payload(): void
    {
        $this->actingAs($this->actingUser());

        $mock = $this->mock(ShopifyServiceInterface::class);
        $mock->shouldReceive('createCustomerFromCrm')->once()->andReturnUsing(function (array $data) {
            $apiRow = $this->sampleApiCustomer([
                'id' => 9_900_001_001,
                'email' => $data['email'],
                'first_name' => 'Manual',
                'last_name' => 'Customer',
                'phone' => $data['phone'],
                'tags' => $data['tags'],
                'default_address' => [
                    'province' => $data['province'],
                    'zip' => $data['zip'],
                ],
            ]);

            return ShopifyCustomer::query()->updateOrCreate(
                ['shopify_customer_id' => (int) $apiRow['id']],
                [
                    'customer_id' => $data['customer_id'],
                    'email' => $apiRow['email'],
                    'first_name' => $apiRow['first_name'],
                    'last_name' => $apiRow['last_name'],
                    'phone' => $apiRow['phone'],
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'raw_payload' => $apiRow,
                ]
            );
        });

        $response = $this->postJson(route('crm.shopify.customers.store'), [
            'name' => 'Manual Customer',
            'email' => 'manual.create@example.com',
            'phone' => '+15550123',
            'province' => 'CA',
            'zip' => '90210',
            'tags' => 'wholesale',
        ]);

        $response->assertCreated()
            ->assertJsonPath('action', 'created')
            ->assertJsonPath('customer.email', 'manual.create@example.com')
            ->assertJsonPath('customer.tags', ['wholesale'])
            ->assertJsonPath('customer.province', 'CA');
    }

    public function test_manual_create_updates_existing_shopify_customer_by_email(): void
    {
        $this->actingAs($this->actingUser());

        $customer = Customer::query()->create([
            'company_id' => null,
            'name' => 'Existing Local',
            'email' => 'existing.sync@example.com',
            'phone' => null,
        ]);

        $existing = ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_001_002,
            'customer_id' => $customer->id,
            'email' => 'existing.sync@example.com',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'phone' => null,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_001_002,
                'email' => 'existing.sync@example.com',
            ]),
        ]);

        $mock = $this->mock(ShopifyServiceInterface::class);
        $mock->shouldReceive('updateShopifyCustomerFromCrm')->once()->withArgs(function ($record, array $data) use ($existing, $customer) {
            return $record->is($existing) && (int) $data['customer_id'] === (int) $customer->id;
        })->andReturnUsing(function (ShopifyCustomer $record, array $data) {
            $record->forceFill([
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'phone' => $data['phone'],
                'raw_payload' => $this->sampleApiCustomer([
                    'id' => $record->shopify_customer_id,
                    'email' => $record->email,
                    'first_name' => 'Updated',
                    'last_name' => 'Name',
                    'phone' => $data['phone'],
                ]),
            ])->save();

            return $record;
        });

        $response = $this->postJson(route('crm.shopify.customers.store'), [
            'name' => 'Updated Name',
            'email' => 'existing.sync@example.com',
            'phone' => '+15550999',
        ]);

        $response->assertOk()
            ->assertJsonPath('action', 'updated');
    }

    public function test_customer_import_creates_valid_rows_and_skips_invalid_rows(): void
    {
        $this->actingAs($this->actingUser());

        $mock = $this->mock(ShopifyServiceInterface::class);
        $mock->shouldReceive('createCustomerFromCrm')->twice()->andReturnUsing(function (array $data) {
            $apiRow = $this->sampleApiCustomer([
                'id' => random_int(9_900_002_000, 9_900_002_999),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tags' => $data['tags'] ?? '',
            ]);

            return ShopifyCustomer::query()->updateOrCreate(
                ['shopify_customer_id' => (int) $apiRow['id']],
                [
                    'customer_id' => $data['customer_id'],
                    'email' => $apiRow['email'],
                    'first_name' => $apiRow['first_name'],
                    'last_name' => $apiRow['last_name'],
                    'phone' => $apiRow['phone'],
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'raw_payload' => $apiRow,
                ]
            );
        });

        $path = tempnam(sys_get_temp_dir(), 'crm_shopify_customers_');
        file_put_contents($path, "name,email,phone,province,zip,tags\nImport One,import.one@example.com,+15550001,CA,90001,wholesale\nNo Contact,,,,,\nImport Two,import.two@example.com,,NY,10001,VIP\n");
        $file = new UploadedFile($path, 'customers.csv', 'text/csv', null, true);

        $response = $this->postJson(route('crm.shopify.customers.import'), [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.created', 2)
            ->assertJsonPath('summary.skipped', 1);
    }

    public function test_shopify_pull_overwrites_existing_local_shopify_customer_payload(): void
    {
        $apiRow = $this->sampleApiCustomer([
            'id' => 9_900_001_003,
            'email' => 'official@example.com',
            'first_name' => 'Official',
            'tags' => 'official',
        ]);

        ShopifyCustomer::query()->create([
            'shopify_customer_id' => (int) $apiRow['id'],
            'email' => 'stale@example.com',
            'first_name' => 'Stale',
            'last_name' => 'Customer',
            'phone' => null,
            'sync_status' => 'synced',
            'last_synced_at' => now()->subDay(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_001_003,
                'email' => 'stale@example.com',
                'tags' => 'stale',
            ]),
        ]);

        $mock = $this->mock(ShopifyServiceInterface::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('syncCustomers')->once()->andReturnUsing(function () use ($apiRow) {
            ShopifyCustomer::query()->updateOrCreate(
                ['shopify_customer_id' => (int) $apiRow['id']],
                [
                    'email' => $apiRow['email'],
                    'first_name' => $apiRow['first_name'],
                    'last_name' => $apiRow['last_name'],
                    'phone' => $apiRow['phone'],
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'raw_payload' => $apiRow,
                ]
            );

            return 1;
        });

        app(ShopifyServiceInterface::class)->syncCustomers();

        $record = ShopifyCustomer::query()->where('shopify_customer_id', $apiRow['id'])->first();
        $this->assertSame('official@example.com', $record->email);
        $this->assertSame('official', $record->raw_payload['tags']);
    }

    public function test_customers_data_includes_whatsapp_status(): void
    {
        $this->createTypedShopifyCustomer(
            9_900_000_260,
            'wa.column@example.com',
            'wholesale',
            null,
            '',
            '+50250163971'
        );

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data').'?q=wa.column%40example.com');
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('email', 'wa.column@example.com');
        $this->assertNotNull($row);
        $this->assertSame('+50250163971', $row['phone']);
        $this->assertArrayHasKey('whatsapp', $row);
        $this->assertArrayHasKey('whatsapp_checked', $row);
        $this->assertNull($row['whatsapp']);
        $this->assertFalse($row['whatsapp_checked']);
    }

    public function test_guest_cannot_check_whatsapp_availability(): void
    {
        $response = $this->postJson(route('crm.shopify.customers.whatsapp.check'), [
            'ids' => [1],
        ]);

        $response->assertStatus(401);
    }

    public function test_whatsapp_check_empty_phone_is_unknown(): void
    {
        $customer = $this->createTypedShopifyCustomer(
            9_900_000_261,
            'wa.empty@example.com',
            'wholesale'
        );

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.whatsapp.check'), [
            'ids' => [(int) $customer->getKey()],
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.0.id', (int) $customer->getKey());
        $response->assertJsonPath('data.0.whatsapp', null);
    }

    public function test_whatsapp_check_invalid_phone_is_unavailable(): void
    {
        $customer = $this->createTypedShopifyCustomer(
            9_900_000_262,
            'wa.invalid@example.com',
            'wholesale',
            null,
            '',
            '123'
        );

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.whatsapp.check'), [
            'ids' => [(int) $customer->getKey()],
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.0.whatsapp', false);
    }

    public function test_whatsapp_check_plausible_phone_is_available_without_provider(): void
    {
        $customer = $this->createTypedShopifyCustomer(
            9_900_000_263,
            'waplausible@example.com',
            'wholesale',
            null,
            '',
            '+17876671861'
        );

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.whatsapp.check'), [
            'ids' => [(int) $customer->getKey()],
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.0.id', (int) $customer->getKey());
        $response->assertJsonPath('data.0.whatsapp', true);

        $service = app(WhatsAppAvailabilityService::class);
        $this->assertTrue($service->checkAndStore($customer));
        $state = $service->cacheState($customer);
        $this->assertTrue($state['whatsapp']);
        $this->assertTrue($state['checked']);
    }

    public function test_customers_data_includes_social_fields(): void
    {
        $customer = $this->createTypedShopifyCustomer(
            9_900_000_400,
            'social.column@example.com',
            'wholesale'
        );

        if (\Illuminate\Support\Facades\Schema::hasColumn('shopify_customers', 'website')) {
            $customer->forceFill([
                'website' => 'https://example.com',
                'facebook' => 'https://facebook.com/shop',
                'instagram' => '@shop',
            ])->save();
        }

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data').'?q=social.column%40example.com');
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('email', 'social.column@example.com');
        $this->assertNotNull($row);
        $this->assertArrayHasKey('website', $row);
        $this->assertArrayHasKey('facebook', $row);
        $this->assertArrayHasKey('instagram', $row);
        if (\Illuminate\Support\Facades\Schema::hasColumn('shopify_customers', 'website')) {
            $this->assertSame('https://example.com', $row['website']);
            $this->assertSame('https://facebook.com/shop', $row['facebook']);
            $this->assertSame('@shop', $row['instagram']);
        }
    }

    public function test_guest_cannot_update_customer_social_fields(): void
    {
        $response = $this->postJson(route('crm.shopify.customers.social'), [
            'ids' => [1],
            'website' => 'https://example.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_customer_social_fields_locally(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('shopify_customers', 'website')) {
            $this->markTestSkipped('shopify_customers social columns are not migrated.');
        }

        $one = $this->createTypedShopifyCustomer(9_900_000_401, 'social.one@example.com', 'wholesale');
        $two = $this->createTypedShopifyCustomer(9_900_000_402, 'social.two@example.com', 'wholesale');

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.social'), [
            'ids' => [(int) $one->getKey(), (int) $two->getKey()],
            'website' => 'https://fivecore.com',
            'instagram' => '@fivecore',
        ]);
        $response->assertOk()->assertJsonPath('updated', 2);

        $rows = collect($response->json('data'));
        $this->assertSame('https://fivecore.com', $rows->firstWhere('id', (int) $one->getKey())['website'] ?? null);
        $this->assertSame('@fivecore', $rows->firstWhere('id', (int) $one->getKey())['instagram'] ?? null);
        $this->assertSame('https://fivecore.com', $rows->firstWhere('id', (int) $two->getKey())['website'] ?? null);
        $this->assertSame('@fivecore', $rows->firstWhere('id', (int) $two->getKey())['instagram'] ?? null);
    }

    public function test_bulk_edit_social_only_skips_shopify(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('shopify_customers', 'website')) {
            $this->markTestSkipped('shopify_customers social columns are not migrated.');
        }

        $one = $this->createTypedShopifyCustomer(9_900_000_403, 'social.bulk.one@example.com', 'wholesale');
        $two = $this->createTypedShopifyCustomer(9_900_000_404, 'social.bulk.two@example.com', 'wholesale');

        $this->mock(ShopifyServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('updateShopifyCustomerFromCrm');
        });

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.update'), [
            'ids' => [(int) $one->getKey(), (int) $two->getKey()],
            'website' => 'https://bulk.example',
        ]);
        $response->assertOk()->assertJsonPath('updated', 2);
    }

    public function test_clear_customer_social_field(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('shopify_customers', 'website')) {
            $this->markTestSkipped('shopify_customers social columns are not migrated.');
        }

        $one = $this->createTypedShopifyCustomer(9_900_000_405, 'social.clear@example.com', 'wholesale');
        $one->forceFill(['facebook' => 'https://facebook.com/keep'])->save();

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('crm.shopify.customers.social'), [
            'ids' => [(int) $one->getKey()],
            'facebook' => '',
        ]);
        $response->assertOk()->assertJsonPath('updated', 1);
        $response->assertJsonPath('data.0.facebook', null);
    }

    public function test_sync_customers_command_requires_shopify_config(): void
    {
        $this->mock(ShopifyServiceInterface::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldReceive('syncCustomers')->never();
        });

        $this->artisan('shopify:sync-customers')->assertFailed();
    }

    public function test_sync_customers_command_runs_when_configured(): void
    {
        $this->mock(ShopifyServiceInterface::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('syncCustomers')->once()->with(250)->andReturn(4);
        });

        $this->artisan('shopify:sync-customers')
            ->expectsOutput('Synced 4 Shopify customers.')
            ->assertSuccessful();
    }
}
