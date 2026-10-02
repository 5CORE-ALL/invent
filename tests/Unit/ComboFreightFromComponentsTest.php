<?php

namespace Tests\Unit;

use App\Models\ProductMaster;
use PHPUnit\Framework\TestCase;

class ComboFreightFromComponentsTest extends TestCase
{
    public function test_combo_freight_sums_component_packages_instead_of_own_box(): void
    {
        $catalog = [
            'AAA 1' => ['l' => 10, 'w' => 8, 'h' => 6],
            'BBB 2' => ['l' => 12, 'w' => 4, 'h' => 4],
        ];

        $ownCbm = ProductMaster::cbmFromRowDims($catalog['AAA 1']);
        $sumCbm = $ownCbm + ProductMaster::cbmFromRowDims($catalog['BBB 2']);

        $row = ProductMaster::applyComboFreightToRow([
            'SKU' => 'AAA 1 + BBB 2',
            'Parent' => 'COMBO',
            'cp' => 20,
            'l' => 10,
            'w' => 8,
            'h' => 6,
            'lp' => round(20 + ($ownCbm * 200), 2),
        ], static function (string $sku) use ($catalog) {
            return $catalog[$sku] ?? null;
        });

        $this->assertEqualsWithDelta($sumCbm, $row['cbm'], 0.0001);
        $this->assertEqualsWithDelta($sumCbm * 200, $row['frght'], 0.01);
        $this->assertEqualsWithDelta(20 + ($sumCbm * 200), $row['lp'], 0.01);
        $this->assertGreaterThan($ownCbm * 200, $row['frght']);
    }

    public function test_non_combo_keeps_its_own_dimensions(): void
    {
        $row = [
            'SKU' => 'AAA 1',
            'l' => 10,
            'w' => 8,
            'h' => 6,
            'frght' => 1.25,
        ];

        $out = ProductMaster::applyComboFreightToRow($row, static fn () => null);

        $this->assertSame(1.25, $out['frght']);
        $this->assertArrayNotHasKey('cbm', $out);
    }
}
