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
                'contact_email' => null,
                'footer_tagline' => null,
            ],
            'grouped' => $grouped,
            'headers' => [],
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
        $this->assertStringContainsString('Itm pkg Cover', $html);
        $this->assertStringContainsString('data-variant="cover"', $html);
        $this->assertStringContainsString('data-variant="sku"', $html);
        $this->assertStringContainsString('>Files</th>', $html);
        $this->assertStringContainsString('sp-dw-thumb', $html);
        $this->assertStringContainsString('spDwImgHover', $html);
        $this->assertStringContainsString('sp-dw-parent', $html);
        $this->assertStringContainsString('sp-pi-parent', $html);
        $this->assertStringContainsString('#fffef2', $html);
        $this->assertStringContainsString('ctn pkg', $html);
        $this->assertStringContainsString('CTN L (cm)', $html);
        $this->assertStringContainsString('CTN L (in)', $html);
        $this->assertStringContainsString('CTN Weight (kg)', $html);
        $this->assertStringContainsString('CTN WT (lb)', $html);
        $this->assertStringNotContainsString('class="sp-dw-actions"', $html);
        $this->assertStringNotContainsString('class="sp-pi-actions"', $html);
    }

    public function test_file_card_opens_a_separate_page(): void
    {
        $asset = new SupplierPortalAsset([
            'category' => 'brand_assets',
            'title' => 'round logo',
            'sku' => 'MIC-01',
            'parent' => 'MIC',
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
                'contact_email' => null,
                'footer_tagline' => null,
            ],
            'grouped' => $grouped,
            'headers' => [],
            'section' => 'brand_assets',
            'title' => '5 Core Supplier Portal',
        ])->render();

        $this->assertStringContainsString(route('supplier-portal.show', $asset, false), $list);
        $this->assertStringContainsString('BA-01', $list);
        $this->assertStringContainsString('MIC · MIC-01', $list);

        $page = view('supplier-portal.show', [
            'settings' => (object) [
                'company_name' => '5 Core',
            ],
            'asset' => $asset,
            'categoryKey' => 'brand_assets',
            'categoryLabel' => 'Brand Assets',
            'fileNumber' => 1,
            'title' => 'round logo — 5 Core',
        ])->render();

        $this->assertStringContainsString('round logo', $page);
        $this->assertStringContainsString('Brand Assets', $page);
        $this->assertStringContainsString('BA-01', $page);
        $this->assertStringContainsString('MIC-01', $page);
        $this->assertStringContainsString('MIC', $page);
        $this->assertStringContainsString(route('supplier-portal.download', $asset, false), $page);
        $this->assertStringContainsString('Back to Brand Assets', $page);
    }

    public function test_admin_lists_categories_as_tabs(): void
    {
        $grouped = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $grouped[$key] = new Collection;
        }

        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = view('supplier-portal.admin', [
            'title' => 'Supplier Portal',
            'settings' => (object) [
                'company_name' => '5 Core',
                'hero_title' => 'Welcome',
                'hero_subtitle' => '',
                'hero_image_path' => null,
                'contact_email' => null,
                'footer_tagline' => null,
            ],
            'grouped' => $grouped,
            'categories' => SupplierPortalAsset::CATEGORIES,
            'activeTab' => 'inner_box_designs',
            'publicUrl' => 'http://localhost/supplier-portal',
        ])->render();

        $this->assertStringContainsString('id="spAdminTabs"', $html);
        $this->assertStringContainsString('role="tablist"', $html);
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $this->assertStringContainsString('data-tab="'.$key.'"', $html);
            $this->assertStringContainsString('id="sp-tab-'.$key.'"', $html);
        }
        $this->assertStringContainsString('id="sp-tab-inner_box_cover"', $html);
        $this->assertStringContainsString('show active', $html);
        $this->assertLessThan(2, substr_count($html, 'card mb-4'));
        $this->assertStringContainsString('name="sku"', $html);
        $this->assertStringContainsString('name="parent"', $html);
        $this->assertStringContainsString('Type to search SKU', $html);
        $this->assertStringContainsString('Type to search parent', $html);
        $this->assertStringContainsString('>Parent</th>', $html);
        $this->assertStringContainsString('>SKU</th>', $html);
        $this->assertStringContainsString('Add to siblings', $html);
        $this->assertStringContainsString('name="add_to_siblings"', $html);
        $this->assertStringContainsString('Page Header', $html);
        $this->assertStringContainsString('id="spHeaderModal"', $html);
        $this->assertStringContainsString('Instructions / Guidelines', $html);
        $this->assertStringContainsString('+ Add header', $html);
        $this->assertStringContainsString('Packing Carton', $html);
        $this->assertStringContainsString('data-sp-pi-mode="view"', $html);
        $this->assertStringContainsString('data-sp-pi-mode="edit"', $html);
        $this->assertStringContainsString('data-sp-pi-sync', $html);
        $this->assertStringContainsString('Design Instructions', $html);
        $this->assertStringContainsString('ctn pkg', $html);
        $this->assertStringContainsString('CTN L (cm)', $html);
        $this->assertStringContainsString('CTN W (cm)', $html);
        $this->assertStringContainsString('CTN H (cm)', $html);
        $this->assertStringContainsString('CTN L (in)', $html);
        $this->assertStringContainsString('CTN Weight (kg)', $html);
        $this->assertStringContainsString('CTN WT (lb)', $html);
        $this->assertStringContainsString('Box / carton', $html);
        $this->assertStringContainsString('item PKG', $html);
        $this->assertStringContainsString('Itm pkg Cover', $html);
        $this->assertStringContainsString('data-sp-dw', $html);
        $this->assertStringContainsString('/dim-wt-master', $html);
        $this->assertStringContainsString('data-variant="sku"', $html);
        $this->assertStringContainsString('Dim / Wt — SKU', $html);
        $this->assertStringContainsString('>Files</th>', $html);
        $this->assertStringContainsString('/supplier-portal/manage/sku-files', $html);
        $this->assertStringContainsString('>Actions</th>', $html);
        $this->assertStringContainsString('>Edit</button>', $html);
        $this->assertStringContainsString('>Delete</button>', $html);
        $this->assertStringContainsString('spDwSkuAdmin_operations_manualWrap', $html);
        $this->assertStringContainsString('spDwSkuAdmin_dos_and_dontsWrap', $html);
    }

    public function test_public_page_shows_category_headers(): void
    {
        $grouped = [];
        $headers = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $grouped[$key] = new Collection;
            $headers[$key] = new Collection;
        }
        $headers['brand_assets']->push((object) [
            'title' => 'Logo usage',
            'instructions' => 'Use the full-color logo on white backgrounds only.',
        ]);

        $html = view('supplier-portal.public', [
            'settings' => (object) [
                'company_name' => '5 Core',
                'hero_title' => 'Welcome to 5 Core Supplier Portal',
                'hero_subtitle' => 'Files',
                'hero_image_path' => null,
                'contact_email' => null,
                'footer_tagline' => null,
            ],
            'grouped' => $grouped,
            'headers' => $headers,
            'section' => 'brand_assets',
            'title' => '5 Core Supplier Portal',
        ])->render();

        $this->assertStringContainsString('Logo usage', $html);
        $this->assertStringContainsString('Use the full-color logo on white backgrounds only.', $html);
        $this->assertStringContainsString('sp-page-header', $html);
    }
}
