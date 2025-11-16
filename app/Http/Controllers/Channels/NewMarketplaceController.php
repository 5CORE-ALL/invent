<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\NewMarketplace;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;


class NewMarketplaceController extends Controller
{

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

         // Fetch counts from your database table
        $counts = [
            'opportunity' => NewMarketplace::where('status', 'Not Started')->count(),
            'review'      => NewMarketplace::where('status', 'Applied')->count(),
            'onboarded'   => NewMarketplace::where('status', 'Processed')->count(),
            'rejected'    => NewMarketplace::where('status', 'Rejected')->count(),
            'reapply'     => NewMarketplace::where('status', 'Resubmit')->count(),
        ];

        // Application stats if needed
        $applicationStats = [
            'this_week'     => NewMarketplace::whereBetween('apply_date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'last_30_days'  => NewMarketplace::where('apply_date', '>=', now()->subDays(30))->count(),
            'all_time'      => NewMarketplace::count(),
        ];

        $followups = NewMarketplace::where('status', 'Not Started')
            ->whereNotNull('apply_date')
            ->whereBetween('apply_date', [now()->subDays(30), now()])
            ->get()
            ->filter(function ($item) {
                $days = Carbon::parse($item->apply_date)->diffInDays(now());
                return $days > 0 && $days <= 30 && $days % 5 === 0;
            });

        // Handle filter-based AJAX (for 7-day / 30-day / all-time)
        if ($request->ajax() && $request->has('filter')) {
            $query = NewMarketplace::query();

            if ($request->filter === 'this_week') {
                $query->whereBetween('apply_date', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($request->filter === 'last_30_days') {
                $query->where('apply_date', '>=', now()->subDays(30));
            } // 'all_time' = no filter

            $data = $query->get()->map(function ($item) {
                return [
                    'id'               => $item->id,
                    'channel_name'     => $item->channel_name,
                    'type'             => $item->type,
                    'link_seller'      => $item->link_seller,
                    'applied_through'  => $item->applied_through,
                    'status'           => $item->status,
                    'apply_date'       => $item->apply_date,
                ];
            });

            return response()->json(['data' => $data]);
        }


        return view('channels.new-marketplaces', compact('counts', 'applicationStats', 'followups'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'channel_name'        => 'required|string|max:255',
            'link_customer'       => 'nullable|string',
            'type'                => 'nullable|string',
            'priority'            => 'nullable|string',
            'category_allowed'    => 'nullable|string',
            'link_seller'         => 'nullable|string',
            'last_year_traffic'   => 'nullable|integer',
            'current_traffic'     => 'nullable|integer',
            'us_presence'         => 'nullable|string',
            'us_visitor'          => 'nullable|string',
            'commission'          => 'nullable|string',
            'applied_through'     => 'nullable|string',
            'status'              => 'required|string',
            'applied_id'          => 'nullable|string',
            'password'            => 'nullable|string',
            'remarks'             => 'nullable|string',
            'apply_date'          => 'nullable|date',
        ]);

        // Save to DB
        NewMarketplace::create($validated);

        return response()->json(['status' => 'success', 'message' => 'Marketplace entry added successfully.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $marketplace = NewMarketplace::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $marketplace
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $validated = $request->validate([
            'channel_name'        => 'required|string|max:255|unique:new_marketplaces,channel_name,' . $id,
            'link_customer'       => 'nullable|string',
            'type'                => 'nullable|string',
            'priority'            => 'nullable|string',
            'category_allowed'    => 'nullable|string',
            'link_seller'         => 'nullable|string',
            'last_year_traffic'   => 'nullable|integer',
            'current_traffic'     => 'nullable|integer',
            'us_presence'         => 'nullable|string',
            'us_visitor'          => 'nullable|string',
            'commission'          => 'nullable|string',
            'applied_through'     => 'nullable|string',
            'status'              => 'required|string',
            'applied_id'          => 'nullable|string',
            'password'            => 'nullable|string',
            'remarks'             => 'nullable|string',
            'apply_date'          => 'nullable|date',
        ]);

        $marketplace = NewMarketplace::findOrFail($id);
        $marketplace->update($validated);

        return response()->json(['message' => 'Marketplace updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getChannelsFromGoogleSheet()
    {
        $response = $this->apiController->fetchDataFromChannelMasterGoogleSheet();

        if ($response->getStatusCode() === 200) {
            $data = $response->getData();

            $filteredData = array_filter($data->data, function ($item) {
                return !empty(trim($item->{'Channel '} ?? ''));
            });

            $channels = array_values(array_unique(array_map(function ($item) {
                return trim($item->{'Channel '});
            }, $filteredData)));

            return response()->json([
                'status' => 200,
                'data' => $channels
            ]);
        }

        return response()->json(['status' => 500, 'message' => 'Failed to load channels']);
    }


    public function getMarketplacesByStatus(Request $request)
    {
        $status = $request->input('status');
        $marketplaces = NewMarketplace::where('status', $status)->get();

        $data = $marketplaces->map(function ($item) {
            return [
                'id' => $item->id,
                'channel_name' => $item->channel_name,
                'type' => $item->type,
                'link_seller' => $item->link_seller,
                'applied_through' => $item->applied_through,
                'status' => $item->status,
                'apply_date' => $item->apply_date,
            ];
        });

        return response()->json(['data' => $data]);
    }


    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = fopen($request->file('csv_file'), 'r');
        $firstRow = true;

        while (($data = fgetcsv($file, 1000, ',')) !== FALSE) {
            if ($firstRow) {
                $firstRow = false; // Skip header
                continue;
            }

            // Parse and convert date from d-m-Y to Y-m-d format
            $rawDate = trim($data[16]);
            $formattedDate = null;
            if (!empty($rawDate)) {
                try {
                    $formattedDate = Carbon::createFromFormat('d-m-Y', $rawDate)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Handle invalid date format
                    $formattedDate = null;
                }
            }

            NewMarketplace::create([
                'channel_name'      => $data[0],
                'link_customer'     => $data[1] ?? null ,
                'type'              => $data[2],
                'priority'          => $data[3] ?? null,
                'category_allowed'  => $data[4] ?? null,
                'link_seller'       => $data[5] ?? null,
                'last_year_traffic' => $data[6] ?? null,
                'current_traffic'   => $data[7] ?? null,
                'us_presence'       => $data[8] ?? null,
                'us_visitors'       => $this->nullIfEmpty($data[9]  ?? null),
                'commission'        => $data[10] ?? null,
                'applied_through'   => $data[11] ?? null,
                'status'            => $data[12],
                'applied_id'        => $data[13] ?? null,
                'password'          => $data[14] ?? null,
                'remarks'           => $data[15] ?? null,
                'apply_date'        => $formattedDate, 
            ]);
        }

        fclose($file);

        return redirect()->back()->with('success', 'CSV Imported Successfully.');
    }

    private function nullIfEmpty($value)
    {
        return ($value === '' || $value === null) ? null : $value;
    }



    public function export(): StreamedResponse
    {
        $filename = 'new_marketplaces_export_' . now()->format('Ymd_His') . '.csv';
        $marketplaces = NewMarketplace::all();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'channel_name', 'link_customer', 'type', 'priority', 'category_allowed', 'link_seller',
            'last_year_traffic', 'current_traffic', 'us_presence', 'us_visitors', 'commission',
            'applied_through', 'status', 'applied_id', 'password', 'remarks', 'apply_date'
        ];

        $callback = function () use ($marketplaces, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($marketplaces as $row) {
                fputcsv($file, $row->only($columns));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }


    public function updateStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:new_marketplaces,id',
            'status' => 'required|string'
        ]);

        $marketplace = NewMarketplace::find($request->id);
        $marketplace->status = $request->status;
        $marketplace->save();

        return response()->json(['message' => 'Status updated successfully']);
    }








}
