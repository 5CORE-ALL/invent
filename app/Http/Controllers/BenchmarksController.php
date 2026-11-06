<?php

namespace App\Http\Controllers;

use App\Models\ExcelImport;
use App\Services\ExcelBenchmarksService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class BenchmarksController extends Controller
{
    public function index(string $account = 'ebay1'): View
    {
        $account = $this->account($account);

        return view('benchmarks.index', [
            'account' => $account,
            'accountLabel' => ExcelImport::ACCOUNTS[$account],
            'dataUrl' => $account === 'ebay1' ? route('benchmarks.data') : route('benchmarks.'.$account.'.data'),
            'excelUrl' => $account === 'ebay1' ? route('excel.index') : route('excel.'.$account.'.index'),
        ]);
    }

    public function data(ExcelBenchmarksService $service, string $account = 'ebay1'): JsonResponse
    {
        return response()->json($service->get($this->account($account)));
    }

    private function account(string $account): string
    {
        return array_key_exists($account, ExcelImport::ACCOUNTS) ? $account : 'ebay1';
    }
}
