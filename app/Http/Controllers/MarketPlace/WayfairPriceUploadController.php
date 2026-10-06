<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\WayfairPriceUpload;
use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use App\Services\Wayfair\WayfairUploadHealthService;
use App\Services\Wayfair\WayfairUploadSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WayfairPriceUploadController extends Controller
{
    public function status(WayfairUploadHealthService $health)
    {
        if (! Schema::hasTable('wayfair_price_uploads')) {
            return response()->json([
                'ready' => false,
                'message' => 'Run php artisan migrate to create wayfair_price_uploads.',
            ]);
        }

        return response()->json(['ready' => true] + $health->summary());
    }

    public function history(WayfairUploadHealthService $health)
    {
        if (! Schema::hasTable('wayfair_price_uploads')) {
            return response()->json(['data' => []]);
        }

        $rows = WayfairPriceUpload::query()->latest('id')->limit(100)->get()
            ->map(fn (WayfairPriceUpload $upload) => $health->present($upload));

        return response()->json(['data' => $rows]);
    }

    public function show(WayfairPriceUpload $upload, WayfairUploadHealthService $health)
    {
        return response()->json($health->present($upload));
    }

    public function generate(Request $request, WayfairPriceUploadOrchestrator $orchestrator, WayfairUploadHealthService $health)
    {
        try {
            $record = $orchestrator->generate(false, $request->boolean('force'), null, $this->actor());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => $record->status !== WayfairPriceUpload::FAILED,
            'message' => $record->error_message ?: 'Wayfair price file generated.',
            'upload' => $health->present($record),
        ]);
    }

    public function uploadNow(Request $request, WayfairPriceUploadOrchestrator $orchestrator, WayfairUploadHealthService $health)
    {
        try {
            $record = $orchestrator->generate(true, $request->boolean('force'), null, $this->actor());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $queued = $record->status === WayfairPriceUpload::QUEUED;
        $message = $queued
            ? 'Upload queued. The Wayfair upload is running in the background.'
            : ($record->error_message ?: 'Wayfair upload was not queued.');

        return response()->json([
            'success' => $record->status !== WayfairPriceUpload::FAILED,
            'queued' => $queued,
            'message' => $message,
            'upload' => $health->present($record),
        ]);
    }

    public function retry(Request $request, WayfairPriceUploadOrchestrator $orchestrator)
    {
        $count = $orchestrator->retryFailed(
            $request->filled('upload_id') ? (int) $request->input('upload_id') : null,
            $request->boolean('force')
        );

        return response()->json([
            'success' => true,
            'queued' => $count,
            'message' => $count > 0
                ? 'Upload queued. The Wayfair upload is running in the background.'
                : 'No failed Wayfair upload was eligible to retry.',
        ]);
    }

    public function setEnabled(Request $request, WayfairUploadSettings $settings)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $settings->setEnabled($request->boolean('enabled'));

        return response()->json([
            'success' => true,
            'enabled' => $settings->enabled(),
        ]);
    }

    public function download(WayfairPriceUpload $upload)
    {
        if (! $upload->file_path) {
            abort(404);
        }
        $root = realpath(storage_path('app/wayfair'));
        $path = realpath(storage_path('app/'.$upload->file_path));
        if (! $root || ! $path || ! str_starts_with($path, $root)) {
            abort(404);
        }

        return response()->download($path, $upload->filename ?: basename($path));
    }

    private function actor(): string
    {
        $id = auth()->id();

        return $id ? (string) $id : 'page';
    }
}
