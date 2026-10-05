<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ListingMissingAdsPagesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\CloseDbConnections::class);
    }

    private function actingUser(): User
    {
        $user = User::query()->first();
        if ($user) {
            return $user;
        }

        return User::factory()->create();
    }

    public function test_guest_is_redirected_from_new_missing_ads_pages(): void
    {
        foreach ([
            'ebay.ads.missing',
            'ebay2.ads.missing',
            'ebay3.ads.missing',
            'tiktok1.ads.missing',
            'tiktok2.ads.missing',
            'walmart.missing.ads',
        ] as $name) {
            $this->get(route($name, [], false))->assertRedirect();
        }
    }

    public function test_missing_ads_pages_render_titles(): void
    {
        $this->actingAs($this->actingUser());

        $pages = [
            'ebay.ads.missing' => 'eBay Missing Ads',
            'ebay2.ads.missing' => 'eBay 2 Missing Ads',
            'ebay3.ads.missing' => 'eBay 3 Missing Ads',
            'tiktok1.ads.missing' => 'TikTok 1 Missing Ads',
            'tiktok2.ads.missing' => 'TikTok 2 Missing Ads',
            'walmart.missing.ads' => 'Walmart Missing Ads',
        ];

        foreach ($pages as $name => $title) {
            $html = $this->get(route($name, [], false))->assertOk()->getContent();
            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString('Missing:', $html);
        }
    }
}
