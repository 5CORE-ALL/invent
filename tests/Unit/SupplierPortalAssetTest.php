<?php

namespace Tests\Unit;

use App\Models\SupplierPortalAsset;
use PHPUnit\Framework\TestCase;

class SupplierPortalAssetTest extends TestCase
{
    public function test_manage_categories_match_requested_labels(): void
    {
        $this->assertSame([
            'brand_assets' => 'Brand Assets',
            'inner_box_designs' => 'Inner Box Designs',
            'inner_box_cover' => 'Inner Box Cover',
            'master_carton_designs' => 'Carton Designs',
            'assembly_designs' => 'Assembly Designs',
            'operations_manual' => 'Operations Manual',
            'dos_and_donts' => "Do's & Don'ts",
        ], SupplierPortalAsset::CATEGORIES);
    }

    public function test_legacy_category_slugs_resolve_to_new_keys(): void
    {
        $this->assertSame('brand_assets', SupplierPortalAsset::resolveCategoryKey('logos'));
        $this->assertSame('inner_box_designs', SupplierPortalAsset::resolveCategoryKey('packaging'));
        $this->assertSame('inner_box_cover', SupplierPortalAsset::resolveCategoryKey('inner_box_cover'));
        $this->assertSame('assembly_designs', SupplierPortalAsset::resolveCategoryKey('assembly_designs'));
        $this->assertSame('operations_manual', SupplierPortalAsset::resolveCategoryKey('operations_manual'));
        $this->assertSame('dos_and_donts', SupplierPortalAsset::resolveCategoryKey('dos_and_donts'));
        $this->assertNull(SupplierPortalAsset::resolveCategoryKey('unknown'));
    }

    public function test_category_prefixes_and_code_labels(): void
    {
        $this->assertSame('BA', SupplierPortalAsset::prefixFor('brand_assets'));
        $this->assertSame('IBD', SupplierPortalAsset::prefixFor('inner_box_designs'));
        $this->assertSame('IBC', SupplierPortalAsset::prefixFor('inner_box_cover'));
        $this->assertSame('MCD', SupplierPortalAsset::prefixFor('master_carton_designs'));
        $this->assertSame('AD', SupplierPortalAsset::prefixFor('assembly_designs'));
        $this->assertSame('OM', SupplierPortalAsset::prefixFor('operations_manual'));
        $this->assertSame('DD', SupplierPortalAsset::prefixFor('dos_and_donts'));

        $asset = new SupplierPortalAsset(['category' => 'brand_assets', 'sort_order' => 0]);
        $this->assertSame('BA-01', $asset->codeLabel(1));
        $this->assertSame('BA-12', $asset->codeLabel(12));

        $linked = new SupplierPortalAsset(['parent' => 'MIC', 'sku' => 'MIC-01']);
        $this->assertSame('MIC · MIC-01', $linked->productMeta());
    }

    public function test_inner_box_pages_use_packing_master_grid(): void
    {
        $this->assertTrue(\App\Support\SupplierPortalPackingData::usesPackingGrid('master_carton_designs'));
        $this->assertFalse(\App\Support\SupplierPortalPackingData::usesPackingGrid('inner_box_designs'));
        $this->assertFalse(\App\Support\SupplierPortalPackingData::usesPackingGrid('brand_assets'));
        $this->assertTrue(\App\Support\SupplierPortalDimWtData::usesDimWtGrid('inner_box_designs'));
        $this->assertTrue(\App\Support\SupplierPortalDimWtData::usesDimWtCoverGrid('inner_box_cover'));
        $this->assertTrue(\App\Support\SupplierPortalDimWtData::usesDimWtSkuGrid('assembly_designs'));
        $this->assertTrue(\App\Support\SupplierPortalDimWtData::usesDimWtSkuGrid('operations_manual'));
        $this->assertTrue(\App\Support\SupplierPortalDimWtData::usesDimWtSkuGrid('dos_and_donts'));
        $this->assertContains('assembly_designs', \App\Support\SupplierPortalDimWtData::SKU_CATEGORIES);
        $this->assertTrue(method_exists(\App\Models\SupplierPortalAsset::class, 'ensureSkuParentColumns'));
        $this->assertFalse(\App\Support\SupplierPortalDimWtData::usesDimWtGrid('master_carton_designs'));
        $this->assertFalse(\App\Support\SupplierPortalDimWtData::usesDimWtSkuGrid('inner_box_designs'));
        $this->assertSame('Design Instructions', \App\Support\SupplierPortalPackingData::FIELDS['packing_instructions']);
        $this->assertSame('ctn pkg', \App\Support\SupplierPortalPackingData::CTN_PKG_LABEL);
        $this->assertSame('ctn_instructions', \App\Support\SupplierPortalPackingData::CTN_PKG_KEY);
        $this->assertSame(
            'https://cdn.example/cover.jpg',
            \App\Support\SupplierPortalDimWtData::resolveCoverUrl(['item_pkg_cover' => 'https://cdn.example/cover.jpg'])
        );
        $this->assertSame(
            '/storage/covers/a.jpg',
            \App\Support\SupplierPortalDimWtData::resolveCoverUrl(['packing_images' => ['storage/covers/a.jpg']])
        );
    }
}
