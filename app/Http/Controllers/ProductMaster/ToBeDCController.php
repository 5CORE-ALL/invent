<?php

namespace App\Http\Controllers\ProductMaster;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToBeDCController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'google_sheet_data';
        $cacheTTL = 60 * 5;                        
        $perPage  = 50;                            
        $search = $request->get('search', '');

        $allRows = Cache::remember($cacheKey, $cacheTTL, function () {
            $url = 'https://script.google.com/macros/s/AKfycbxqlt0ehzuyP_dRPIIFF_SoPQH-tVD4EEHmAMmPrchUTv2LutGvL4XHXTnJarVP02vxEg/exec';
            $response = Http::timeout(10)->get($url);
            return $response->successful() ? $response->json() : [];
        });

        $allRows = collect($allRows)->map(function ($row) {
            foreach ($row as $key => $val) {
                if (is_string($val)) {
                    $v = strtolower(trim($val));
                    if ($v === 'true' || $v === 'yes')  $row[$key] = 'Yes';
                    if ($v === 'false' || $v === 'no')   $row[$key] = 'No';
                }
            }
            return $row;
        });

        // Whole search
        if ($search) {
            $allRows = $allRows->filter(function ($row) use ($search) {
                return collect($row)->some(function ($value) use ($search) {
                    $cleanValue = strtolower(preg_replace('/\s+/', '', (string) $value));
                    $cleanSearch = strtolower(preg_replace('/\s+/', '', $search));
                    return str_contains($cleanValue, $cleanSearch);
                });
            });
        }

        $allRows = $allRows->values()->all();

        $page       = LengthAwarePaginator::resolveCurrentPage();
        $pageRows   = array_slice($allRows, ($page - 1) * $perPage, $perPage);
        $paginator  = new LengthAwarePaginator(
            $pageRows,
            count($allRows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('product-master.tobedc', ['data' => $paginator]);
    }

}
