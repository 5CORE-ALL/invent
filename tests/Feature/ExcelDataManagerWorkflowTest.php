<?php

namespace Tests\Feature;

use App\Models\ExcelImport;
use App\Models\ExcelWorksheet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelDataManagerWorkflowTest extends TestCase
{
    private array $importIds = [];

    protected function tearDown(): void
    {
        foreach ($this->importIds as $importId) {
            $import = ExcelImport::query()->find($importId);
            if ($import) {
                Storage::disk('local')->delete($import->stored_filename);
                $import->delete();
            }
        }

        parent::tearDown();
    }

    public function test_multi_sheet_workbook_can_be_viewed_searched_sorted_paged_and_exported(): void
    {
        $user = $this->activeUser();
        $other = User::query()
            ->where('is_active', 1)
            ->where('id', '!=', $user->id)
            ->where(function ($query) {
                $query->whereNull('logined')->orWhere('logined', '!=', 0);
            })
            ->first();

        $path = $this->workbook();
        $upload = new UploadedFile(
            $path,
            'catalog-workbook.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($user)->post('/excel/import', [
            'file' => $upload,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        @unlink($path);
        $payload = $response->json('import');
        $this->importIds[] = $payload['id'];

        $this->assertSame('catalog-workbook.xlsx', $payload['original_filename']);
        $this->assertSame(6, $payload['total_worksheets']);
        $this->assertSame(
            ['& Benches', 'Speakers', 'Guitar Amplifiers', 'Car Speakers & Speaker Systems', 'Stands, Mounts & Holders', 'Microphones & Wireless Systems'],
            collect($payload['worksheets'])->pluck('worksheet_name')->all()
        );

        $stored = ExcelImport::query()->findOrFail($payload['id']);
        $this->assertSame($user->id, (int) $stored->uploaded_by);
        $this->assertStringStartsWith('excel-imports/'.$user->id.'/', $stored->stored_filename);
        $this->assertFileExists(Storage::disk('local')->path($stored->stored_filename));
        $this->assertFileDoesNotExist(public_path('storage/'.$stored->stored_filename));

        $benches = collect($payload['worksheets'])->firstWhere('worksheet_name', '& Benches');
        $this->assertSame($benches['id'], $payload['selected_worksheet_id']);
        $this->assertSame(['SKU', 'Product Name', 'Price', 'Notes', 'Updated', 'Qty', 'Line Total'], $benches['headers']);
        $this->assertSame(12, $benches['total_rows']);

        $page = $this->actingAs($user)->getJson('/excel/worksheets/'.$benches['id'].'/data?per_page=10&page=1');
        $page->assertOk();
        $page->assertJsonPath('pagination.total', 12);
        $page->assertJsonPath('pagination.per_page', 10);
        $page->assertJsonPath('pagination.current_page', 1);
        $page->assertJsonPath('pagination.from', 1);
        $page->assertJsonPath('pagination.to', 10);
        $this->assertCount(10, $page->json('records'));
        $this->assertSame('Café & "quotes"', $page->json('records.0.values.Notes'));
        $this->assertSame('2024-03-15', $page->json('records.0.values.Updated'));
        $this->assertContains($page->json('records.0.values.Line Total'), ['259', '259.0', '259.00']);
        $this->assertSame('8901234567890123', $page->json('records.0.values.SKU'));

        $blankPrice = collect($page->json('records'))->first(fn ($row) => $row['values']['SKU'] === 'BN-02');
        $this->assertNotNull($blankPrice);
        $this->assertArrayNotHasKey('Price', $blankPrice['values']);
        $this->assertStringContainsString('line two', $blankPrice['values']['Notes']);

        $pageTwo = $this->actingAs($user)->getJson('/excel/worksheets/'.$benches['id'].'/data?per_page=10&page=2');
        $pageTwo->assertOk();
        $pageTwo->assertJsonPath('pagination.from', 11);
        $pageTwo->assertJsonPath('pagination.to', 12);
        $this->assertCount(2, $pageTwo->json('records'));

        $search = $this->actingAs($user)->getJson('/excel/worksheets/'.$benches['id'].'/data?search='.urlencode('Café'));
        $search->assertOk();
        $search->assertJsonPath('pagination.total', 1);
        $search->assertJsonPath('records.0.values.Product Name', 'Oak Bench');

        $sorted = $this->actingAs($user)->getJson('/excel/worksheets/'.$benches['id'].'/data?sort=Price&direction=desc&per_page=10');
        $sorted->assertOk();
        $prices = collect($sorted->json('records'))->pluck('values.Price')->filter(fn ($price) => $price !== null && $price !== '')->map(fn ($price) => (float) $price)->values();
        $this->assertSame($prices->all(), $prices->sortDesc()->values()->all());
        $this->assertSame(200.0, $prices->first());

        $speakers = collect($payload['worksheets'])->firstWhere('worksheet_name', 'Speakers');
        $speakerData = $this->actingAs($user)->getJson('/excel/worksheets/'.$speakers['id'].'/data');
        $speakerData->assertOk();
        $speakerData->assertJsonPath('worksheet.headers', ['Model', 'Watts', 'Impedance (Ω)', 'Bluetooth']);
        $speakerData->assertJsonPath('records.0.values.Impedance (Ω)', '8Ω');
        $this->assertNotSame($benches['headers'], $speakerData->json('worksheet.headers'));

        $amps = collect($payload['worksheets'])->firstWhere('worksheet_name', 'Guitar Amplifiers');
        $this->assertSame(['Brand', 'Brand (2)', 'Channels', 'Price USD'], $amps['headers']);

        $empty = collect($payload['worksheets'])->firstWhere('worksheet_name', 'Car Speakers & Speaker Systems');
        $emptyData = $this->actingAs($user)->getJson('/excel/worksheets/'.$empty['id'].'/data');
        $emptyData->assertOk();
        $emptyData->assertJsonPath('total_records', 0);
        $emptyData->assertJsonPath('records', []);

        $stands = collect($payload['worksheets'])->firstWhere('worksheet_name', 'Stands, Mounts & Holders');
        $standsData = $this->actingAs($user)->getJson('/excel/worksheets/'.$stands['id'].'/data');
        $standsData->assertOk();
        $standsData->assertJsonPath('worksheet.headers', ['Item', 'Material', 'SKU', 'Price']);
        $standsData->assertJsonPath('total_records', 1);
        $standsData->assertJsonPath('records.0.values.Item', 'Floor stand');
        $this->assertNotContains('Month', $standsData->json('worksheet.headers'));
        $this->assertNotContains('Units sold', $standsData->json('records.0.values'));

        $meta = $this->actingAs($user)->getJson('/excel/worksheets/'.$speakers['id']);
        $meta->assertOk();
        $meta->assertJsonPath('worksheet.worksheet_name', 'Speakers');
        $meta->assertJsonMissingPath('records');

        $export = $this->actingAs($user)->get('/excel/worksheets/'.$benches['id'].'/export');
        $export->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $export->headers->get('content-type')
        );

        $exportPath = $export->baseResponse->getFile()->getPathname();
        $reloaded = IOFactory::load($exportPath);
        $exportedSheet = $reloaded->getActiveSheet();
        $this->assertSame('SKU', (string) $exportedSheet->getCell('A1')->getValue());
        $this->assertSame('Line Total', (string) $exportedSheet->getCell('G1')->getValue());
        $this->assertSame(13, $exportedSheet->getHighestDataRow());
        $exportedValues = $exportedSheet->rangeToArray('A2:G13', null, true, false, false);
        $this->assertCount(12, $exportedValues);
        $this->assertSame('8901234567890123', (string) $exportedValues[0][0]);
        $reloaded->disconnectWorksheets();
        @unlink($exportPath);

        if ($other) {
            $this->actingAs($other)->getJson('/excel/worksheets/'.$benches['id'].'/data')->assertNotFound();
            $this->actingAs($other)->getJson('/excel/worksheets/'.$benches['id'])->assertNotFound();
        } else {
            $this->assertFalse(
                ExcelWorksheet::query()->ownedBy($user->id + 999999)->whereKey($benches['id'])->exists()
            );
        }

        $invalid = UploadedFile::fake()->create('notes.xlsx', 12, 'text/plain');
        file_put_contents($invalid->getRealPath(), 'this is not a workbook');
        $this->actingAs($user)
            ->post('/excel/import', ['file' => $invalid], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertXlsImport($user);
    }

    private function assertXlsImport(User $user): void
    {
        $spreadsheet = new Spreadsheet();
        $first = $spreadsheet->getActiveSheet();
        $first->setTitle('Legacy Speakers');
        $first->fromArray(['Model', 'Ohms'], null, 'A1', true);
        $first->fromArray(['LS-1', '8'], null, 'A2', true);
        $second = $spreadsheet->createSheet();
        $second->setTitle('Legacy Mics');
        $second->fromArray(['Mic', 'Wireless'], null, 'A1', true);

        $path = tempnam(sys_get_temp_dir(), 'excel-xls-');
        $xls = $path.'.xls';
        (new Xls($spreadsheet))->save($xls);
        $spreadsheet->disconnectWorksheets();
        @unlink($path);

        $upload = new UploadedFile($xls, 'legacy.xls', 'application/vnd.ms-excel', null, true);
        $response = $this->actingAs($user)->post('/excel/import', [
            'file' => $upload,
        ], ['Accept' => 'application/json']);
        $response->assertOk();
        $payload = $response->json('import');
        $this->importIds[] = $payload['id'];
        $this->assertSame(['Legacy Speakers', 'Legacy Mics'], collect($payload['worksheets'])->pluck('worksheet_name')->all());
        $this->assertSame($payload['worksheets'][0]['id'], $payload['selected_worksheet_id']);
        $this->assertSame(0, $payload['worksheets'][1]['total_rows']);
        @unlink($xls);

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('excel.index'));
        if (\Illuminate\Support\Facades\Schema::hasTable('product_master')) {
            $this->actingAs($user)->get(route('excel.index'))->assertOk()->assertSee('eBay LQS', false);
        }
    }

    private function activeUser(): User
    {
        $user = User::query()
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->whereNull('logined')->orWhere('logined', '!=', 0);
            })
            ->orderBy('id')
            ->first();

        $this->assertNotNull($user, 'An active user is required to exercise the Excel manager.');

        return $user;
    }

    private function workbook(): string
    {
        $spreadsheet = new Spreadsheet();
        $benches = $spreadsheet->getActiveSheet();
        $benches->setTitle('& Benches');
        $benches->fromArray(['SKU', 'Product Name', 'Price', 'Notes', 'Updated', 'Qty', 'Line Total'], null, 'A1', true);

        $rows = [
            ['BN-01', 'Oak Bench', 129.5, 'Café & "quotes"', null, 2],
            ['BN-02', "Kids' Bench", null, "first line\nline two", null, null],
            ['BN-03', 'Speaker bench', 10, null, null, 1],
            ['BN-04', 'Wall bench', 200, null, null, 1],
            ['BN-05', 'Patio bench', 30, null, null, 4],
        ];
        foreach (range(6, 12) as $number) {
            $rows[] = ['BN-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'Bench '.$number, 40 + $number, null, null, 1];
        }

        $excelRow = 2;
        foreach ($rows as $row) {
            $benches->fromArray($row, null, 'A'.$excelRow, true);
            $excelRow++;
        }

        $benches->setCellValueExplicit('A2', '8901234567890123', DataType::TYPE_STRING);
        $benches->setCellValue('E2', Date::PHPToExcel(new \DateTime('2024-03-15')));
        $benches->getStyle('E2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $benches->setCellValue('E3', Date::PHPToExcel(new \DateTime('2024-04-01 15:45:00')));
        $benches->getStyle('E3')->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
        $benches->setCellValue('G2', '=C2*F2');

        $speakers = $spreadsheet->createSheet();
        $speakers->setTitle('Speakers');
        $speakers->fromArray(['Model', 'Watts', 'Impedance (Ω)', 'Bluetooth'], null, 'A1', true);
        $speakers->fromArray(['SP-1', 50, '8Ω', 'Yes'], null, 'A2', true);
        $speakers->fromArray(['SP-2', 200, '4Ω', 'No'], null, 'A3', true);
        $speakers->fromArray(['SP-3', 10, '6Ω', 'Yes'], null, 'A4', true);

        $amps = $spreadsheet->createSheet();
        $amps->setTitle('Guitar Amplifiers');
        $amps->fromArray(['Brand', 'Brand', 'Channels', 'Price USD'], null, 'A1', true);
        $amps->fromArray(['Fender', 'Twin', 2, 899], null, 'A2', true);

        $spreadsheet->createSheet()->setTitle('Car Speakers & Speaker Systems');

        $stands = $spreadsheet->createSheet();
        $stands->setTitle('Stands, Mounts & Holders');
        $stands->setCellValue('A1', 'Units sold');
        $stands->fromArray(['Month', 'Units'], null, 'A3', true);
        $stands->fromArray(['Jan', 12], null, 'A4', true);
        $stands->fromArray(['Feb', 18], null, 'A5', true);
        $stands->fromArray(['Item', 'Material', 'SKU', 'Price'], null, 'A8', true);
        $stands->fromArray(['Floor stand', 'Steel & alloy', 'ST-1', 49], null, 'A9', true);

        $mics = $spreadsheet->createSheet();
        $mics->setTitle('Microphones & Wireless Systems');
        $mics->fromArray(['System', 'Band'], null, 'A1', true);
        $mics->fromArray(['Handheld', 'UHF'], null, 'A2', true);
        $mics->fromArray(['Lavalier', '2.4 GHz'], null, 'A3', true);

        $path = tempnam(sys_get_temp_dir(), 'excel-xlsx-');
        $xlsx = $path.'.xlsx';
        (new Xlsx($spreadsheet))->save($xlsx);
        $spreadsheet->disconnectWorksheets();
        @unlink($path);

        return $xlsx;
    }
}
