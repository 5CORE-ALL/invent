<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Smoke tests for Ready to Ship table and Forecast Analysis search UI.
 *
 * Run: php artisan test tests/Feature/ReadyToShipAndForecastSearchPagesTest.php
 */
class ReadyToShipAndForecastSearchPagesTest extends TestCase
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

    public function test_guest_is_redirected_from_ready_to_ship(): void
    {
        $response = $this->get('/ready-to-ship');

        $response->assertRedirect();
    }

    public function test_ready_to_ship_page_includes_table_and_row_filter(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->get('/ready-to-ship');

        $response->assertOk();
        $response->assertSee('id="readyToShipTable"', false);
        $response->assertSee('function filterByR2SStage()', false);
        $response->assertSee('window.filterByR2SStage', false);
        $response->assertSee('r2s-row-checkbox', false);
    }

    public function test_guest_is_redirected_from_forecast_analysis(): void
    {
        $response = $this->get('/forecast.analysis');

        $response->assertRedirect();
    }

    public function test_forecast_analysis_page_includes_tabulator_sku_header_filter(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->get('/forecast.analysis');

        $response->assertOk();
        $response->assertSee('headerFilterPlaceholder: "Search sku."', false);
        $response->assertSee('headerFilterFunc: "like"', false);
        $response->assertSee('field: "SKU"', false);
        $response->assertSee('Search parent.', false);
    }
}
