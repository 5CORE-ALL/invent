<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\Supplier;
use App\Models\SupplierRemark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FollowUpHistoryController extends Controller
{
    public function index()
    {
        // Get all suppliers from mfrg_progress table (may contain comma-separated values)
        $mfrgSuppliers = MfrgProgress::whereNotNull('supplier')
            ->where('supplier', '!=', '')
            ->pluck('supplier');

        // Split comma-separated suppliers and get unique names
        $uniqueSuppliers = collect();
        foreach ($mfrgSuppliers as $supplierString) {
            $names = explode(',', $supplierString);
            foreach ($names as $name) {
                $trimmed = trim($name);
                if ($trimmed !== '') {
                    $uniqueSuppliers->push($trimmed);
                }
            }
        }

        $suppliers = $uniqueSuppliers->unique()->sort()->values()->map(function($name) {
            return (object)['name' => $name];
        });

        $totalRemarks = SupplierRemark::count();

        return view('purchase-master.follow-up-history.index', [
            'suppliers' => $suppliers,
            'totalRemarks' => $totalRemarks,
        ]);
    }

    public function getRemarks(Request $request)
    {
        try {
            $filterSupplierName = $request->input('supplier_name');
            
            // Get all suppliers from mfrg_progress table (may contain comma-separated values)
            $mfrgSuppliers = MfrgProgress::whereNotNull('supplier')
                ->where('supplier', '!=', '')
                ->pluck('supplier');

            // Split comma-separated suppliers and get unique names
            $uniqueSuppliers = collect();
            foreach ($mfrgSuppliers as $supplierString) {
                $names = explode(',', $supplierString);
                foreach ($names as $name) {
                    $trimmed = trim($name);
                    if ($trimmed !== '') {
                        $uniqueSuppliers->push($trimmed);
                    }
                }
            }

            $uniqueSuppliers = $uniqueSuppliers->unique()->sort()->values();
            $data = [];
            
            foreach ($uniqueSuppliers as $supplierName) {
                // Get only the LATEST remark for each supplier
                $latestRemark = SupplierRemark::where('supplier_name', $supplierName)
                    ->orderByDesc('created_at')
                    ->first();
                
                if (!$latestRemark) {
                    // Show supplier with blank remark
                    $data[] = [
                        'id' => null,
                        'supplier_name' => $supplierName,
                        'remark' => '',
                        'created_by' => null,
                        'created_at' => null,
                        'updated_at' => null,
                        'has_history' => false,
                    ];
                } else {
                    // Show only the latest remark
                    $remarkData = $latestRemark->toArray();
                    // Check if there are more remarks (history)
                    $totalRemarks = SupplierRemark::where('supplier_name', $supplierName)->count();
                    $remarkData['has_history'] = $totalRemarks > 1;
                    $remarkData['total_count'] = $totalRemarks;
                    $data[] = $remarkData;
                }
            }
            
            // Filter by supplier name if provided
            if ($filterSupplierName && $filterSupplierName !== 'all') {
                $data = array_filter($data, function($item) use ($filterSupplierName) {
                    return $item['supplier_name'] === $filterSupplierName;
                });
                $data = array_values($data); // Re-index array
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting remarks: '.$e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'supplier_name' => 'required|string',
                'remark' => 'required|string',
            ]);

            $supplierRemark = SupplierRemark::create([
                'supplier_name' => $request->supplier_name,
                'remark' => $request->remark,
                'created_by' => $request->user()->name ?? 'Unknown',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Remark saved successfully.',
                'data' => $supplierRemark,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving remark: '.$e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $remark = SupplierRemark::findOrFail($id);
            $remark->delete();

            return response()->json([
                'success' => true,
                'message' => 'Remark deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting remark: '.$e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getSupplierRemarks($supplierName)
    {
        try {
            $remarks = SupplierRemark::where('supplier_name', $supplierName)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $remarks,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting supplier remarks: '.$e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }
}
