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
            'master_carton_designs' => 'Master Carton Designs',
        ], SupplierPortalAsset::CATEGORIES);
    }

    public function test_legacy_category_slugs_resolve_to_new_keys(): void
    {
        $this->assertSame('brand_assets', SupplierPortalAsset::resolveCategoryKey('logos'));
        $this->assertSame('inner_box_designs', SupplierPortalAsset::resolveCategoryKey('packaging'));
        $this->assertSame('inner_box_cover', SupplierPortalAsset::resolveCategoryKey('inner_box_cover'));
        $this->assertNull(SupplierPortalAsset::resolveCategoryKey('unknown'));
    }
}
