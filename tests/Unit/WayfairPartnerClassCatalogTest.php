<?php

namespace Tests\Unit;

use App\Support\Marketplace\WayfairPartnerClassCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WayfairPartnerClassCatalogTest extends TestCase
{
    #[Test]
    public function it_lists_partner_home_categories(): void
    {
        $groups = WayfairPartnerClassCatalog::groupNames();

        $this->assertCount(53, $groups);
        $this->assertContains('Accent Furniture', $groups);
        $this->assertContains('AV/TV', $groups);
        $this->assertContains('Lighting', $groups);
    }

    #[Test]
    public function it_finds_stand_classes_like_partner_home(): void
    {
        $result = WayfairPartnerClassCatalog::search('stands');
        $names = array_column($result['classes'], 'name');

        $this->assertContains('Carts & Stands', $names);
        $this->assertContains('Speaker Stands', $names);
        $this->assertContains('TV Stands & Entertainment Centers', $names);
        $this->assertContains('Nightstands', $names);
        $this->assertContains('Light Stands & Tripods', $names);
        $this->assertNotEmpty($result['groups']);
    }

    #[Test]
    public function it_returns_class_definition_for_carts_and_stands(): void
    {
        $row = WayfairPartnerClassCatalog::findByName('Carts & Stands');

        $this->assertNotNull($row);
        $this->assertSame('Accent Furniture', $row['category']);
        $this->assertStringContainsString('CLASS OVERVIEW:', $row['definition']);
        $this->assertStringContainsString('DO NOT CLASSIFY:', $row['definition']);
    }

    #[Test]
    public function it_filters_classes_by_category(): void
    {
        $result = WayfairPartnerClassCatalog::search('', 'Music');
        $names = array_column($result['classes'], 'name');

        $this->assertContains('Guitar Stands', $names);
        $this->assertContains('Microphone Stands', $names);
        $this->assertNotContains('Nightstands', $names);
    }

    #[Test]
    public function it_fills_missing_class_ids_from_a_name_map(): void
    {
        $result = WayfairPartnerClassCatalog::search('stands', '', [
            'carts & stands' => '18521',
        ]);
        $row = collect($result['classes'])->firstWhere('name', 'Carts & Stands');

        $this->assertNotNull($row);
        $this->assertSame('18521', $row['id']);
        $this->assertStringContainsString('18521', $row['path']);
    }

    #[Test]
    public function it_does_not_use_storefront_browse_ids_as_class_ids(): void
    {
        $row = WayfairPartnerClassCatalog::findByName('Light Stands & Tripods');

        $this->assertNotNull($row);
        $this->assertSame('', $row['id']);
        $this->assertFalse(WayfairPartnerClassCatalog::isUsableClassId('416547'));
        $this->assertTrue(WayfairPartnerClassCatalog::isUsableClassId('38'));
    }
}
