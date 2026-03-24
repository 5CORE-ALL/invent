<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verifies Spare Parts SKU search hits product_master correctly (no legacy `category` column).
 *
 * Data comes from the same source as Product Master (product_master table).
 * UI route for Product Master data view (example): GET /product-master-data-view
 *
 * Run:
 *   php artisan test tests/Feature/SparePartsProductMasterSearchTest.php
 *
 * CLI debug (real DB, no HTTP auth):
 *   php artisan spare-parts:debug-search "sp 121"
 */
class SparePartsProductMasterSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function actingUser(): User
    {
        $user = User::query()->first();
        if ($user) {
            return $user;
        }

        return User::factory()->create();
    }

    public function test_guest_cannot_access_spare_parts_search_api(): void
    {
        $response = $this->getJson(route('inventory.spare-parts.api.search-parts', [
            'q' => 'te',
            'limit' => 3,
        ], false));

        $response->assertStatus(401);
    }

    public function test_authenticated_search_returns_json_data_array_without_server_error(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('inventory.spare-parts.api.search-parts', [
            'q' => 'a',
            'limit' => 5,
        ], false));

        $response->assertOk();
        $response->assertJsonStructure(['data']);
        $this->assertIsArray($response->json('data'));
        foreach ($response->json('data') as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('sku', $row);
            $this->assertArrayHasKey('is_spare_part', $row);
        }
    }

    public function test_empty_query_returns_empty_data(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('inventory.spare-parts.api.search-parts', [
            'q' => '',
        ], false));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_part_skus_dropdown_endpoint_returns_id_and_sku(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->getJson(route('inventory.spare-parts.api.part-skus', ['limit' => 5], false));

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta' => ['total_in_db', 'returned', 'capped']]);
        $this->assertIsArray($response->json('data'));
        foreach ($response->json('data') as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('sku', $row);
        }
    }
}
