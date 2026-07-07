<?php

namespace App\Http\Controllers;

use App\Models\AdsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdsCategoriesController extends Controller
{
    public function index()
    {
        return view('amazon_ads.ads_categories');
    }

    /**
     * List of ads categories for the grid.
     */
    public function data(): JsonResponse
    {
        $rows = AdsCategory::orderBy('name')->get()->map(fn ($c) => [
            'id' => (int) $c->id,
            'ads_category' => (string) $c->name,
        ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Add a new ads category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:ads_categories,name'],
        ]);

        $category = AdsCategory::create([
            'name' => trim($validated['name']),
            'user_id' => Auth::id(),
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'ok' => true,
            'category' => [
                'id' => (int) $category->id,
                'ads_category' => (string) $category->name,
            ],
        ]);
    }

    /**
     * Rename an existing category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = AdsCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ads_categories', 'name')->ignore($id)],
        ]);

        $category->update(['name' => trim($validated['name'])]);

        return response()->json([
            'ok' => true,
            'category' => ['id' => (int) $category->id, 'ads_category' => (string) $category->name],
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroy(int $id): JsonResponse
    {
        AdsCategory::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Download a CSV template for bulk category upload.
     */
    public function template(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'ads_categories_template.csv';
        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ads_category']);
            fputcsv($out, ['Example Category 1']);
            fputcsv($out, ['Example Category 2']);
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Bulk-add categories from an uploaded CSV (first column = category name).
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('file')->getRealPath();
        $added = 0;
        $skipped = 0;

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['ok' => false, 'message' => 'Could not read the uploaded file.'], 422);
        }

        $first = true;
        while (($row = fgetcsv($handle)) !== false) {
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($first) {
                $first = false;
                $lower = strtolower($name);
                if ($lower === 'ads_category' || $lower === 'name' || $lower === 'category') {
                    continue; // header row
                }
            }
            if (AdsCategory::where('name', $name)->exists()) {
                $skipped++;
                continue;
            }
            AdsCategory::create([
                'name' => $name,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]);
            $added++;
        }
        fclose($handle);

        return response()->json([
            'ok' => true,
            'added' => $added,
            'skipped' => $skipped,
        ]);
    }
}
