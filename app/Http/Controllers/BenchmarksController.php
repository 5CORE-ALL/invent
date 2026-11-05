<?php

namespace App\Http\Controllers;

use App\Services\ExcelBenchmarksService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class BenchmarksController extends Controller
{
    public function index(): View
    {
        return view('benchmarks.index');
    }

    public function data(ExcelBenchmarksService $service): JsonResponse
    {
        return response()->json($service->get());
    }
}
