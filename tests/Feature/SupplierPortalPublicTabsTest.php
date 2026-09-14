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
}
