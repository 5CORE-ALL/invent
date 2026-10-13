<?php

namespace Tests\Feature;

use App\Models\CpHistory;
use App\Models\ProductMaster;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductMasterCpBulkUpdateTest extends TestCase
{
    private string $sqliteFile = '';

    public function createApplication(): Application
    {
        // Local MySQL is not running. A SQLite file survives the request
        // middleware that closes connections, so the test can re-read rows.
        $dir = dirname(__DIR__, 2).'/storage/framework/testing';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->sqliteFile = $dir.'/cp-bulk-'.uniqid('', true).'.sqlite';
        touch($this->sqliteFile);
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->sqliteFile);
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $this->sqliteFile;
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $this->sqliteFile;

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->sqliteFile !== '' && is_file($this->sqliteFile)) {
            @unlink($this->sqliteFile);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role')->nullable();
            $table->json('permissions')->nullable();
            $table->json('culomn_permission')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('product_master', function (Blueprint $table) {
            $table->id();
            $table->string('parent')->nullable();
            $table->string('sku')->unique();
            $table->json('Values')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cp_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->decimal('old_cp', 12, 2)->nullable();
            $table->decimal('new_cp', 12, 2);
            $table->boolean('is_increase')->default(false);
            $table->text('reason')->nullable();
            $table->string('changed_by')->nullable();
            $table->boolean('approved')->default(false);
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    private function actingUser(): User
    {
        return User::create([
            'name' => 'CP Tester',
            'email' => 'cp-bulk-'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
        ]);
    }

    private function makeProduct(string $sku, float $cp, float $l, float $w, float $h): ProductMaster
    {
        return ProductMaster::create([
            'sku' => $sku,
            'parent' => 'ZZ CP BULK',
            'Values' => [
                'cp' => $cp,
                'l' => $l,
                'w' => $w,
                'h' => $h,
                'status' => 'active',
                'unit' => 'Pieces',
            ],
        ]);
    }

    private function values(ProductMaster $product): array
    {
        $product->refresh();
        $values = $product->Values;

        return is_array($values) ? $values : [];
    }

    public function test_selected_rows_save_cp_and_lp_and_leave_unselected_unchanged(): void
    {
        $this->actingAs($this->actingUser());
        $suffix = uniqid('cpbulk', true);
        $a = $this->makeProduct("ZZ CP A {$suffix}", 45.63, 10, 10, 10);
        $b = $this->makeProduct("ZZ CP B {$suffix}", 57.00, 12, 8, 6);
        $untouched = $this->makeProduct("ZZ CP C {$suffix}", 20.00, 5, 5, 5);
        $beforeC = $this->values($untouched);

        $response = $this->postJson(route('product_master.cp.update'), [
            'sku' => $a->sku,
            'skus' => [$a->sku, $b->sku],
            'cp' => 50,
            'reason' => 'Supplier cost increase',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $valuesA = $this->values($a);
        $valuesB = $this->values($b);
        $valuesC = $this->values($untouched);

        $this->assertEquals(50.0, (float) $valuesA['cp']);
        $this->assertEquals(50.0, (float) $valuesB['cp']);
        $this->assertEqualsWithDelta(
            round(50 + (float) $valuesA['frght'], 2),
            (float) $valuesA['lp'],
            0.001
        );
        $this->assertEqualsWithDelta(
            round(50 + (float) $valuesB['frght'], 2),
            (float) $valuesB['lp'],
            0.001
        );
        $this->assertNotEquals((float) $valuesA['lp'], (float) $valuesB['lp']);
        $this->assertEquals((float) $beforeC['cp'], (float) $valuesC['cp']);
        $this->assertEquals((float) $beforeC['lp'], (float) $valuesC['lp']);

        $this->assertSame(1, CpHistory::where('sku', $a->sku)->where('new_cp', 50)->count());
        $this->assertSame(1, CpHistory::where('sku', $b->sku)->where('new_cp', 50)->count());
        $this->assertSame(0, CpHistory::where('sku', $untouched->sku)->count());

        $rows = collect($response->json('rows'))->keyBy('sku');
        $this->assertEquals(50.0, (float) $rows[$a->sku]['current_cp']);
        $this->assertEquals((float) $valuesA['lp'], (float) $rows[$a->sku]['current_lp']);
        $this->assertEquals((float) $valuesB['lp'], (float) $rows[$b->sku]['current_lp']);
    }

    public function test_increase_without_reason_writes_nothing(): void
    {
        $this->actingAs($this->actingUser());
        $suffix = uniqid('cpreason', true);
        $low = $this->makeProduct("ZZ CP LOW {$suffix}", 10, 10, 10, 10);
        $high = $this->makeProduct("ZZ CP HIGH {$suffix}", 80, 10, 10, 10);
        $beforeLow = $this->values($low);
        $beforeHigh = $this->values($high);

        $response = $this->postJson(route('product_master.cp.update'), [
            'sku' => $high->sku,
            'skus' => [$low->sku, $high->sku],
            'cp' => 50,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('requires_reason', true);

        $this->assertEquals((float) $beforeLow['cp'], (float) $this->values($low)['cp']);
        $this->assertEquals((float) $beforeLow['lp'], (float) $this->values($low)['lp']);
        $this->assertEquals((float) $beforeHigh['cp'], (float) $this->values($high)['cp']);
        $this->assertEquals((float) $beforeHigh['lp'], (float) $this->values($high)['lp']);
        $this->assertSame(0, CpHistory::whereIn('sku', [$low->sku, $high->sku])->count());
    }

    public function test_single_sku_update_still_saves_cp_and_derived_lp(): void
    {
        $this->actingAs($this->actingUser());
        $suffix = uniqid('cpsingle', true);
        $product = $this->makeProduct("ZZ CP ONE {$suffix}", 40, 10, 10, 10);

        $response = $this->postJson(route('product_master.cp.update'), [
            'sku' => $product->sku,
            'cp' => 30,
        ]);

        $response->assertOk();
        $values = $this->values($product);
        $this->assertEquals(30.0, (float) $values['cp']);
        $this->assertEqualsWithDelta(
            round(30 + (float) $values['frght'], 2),
            (float) $values['lp'],
            0.001
        );
        $this->assertSame(1, CpHistory::where('sku', $product->sku)->count());
    }
}
