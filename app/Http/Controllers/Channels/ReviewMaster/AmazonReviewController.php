<?php

namespace App\Http\Controllers\Channels\ReviewMaster;

use App\Http\Controllers\Controller;
use App\Models\AmazonProductReview;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AmazonReviewController extends Controller
{
    public function index()
    {
        return view('channels.review-masters.amazon-product-review');
    }

    public function fetchAmazonProductReview()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $amazonReviews = AmazonProductReview::whereIn('sku', $skus)->get()->keyBy('sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $shopify = $shopifyData[$pm->sku] ?? null;
            $amazon  = $amazonReviews[$pm->sku] ?? null;

            $inv = $shopify->inv ?? 0;

            // Skip rows where INV is 0 or less
            if (floatval($inv) <= 0) {
                continue;
            }

            $row = [];
            $row['Parent'] = $pm->parent;
            $row['Sku'] = $pm->sku;
            $row['INV'] = $inv;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['image_path'] = $shopify->image_src ?? null;

            $row['product_rating']     = $amazon->product_rating ?? null;
            $row['review_count']       = $amazon->review_count ?? null;
            $row['link']               = $amazon->link ?? null;
            $row['remarks']            = $amazon->remarks ?? null;
            $row['comp_link']          = $amazon->comp_link ?? null;
            $row['comp_rating']        = $amazon->comp_rating ?? null;
            $row['comp_review_count']  = $amazon->comp_review_count ?? null;
            $row['comp_remarks']       = $amazon->comp_remarks ?? null;
            $row['negation_l90']       = $amazon->negation_l90 ?? null;
            $row['action']             = $amazon->action ?? null;
            $row['corrective_action']  = $amazon->corrective_action ?? null;

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function createUpdateProductReview(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string|max:255',
            'field' => 'required|string',
            'value' => 'nullable'
        ]);

        $record = AmazonProductReview::firstOrNew(['sku' => $request->sku]);

        $record->{$request->field} = $request->value;

        $record->save();

        return response()->json([
            'success' => true,
            'sku' => $record->sku,
            'message' => 'Record saved successfully'
        ]);
    }

    public function importProductReview(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('excel_file');
        $spreadsheet = IOFactory::load($file->getPathName());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows) || count($rows) < 2) {
            return back()->withErrors(['excel_file' => 'The file is empty or invalid.']);
        }

        $headers = array_map(function ($h) {
            return strtolower(trim($h));
        }, $rows[0]);

        unset($rows[0]);

        foreach ($rows as $row) {
            if (empty($row[0])) {
                continue;
            }

            $rowData = array_combine($headers, $row);

            AmazonProductReview::updateOrCreate(
                ['sku' => $rowData['sku']],
                [
                    'product_rating'       => $rowData['product_rating'] ?? null,
                    'review_count'         => $rowData['review_count'] ?? null,
                    'link'                 => $rowData['link'] ?? null,
                    'remarks'              => $rowData['remarks'] ?? null,
                    'comp_link'            => $rowData['comp_link'] ?? null,
                    'comp_rating'          => $rowData['comp_rating'] ?? null,
                    'comp_review_count'    => $rowData['comp_review_count'] ?? null,
                    'comp_remarks'         => $rowData['comp_remarks'] ?? null,
                    'negation_l90'         => $rowData['negation_l90'] ?? null,
                    'action'               => $rowData['action'] ?? null,
                    'corrective_action'    => $rowData['corrective_action'] ?? null,
                ]
            );
        }

        return back()->with('success', 'Amazon Product Reviews Imported Successfully!');
    }
}
