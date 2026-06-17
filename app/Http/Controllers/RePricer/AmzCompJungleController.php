<?php

namespace App\Http\Controllers\RePricer;

use App\Http\Controllers\Controller;
use App\Models\AmzCompJungleKw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AmzCompJungleController extends Controller
{
    public function index()
    {
        return view('repricer.amz_comp_jungle.index');
    }

    /**
     * Return saved data (search keyword + ASINs) keyed by SKU.
     */
    public function getKws()
    {
        $rows = AmzCompJungleKw::all(['sku', 'search_kw', 'asins']);

        $data = [];
        foreach ($rows as $row) {
            $data[$row->sku] = [
                'search_kw' => $row->search_kw,
                'asins'     => is_array($row->asins) ? array_values($row->asins) : [],
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Return competitor ASINs (from the main Amazon competitor source,
     * amazon_sku_competitors) keyed by a normalised SKU key, ordered by price
     * and capped at 10 per SKU. Used to pre-fill the table/% by default.
     *
     * Response: { success: true, data: { NORMALISED_SKU: [asin1, asin2, ...] } }
     */
    public function getCompetitorAsins()
    {
        $grouped = \App\Models\AmazonSkuCompetitor::buildGroupedLookup('amazon');
        $details = $grouped['details'] ?? collect();

        $data = [];
        foreach ($details as $key => $items) {
            $asins = [];
            foreach ($items as $item) {
                $asin = trim((string) ($item->asin ?? ''));
                if ($asin === '' || in_array($asin, $asins, true)) {
                    continue;
                }
                $asins[] = $asin;
                if (count($asins) >= 10) {
                    break;
                }
            }
            if (!empty($asins)) {
                $data[$key] = $asins;
            }
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create or update the 10 competitor ASINs for a SKU.
     */
    public function saveAsins(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku'     => 'required|string|max:255',
            'asins'   => 'nullable|array|max:10',
            'asins.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Normalise to exactly 10 slots, trimming each value.
        $asins = [];
        for ($i = 0; $i < 10; $i++) {
            $val = isset($data['asins'][$i]) ? trim((string) $data['asins'][$i]) : '';
            $asins[$i] = $val !== '' ? $val : null;
        }

        $kw = AmzCompJungleKw::updateOrCreate(
            ['sku' => $data['sku']],
            ['asins' => $asins]
        );

        return response()->json(['success' => true, 'data' => $kw]);
    }

    /**
     * Create or update the search keyword for a SKU.
     */
    public function saveKw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku'       => 'required|string|max:255',
            'search_kw' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $kw = AmzCompJungleKw::updateOrCreate(
            ['sku' => $data['sku']],
            ['search_kw' => $data['search_kw'] ?? null]
        );

        return response()->json(['success' => true, 'data' => $kw]);
    }

    /**
     * Apply the same 10 competitor ASINs to multiple SKUs (bulk edit).
     */
    public function saveAsinsBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'skus'    => 'required|array|min:1',
            'skus.*'  => 'required|string|max:255',
            'asins'   => 'nullable|array|max:10',
            'asins.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $asins = [];
        for ($i = 0; $i < 10; $i++) {
            $val = isset($data['asins'][$i]) ? trim((string) $data['asins'][$i]) : '';
            $asins[$i] = $val !== '' ? $val : null;
        }

        foreach (array_unique($data['skus']) as $sku) {
            AmzCompJungleKw::updateOrCreate(
                ['sku' => $sku],
                ['asins' => $asins]
            );
        }

        return response()->json(['success' => true, 'asins' => $asins]);
    }

    /**
     * Download a CSV import template:
     * sku, search_kw, asin_1 ... asin_10
     */
    public function importTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="amz-comp-jungle-template.csv"',
        ];

        $columns = ['sku', 'search_kw'];
        for ($i = 1; $i <= 10; $i++) {
            $columns[] = 'asin_' . $i;
        }

        $callback = function () use ($columns) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($out, $columns);
            // Example row to guide the user.
            fputcsv($out, ['SAMPLE-SKU-001', 'wireless earbuds', 'B0EXAMPLE1', 'B0EXAMPLE2', '', '', '', '', '', '', '', '']);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import search keywords + ASINs from a CSV file.
     * Expected header: sku, search_kw, asin_1 ... asin_10 (case/space insensitive).
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Please upload a valid CSV file.'], 422);
        }

        $content = file_get_contents($request->file('file')->getRealPath());
        if (substr($content, 0, 3) === "\xef\xbb\xbf") {
            $content = substr($content, 3);
        }

        $tmp = tmpfile();
        fwrite($tmp, $content);
        rewind($tmp);

        $map = null;
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($tmp)) !== false) {
            if ($row === [null] || count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            // Header row: build column index map (normalised: lowercase, spaces -> underscore).
            if ($map === null) {
                $header = array_map(fn($h) => str_replace(' ', '_', strtolower(trim((string) $h))), $row);
                $map = [];
                foreach ($header as $idx => $name) {
                    $map[$name] = $idx;
                }
                if (! isset($map['sku'])) {
                    fclose($tmp);
                    return response()->json([
                        'success' => false,
                        'message' => 'CSV must contain a "sku" column. Expected header: sku, search_kw, asin_1 ... asin_10',
                    ], 422);
                }
                continue;
            }

            $get = fn($col) => isset($map[$col]) ? trim((string) ($row[$map[$col]] ?? '')) : '';

            $sku = $get('sku');
            if ($sku === '') {
                $skipped++;
                continue;
            }

            $asins = [];
            $hasAsin = false;
            for ($i = 1; $i <= 10; $i++) {
                $val = $get('asin_' . $i);
                $asins[$i - 1] = $val !== '' ? $val : null;
                if ($val !== '') {
                    $hasAsin = true;
                }
            }

            $values = [];
            if (isset($map['search_kw'])) {
                $values['search_kw'] = $get('search_kw') !== '' ? $get('search_kw') : null;
            }
            if ($hasAsin || $this->headerHasAsinColumns($map)) {
                $values['asins'] = $asins;
            }

            if (empty($values)) {
                $skipped++;
                continue;
            }

            AmzCompJungleKw::updateOrCreate(['sku' => $sku], $values);
            $imported++;
        }

        fclose($tmp);

        return response()->json([
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'message'  => "Imported {$imported} row(s)." . ($skipped > 0 ? " Skipped {$skipped} row(s)." : ''),
        ]);
    }

    private function headerHasAsinColumns(array $map): bool
    {
        for ($i = 1; $i <= 10; $i++) {
            if (isset($map['asin_' . $i])) {
                return true;
            }
        }

        return false;
    }
}
