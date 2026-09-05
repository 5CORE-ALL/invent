<?php

namespace Tests\Feature;

use App\Models\Crm\ShopifyCustomer;
use App\Models\GoogleMapsExtractorResult;
use App\Models\GoogleMapsExtractorSearch;
use App\Models\User;
use App\Services\Crm\GoogleMapsLeadImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleMapsLeadImportTest extends TestCase
{
    use DatabaseTransactions;

    private function actingUser(): User
    {
        $user = User::query()->first();

        return $user ?? User::factory()->create();
    }

    private function createSearchWithLead(): array
    {
        $search = GoogleMapsExtractorSearch::query()->create([
            'query' => 'Music Store',
            'location' => 'Austin, TX',
            'result_limit' => 10,
            'status' => 'completed',
            'results_count' => 1,
        ]);

        $result = GoogleMapsExtractorResult::query()->create([
            'search_id' => $search->id,
            'source' => 'google_maps',
            'name' => 'Austin Music Co',
            'email' => 'austin.music.import@example.com',
            'phone' => '+15125550123',
            'address' => '100 Main St, Austin, TX 78701',
            'website' => 'https://austinmusic.example',
            'social_links' => [
                'https://facebook.com/austinmusic',
                'https://instagram.com/austinmusic',
            ],
        ]);

        return [$search, $result];
    }

    public function test_guest_cannot_add_leads_to_customers(): void
    {
        [$search, $result] = $this->createSearchWithLead();

        $response = $this->postJson(route('google-maps-data-extractor.add-to-customers', $search), [
            'result_ids' => [$result->id],
        ]);

        $response->assertStatus(401);
    }

    public function test_import_creates_customer_with_search_tag_and_google_source(): void
    {
        [$search, $result] = $this->createSearchWithLead();

        $summary = app(GoogleMapsLeadImportService::class)->import($search, [(int) $result->id]);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['updated']);

        $customer = ShopifyCustomer::query()
            ->whereRaw('LOWER(email) = ?', ['austin.music.import@example.com'])
            ->first();
        $this->assertNotNull($customer);
        $this->assertSame('Google', $customer->classification_source);
        $this->assertTrue((bool) $customer->classification_overridden);
        $this->assertSame('wholesale', $customer->customer_type);
        $this->assertStringContainsString('Music Store', (string) ($customer->raw_payload['tags'] ?? ''));
        $this->assertSame('TX', $customer->raw_payload['default_address']['province'] ?? null);
        $this->assertSame('78701', $customer->raw_payload['default_address']['zip'] ?? null);
        if (Schema::hasColumn('shopify_customers', 'website')) {
            $this->assertSame('https://austinmusic.example', $customer->website);
            $this->assertSame('https://facebook.com/austinmusic', $customer->facebook);
            $this->assertSame('https://instagram.com/austinmusic', $customer->instagram);
        }
    }

    public function test_import_is_idempotent_and_keeps_google_source(): void
    {
        [$search, $result] = $this->createSearchWithLead();
        $service = app(GoogleMapsLeadImportService::class);

        $this->assertSame(1, $service->import($search, [(int) $result->id])['created']);
        $second = $service->import($search, [(int) $result->id]);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(0, $second['created']);

        $this->assertSame(1, ShopifyCustomer::query()
            ->whereRaw('LOWER(email) = ?', ['austin.music.import@example.com'])
            ->count());
    }

    public function test_authenticated_user_can_add_selected_leads(): void
    {
        [$search, $result] = $this->createSearchWithLead();

        $this->actingAs($this->actingUser());

        $response = $this->postJson(route('google-maps-data-extractor.add-to-customers', $search), [
            'result_ids' => [(int) $result->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('customers_url', route('crm.shopify.customers.index'));
    }
}
