<?php

namespace Tests\Feature;

use App\Models\SupplierPortalAsset;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SupplierPortalPublicTabsTest extends TestCase
{
    public function test_public_portal_renders_category_tabs(): void
    {
        $grouped = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $grouped[$key] = new Collection;
        }

        $html = view('supplier-portal.public', [
            'settings' => (object) [
                'company_name' => '5 Core',
                'hero_title' => 'Welcome to 5 Core Supplier Portal',
                'hero_subtitle' => 'Files',
                'hero_image_path' => null,
                'announcement' => null,
                'contact_email' => null,
                'footer_tagline' => null,
            ],
            'grouped' => $grouped,
            'section' => null,
            'title' => '5 Core Supplier Portal',
        ])->render();

        $this->assertStringContainsString('role="tablist"', $html);
        foreach (SupplierPortalAsset::CATEGORIES as $key => $label) {
            $this->assertStringContainsString('data-tab="'.$key.'"', $html);
            $this->assertStringContainsString(e($label), $html);
        }
        $this->assertStringNotContainsString('Packaging Designs', $html);
        $this->assertStringNotContainsString('Marketing Materials', $html);
    }

    public function test_file_card_opens_a_separate_page(): void
    {
        $asset = new SupplierPortalAsset([
            'category' => 'brand_assets',
            'title' => 'round logo',
            'file_name' => 'round-logo.png',
            'file_path' => 'supplier-portal/brand_assets/round-logo.png',
            'mime' => 'image/png',
            'file_size' => 62900,
        ]);
        $asset->id = 41;

        $grouped = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $grouped[$key] = new Collection;
        }
        $grouped['brand_assets']->push($asset);

        $list = view('supplier-portal.public', [
            'settings' => (object) [
                'company_name' => '5 Core',
                'hero_title' => 'Welcome to 5 Core Supplier Portal',
                'hero_subtitle' => 'Files',
                'hero_image_path' => null,
                'announcement' => null,
                'contact_email' => null,
                'footer_tagline' => null,
            ],
            'grouped' => $grouped,
            'section' => 'brand_assets',
            'title' => '5 Core Supplier Portal',
        ])->render();

        $this->assertStringContainsString(route('supplier-portal.show', $asset, false), $list);

        $page = view('supplier-portal.show', [
            'settings' => (object) [
                'company_name' => '5 Core',
            ],
            'asset' => $asset,
            'categoryKey' => 'brand_assets',
            'categoryLabel' => 'Brand Assets',
            'title' => 'round logo — 5 Core',
        ])->render();

        $this->assertStringContainsString('round logo', $page);
        $this->assertStringContainsString('Brand Assets', $page);
        $this->assertStringContainsString(route('supplier-portal.download', $asset, false), $page);
        $this->assertStringContainsString('Back to Brand Assets', $page);
    }
}
