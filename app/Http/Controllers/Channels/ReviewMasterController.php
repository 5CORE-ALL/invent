<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NegativeReview;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReviewMasterController extends Controller
{
    
    public function index()
    {
        $reviews = NegativeReview::whereIn('rating', [1, 2, 3])->get();

        $starRatings = ['1' => 0, '2' => 0, '3' => 0, 'all' => 0];
        $resolvedStatus = ['resolved' => 0, 'pending' => 0, 'all' => 0];
        $marketplaces = [];
        $groupedListings = [];

        $globalActionCount = 0;
        $globalActionDays = 0;

        foreach ($reviews as $review) {
            $rating = (string) $review->rating;
            $starRatings[$rating]++;
            $starRatings['all']++;

            $status = strtolower(trim($review->action_status ?? 'pending'));
            $status = in_array($status, ['resolved', 'pending']) ? $status : 'pending';

            $resolvedStatus[$status]++;
            $resolvedStatus['all']++;

            $marketplace = $review->marketplace ?? 'Unknown';
            $marketplaces[$marketplace] = ($marketplaces[$marketplace] ?? 0) + 1;

            $sku = $review->sku ?? 'UNKNOWN';
            if (!isset($groupedListings[$sku])) {
                $groupedListings[$sku] = [
                    'sku' => $sku,
                    'total' => 0,
                    'star1' => 0,
                    'star2' => 0,
                    'star3' => 0,
                    'marketplaces' => [],
                    'marketplaceStars' => [],
                    'totalActions' => 0,
                    'totalActionDays' => 0,
                    'avgActionDays' => 0,
                ];
            }

            $groupedListings[$sku]['total']++;
            $groupedListings[$sku]['star' . $rating]++;

            if (!in_array($marketplace, $groupedListings[$sku]['marketplaces'])) {
                $groupedListings[$sku]['marketplaces'][] = $marketplace;
            }

            if (!isset($groupedListings[$sku]['marketplaceStars'][$marketplace])) {
                $groupedListings[$sku]['marketplaceStars'][$marketplace] = ['star1' => 0, 'star2' => 0, 'star3' => 0];
            }
            $groupedListings[$sku]['marketplaceStars'][$marketplace]['star' . $rating]++;

            if (!empty($review->action_date) && !empty($review->review_date)) {
                try {
                    $actionDate = \Carbon\Carbon::parse($review->action_date);
                    $reviewDate = \Carbon\Carbon::parse($review->review_date);

                    if ($actionDate >= $reviewDate) {
                        $daysDiff = $actionDate->diffInDays($reviewDate);

                        $groupedListings[$sku]['totalActions']++;
                        $groupedListings[$sku]['totalActionDays'] += $daysDiff;

                        $globalActionCount++;
                        $globalActionDays += $daysDiff;
                    }
                } catch (\Exception $e) {
                    // Invalid dates — skip
                }
            }
        }

        foreach ($groupedListings as &$listing) {
            if ($listing['totalActions'] > 0) {
                $listing['avgActionDays'] = round($listing['totalActionDays'] / $listing['totalActions'], 1);
            }
        }

        $affectedListings = array_values($groupedListings);
        usort($affectedListings, fn($a, $b) => $b['total'] <=> $a['total']);

        arsort($marketplaces);

        $globalAvgActionDays = $globalActionCount > 0 ? round($globalActionDays / $globalActionCount, 1) : 0;

        $actionSummary = [
            'totalActions' => $globalActionCount,
            'avgActionTime' => $globalAvgActionDays,
        ];

        $totalRelevant = $resolvedStatus['resolved'] + $resolvedStatus['pending'];
        $pendingPercentage = $totalRelevant > 0 ? round(($resolvedStatus['pending'] / $totalRelevant) * 100, 1) : 0;

        return view('channels.new-review-master', compact(
            'starRatings',
            'resolvedStatus',
            'marketplaces',
            'affectedListings',
            'actionSummary',
            'pendingPercentage'
        ));
    }

    public function showAllReviews(){
        return view('channels.review-master');
    }

    public function data(Request $request)
    {
        $query = NegativeReview::whereIn('rating', [1, 2, 3]);

        if ($request->has('rating') && in_array($request->rating, [1, 2, 3])) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('action_status') && in_array($request->action_status, ['pending', 'resolved'])) {
            $query->where('action_status', $request->action_status);
        }

        if ($request->filled('marketplace')) {
            $query->where('marketplace', $request->marketplace);
        }
        return response()->json($query->get());
    }

    public function updateField(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'column' => 'required|string',
            'value' => 'nullable|string',
        ]);

        $allowedColumns = [
            'rating',
            'review_category',
            'review_text',
            'review_summary',
            'reviewer_name',
            'action_status',
            'action_taken',
            'action_date',
        ];

        if (!in_array($validated['column'], $allowedColumns)) {
            return response()->json(['success' => false, 'message' => 'Invalid column.']);
        }

        DB::table('negative_reviews')
            ->where('sku', $validated['sku'])
            ->update([
                $validated['column'] => $validated['value']
            ]);

        return response()->json(['success' => true]);
    }

    public function importNegativeReviews(Request $request)
    {
        $request->validate([
            'review_file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('review_file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            return back()->with('error', 'No data found in the sheet.');
        }

        $header = array_map(fn($h) => strtolower(trim($h)), $rows[0]);

        foreach (array_slice($rows, 1) as $index => $row) {
            $data = array_combine($header, $row);

            if (empty($data['marketplace']) || empty($data['sku'])) {
                continue;
            }

            $reviewDate = $this->convertDate($data['review_date'] ?? null);

            // Skip if review_date is invalid or null
            if (empty($reviewDate)) {
                continue;
            }

            NegativeReview::create([
                'review_date'     => $reviewDate,
                'marketplace'     => $data['marketplace'],
                'sku'             => $data['sku'],
                'rating'          => $data['rating'],
                'review_category' => $data['review_category'] ?? null,
                'review_text'     => $data['review_text'] ?? null,
                'review_summary'  => $data['review_summary'] ?? null,
                'reviewer_name'   => $data['reviewer_name'] ?? null,
                'action_status'   => $data['action_status'] ?? 'Pending',
                'action_taken'    => $data['action_taken'] ?? null,
                'action_date'     => $this->convertDate($data['action_date'] ?? null),
            ]);
        }


        return back()->with('success', 'Negative reviews imported successfully.');
    }

    public function exportNegativeReviews()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->fromArray([
            'Review Date', 'Marketplace', 'SKU', 'Rating', 'Review Category',
            'Review Text', 'Review Summary', 'Reviewer Name',
            'Action Status', 'Action Taken', 'Action Date'
        ], null, 'A1');

        $headerRange = 'A1:K1';
         $sheet->getStyle($headerRange)->getFont()->setBold(true);

        $reviews = NegativeReview::all();

        $rowNumber = 2;
        foreach ($reviews as $review) {
            $sheet->fromArray([
                $review->review_date,
                $review->marketplace,
                $review->sku,
                str_repeat('⭐', (int)$review->rating),
                $review->review_category,
                $review->review_text,
                $review->review_summary,
                $review->reviewer_name,
                $review->action_status,
                $review->action_taken,
                $review->action_date,
            ], null, 'A' . $rowNumber++);
        }

        $fileName = 'negative_reviews_export_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = storage_path('app/public/' . $fileName);
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    private function convertDate($val)
    {
        try {
            if (is_numeric($val)) {
                return Date::excelToDateTimeObject($val)->format('Y-m-d');
            } elseif (!empty($val)) {
                return Carbon::parse($val)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }
}
