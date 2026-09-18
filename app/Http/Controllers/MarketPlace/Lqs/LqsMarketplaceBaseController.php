<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\Controller;
use App\Services\Lqs\LqsMarketplacePageService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class LqsMarketplaceBaseController extends Controller
{
    abstract protected function slug(): string;

    abstract protected function viewName(): string;

    public function __construct(protected LqsMarketplacePageService $service)
    {
    }

    public function view()
    {
        $config = $this->service->config($this->slug());

        return view($this->viewName(), [
            'lqsPage' => $config + [
                'routes' => $this->pageRoutes(),
                'has_sheet' => true,
                'has_api_metrics' => ! empty($config['metrics']),
                'has_audit' => $this->slug() === 'ebay',
                'has_yoast_seo' => $this->slug() === 'shopify',
            ],
        ]);
    }

    public function data()
    {
        try {
            return response()->json($this->service->tableRows($this->slug()));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function saveAction(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'action' => 'required|string|max:100',
            ]);

            $entry = $this->service->saveAction($this->slug(), $request->sku, $request->action);

            return response()->json([
                'success' => true,
                'id' => $entry->id,
                'action' => $entry->action,
                'user_name' => $entry->user->name ?? 'Unknown',
                'created_at' => $entry->created_at->format('d M, h:i A'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to save action'], 500);
        }
    }

    public function actionHistory(string $sku)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->actionHistory($this->slug(), $sku),
        ]);
    }

    public function badgeChart(Request $request)
    {
        $metric = (string) $request->input('metric');
        if ($metric === '') {
            return response()->json(['success' => false, 'message' => 'Metric required'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $this->service->badgeHistory($this->slug(), $metric, (int) $request->input('days', 30)),
        ]);
    }

    public function cvrHistory(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->cvrHistory($this->slug(), (int) $request->input('days', 32)),
        ]);
    }

    public function downloadSample(): StreamedResponse
    {
        $config = $this->service->config($this->slug());

        return $this->downloadSpreadsheet(
            [[
                'SKU',
                $config['listing_label'],
                'LQS',
                'Rating',
                'Reviews',
                'L30',
                'Sessions',
                'Price',
            ], [
                'SAMPLE-SKU',
                '123456',
                '8.5',
                '4.6',
                '120',
                '15',
                '400',
                '29.99',
            ]],
            'lqs-'.$this->slug().'-sample.xlsx'
        );
    }

    public function downloadSheet(): StreamedResponse
    {
        $config = $this->service->config($this->slug());
        $header = ['SKU', $config['listing_label'], 'LQS', 'Rating', 'Reviews', 'L30', 'Sessions', 'Price'];
        $rows = [$header];
        foreach ($this->service->exportRows($this->slug()) as $row) {
            $rows[] = [
                $row['sku'],
                $row['listing_id'],
                $row['lqs'],
                $row['rating'],
                $row['reviews'],
                $row['l30'],
                $row['sessions'],
                $row['price'],
            ];
        }

        return $this->downloadSpreadsheet($rows, 'lqs-'.$this->slug().'.xlsx');
    }

    public function uploadSheet(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240',
            ]);

            $ext = strtolower((string) $request->file('file')->getClientOriginalExtension());
            if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                return response()->json(['success' => false, 'message' => 'Upload an xlsx, xls, or csv file.'], 422);
            }

            $path = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(fn ($col) => strtolower(trim((string) $col)), $sheet[0] ?? []);
            $map = [];
            foreach ($header as $i => $name) {
                $map[$this->normalizeHeader($name)] = $i;
            }

            $rows = [];
            foreach (array_slice($sheet, 1) as $line) {
                $rows[] = [
                    'sku' => $line[$map['sku'] ?? -1] ?? '',
                    'listing_id' => $line[$map['listing_id'] ?? -1] ?? '',
                    'lqs' => $line[$map['lqs'] ?? -1] ?? null,
                    'rating' => $line[$map['rating'] ?? -1] ?? null,
                    'reviews' => $line[$map['reviews'] ?? -1] ?? null,
                    'l30' => $line[$map['l30'] ?? -1] ?? null,
                    'sessions' => $line[$map['sessions'] ?? -1] ?? null,
                    'price' => $line[$map['price'] ?? -1] ?? null,
                ];
            }

            $count = $this->service->importScores($this->slug(), $rows);

            return response()->json([
                'success' => true,
                'message' => "Imported {$count} LQS row(s).",
                'count' => $count,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to import sheet.'], 500);
        }
    }

    protected function pageRoutes(): array
    {
        $slug = $this->slug();

        return [
            'view' => route('lqs.'.$slug.'.view'),
            'data' => route('lqs.'.$slug.'.data'),
            'action' => route('lqs.'.$slug.'.action.save'),
            'history' => url('/lqs/'.$slug.'/action-history'),
            'badge' => route('lqs.'.$slug.'.badge.chart'),
            'cvr' => route('lqs.'.$slug.'.cvr.history'),
            'sample' => route('lqs.'.$slug.'.sample'),
            'download' => route('lqs.'.$slug.'.download'),
            'upload' => route('lqs.'.$slug.'.upload'),
            'audit_prompt' => $slug === 'ebay' ? route('lqs.ebay.audit.prompt') : null,
            'audit_prompt_save' => $slug === 'ebay' ? route('lqs.ebay.audit.prompt.save') : null,
            'audit_run' => $slug === 'ebay' ? route('lqs.ebay.audit.run') : null,
            'seo_sync' => $slug === 'shopify' ? route('lqs.shopify.seo.sync') : null,
        ];
    }

    private function normalizeHeader(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace([' ', '-'], '_', $name);

        return match ($name) {
            'item_id', 'goodsid', 'goods_id', 'listingid', 'product_id', 'sku_code' => 'listing_id',
            'units', 'qty', 'quantity' => 'l30',
            'views', 'sessions_l30' => 'sessions',
            default => $name,
        };
    }

    private function downloadSpreadsheet(array $rows, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
