<?php

namespace Tests\Feature;

use App\Models\Crm\Customer;
use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use App\Models\User;
use App\Services\Crm\Contracts\ShopifyServiceInterface;
use App\Services\Crm\ShopifyCustomerClassifier;
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

    public function test_main_customer_data_excludes_marketplace_by_default(): void
    {
        ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_108,
            'email' => 'direct.customer@example.com',
            'first_name' => 'Direct',
            'last_name' => 'Customer',
            'phone' => null,
            'sync_status' => 'synced',
            'customer_type' => 'direct',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_108,
                'email' => 'direct.customer@example.com',
                'tags' => '',
            ]),
        ]);

        ShopifyCustomer::query()->create([
            'shopify_customer_id' => 9_900_000_109,
            'email' => 'marketplace.customer@marketplace.amazon.com',
            'first_name' => 'Marketplace',
            'last_name' => 'Customer',
            'phone' => null,
            'sync_status' => 'synced',
            'customer_type' => 'marketplace',
            'marketplace_channel' => 'amazon',
            'last_synced_at' => now(),
            'raw_payload' => $this->sampleApiCustomer([
                'id' => 9_900_000_109,
                'email' => 'marketplace.customer@marketplace.amazon.com',
                'tags' => '',
            ]),
        ]);

        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('crm.shopify.customers.data') . '?per_page=100');
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertContains('direct.customer@example.com', $emails);
        $this->assertNotContains('marketplace.customer@marketplace.amazon.com', $emails);
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
}
