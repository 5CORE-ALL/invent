<?php

namespace App\Http\Controllers;

use App\Exceptions\ExcelImportException;
use App\Models\ExcelImport;
use App\Models\ExcelWorksheet;
use App\Models\ExcelWorksheetRow;
use App\Services\ExcelWorkbookImportService;
use App\Services\ExcelWorksheetQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ExcelImportController extends Controller
{
    public function index(Request $request, string $account = 'ebay1'): View
    {
        $account = $this->account($account);
        $import = ExcelImport::query()
            ->ownedBy((int) $request->user()->id)
            ->forAccount($account)
            ->with('worksheets')
            ->latest('id')
            ->first();

        return view('excel.index', [
            'importPayload' => $import ? $this->importPayload($import) : null,
            'account' => $account,
            'accountLabel' => ExcelImport::ACCOUNTS[$account],
            'importUrl' => $account === 'ebay1' ? route('excel.import') : route('excel.'.$account.'.import'),
        ]);
    }

    public function import(Request $request, ExcelWorkbookImportService $importer, string $account = 'ebay1'): JsonResponse
    {
        $account = $this->account($account);
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        try {
            $import = $importer->import($request->file('file'), (int) $request->user()->id, $account);
        } catch (ExcelImportException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The workbook could not be read. Please upload a valid .xlsx or .xls file.',
            ], 500);
        }

        return response()->json([
            'message' => 'Workbook imported.',
            'import' => $this->importPayload($import),
        ]);
    }

    public function worksheet(Request $request, int $worksheet): JsonResponse
    {
        $sheet = $this->ownedWorksheet($request, $worksheet);

        return response()->json([
            'worksheet' => $this->worksheetPayload($sheet),
        ]);
    }

    public function data(Request $request, int $worksheet, ExcelWorksheetQueryService $query): JsonResponse
    {
        $sheet = $this->ownedWorksheet($request, $worksheet);

        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', 'string', 'max:191'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $paginator = $query->paginate($sheet, $filters);

        return response()->json([
            'worksheet' => $this->worksheetPayload($sheet),
            'records' => collect($paginator->items())->map(function ($row) {
                return [
                    'id' => $row->id,
                    'row_index' => (int) $row->row_index,
                    'values' => $this->rowValues($row),
                ];
            })->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'total_records' => (int) $sheet->total_rows,
        ]);
    }

    public function export(Request $request, int $worksheet, ExcelWorkbookImportService $importer): BinaryFileResponse|JsonResponse
    {
        $sheet = $this->ownedWorksheet($request, $worksheet);
        $relative = 'excel-exports/'.Str::uuid().'.xlsx';
        Storage::disk('local')->makeDirectory('excel-exports');
        $absolute = Storage::disk('local')->path($relative);

        try {
            $importer->exportTo($sheet, $absolute);
        } catch (Throwable $exception) {
            report($exception);
            if (is_file($absolute)) {
                unlink($absolute);
            }

            return response()->json(['message' => 'The worksheet could not be exported.'], 500);
        }

        $filename = $this->downloadFilename($sheet->worksheet_name);

        return response()->download($absolute, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function account(string $account): string
    {
        return array_key_exists($account, ExcelImport::ACCOUNTS) ? $account : 'ebay1';
    }

    private function ownedWorksheet(Request $request, int $worksheetId): ExcelWorksheet
    {
        return ExcelWorksheet::query()
            ->ownedBy((int) $request->user()->id)
            ->whereKey($worksheetId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function importPayload(ExcelImport $import): array
    {
        $import->loadMissing('worksheets');
        $selected = $import->worksheets->first(fn (ExcelWorksheet $sheet) => $sheet->total_rows > 0)
            ?? $import->worksheets->first();

        return [
            'id' => $import->id,
            'original_filename' => $import->original_filename,
            'total_worksheets' => (int) $import->total_worksheets,
            'selected_worksheet_id' => $selected?->id,
            'worksheets' => $import->worksheets->map(fn (ExcelWorksheet $sheet) => $this->worksheetPayload($sheet))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function worksheetPayload(ExcelWorksheet $sheet): array
    {
        return [
            'id' => $sheet->id,
            'worksheet_name' => $sheet->worksheet_name,
            'worksheet_index' => (int) $sheet->worksheet_index,
            'total_rows' => (int) $sheet->total_rows,
            'headers' => array_values($sheet->headers ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowValues(ExcelWorksheetRow $row): array
    {
        $data = $row->data;
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }

    private function downloadFilename(string $worksheetName): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_&.()+ ]+/u', '', $worksheetName) ?? '';
        $name = trim($name);

        return mb_substr($name !== '' ? $name : 'worksheet', 0, 80).'.xlsx';
    }
}
