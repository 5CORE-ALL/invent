<?php

namespace App\Http\Controllers;

use App\Models\SupplierPortalAsset;
use App\Models\SupplierPortalHeader;
use App\Models\SupplierPortalSetting;
use App\Support\SupplierPortalDimWtData;
use App\Support\SupplierPortalPackingData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPortalController extends Controller
{
    public function index()
    {
        SupplierPortalSetting::ensureDatabaseReachable();
        if (! Schema::hasTable('supplier_portal_assets')) {
            abort(503, 'Supplier Portal is not ready yet.');
        }

        $settings = SupplierPortalSetting::current();
        $grouped = $this->assetsByCategory();

        return view('supplier-portal.public', [
            'settings' => $settings,
            'grouped' => $grouped,
            'headers' => SupplierPortalHeader::groupedByCategory(),
            'section' => null,
            'title' => $settings->company_name.' Supplier Portal',
        ]);
    }

    public function section(string $category)
    {
        SupplierPortalSetting::ensureDatabaseReachable();
        $category = SupplierPortalAsset::resolveCategoryKey($category);
        if ($category === null) {
            abort(404);
        }

        $settings = SupplierPortalSetting::current();
        $grouped = $this->assetsByCategory();

        return view('supplier-portal.public', [
            'settings' => $settings,
            'grouped' => $grouped,
            'headers' => SupplierPortalHeader::groupedByCategory(),
            'section' => $category,
            'title' => SupplierPortalAsset::CATEGORIES[$category].' — '.$settings->company_name,
        ]);
    }

    public function show(SupplierPortalAsset $asset)
    {
        SupplierPortalSetting::ensureDatabaseReachable();
        if (! Schema::hasTable('supplier_portal_assets')) {
            abort(503, 'Supplier Portal is not ready yet.');
        }

        $settings = SupplierPortalSetting::current();
        $categoryKey = SupplierPortalAsset::resolveCategoryKey((string) $asset->category)
            ?? (string) $asset->category;
        $categoryLabel = SupplierPortalAsset::CATEGORIES[$categoryKey] ?? 'Files';

        $siblings = SupplierPortalAsset::query()
            ->where('category', $asset->category)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');
        $fileNumber = max(1, $siblings->search($asset->id) + 1);

        return view('supplier-portal.show', [
            'settings' => $settings,
            'asset' => $asset,
            'categoryKey' => $categoryKey,
            'categoryLabel' => $categoryLabel,
            'fileNumber' => $fileNumber,
            'title' => $asset->title.' — '.$settings->company_name,
        ]);
    }

    public function packingData(): JsonResponse
    {
        SupplierPortalSetting::ensureDatabaseReachable();

        return response()->json([
            'data' => SupplierPortalPackingData::rows(),
            'fields' => SupplierPortalPackingData::FIELDS,
        ]);
    }

    public function dimWtData(): JsonResponse
    {
        SupplierPortalSetting::ensureDatabaseReachable();

        return response()->json([
            'data' => SupplierPortalDimWtData::rows(),
        ]);
    }

    public function download(SupplierPortalAsset $asset): StreamedResponse
    {
        if (! Storage::disk('public')->exists($asset->file_path)) {
            abort(404, 'File is no longer available.');
        }

        return Storage::disk('public')->download(
            $asset->file_path,
            $asset->file_name
        );
    }

    /**
     * @return array<string, \Illuminate\Support\Collection<int, SupplierPortalAsset>>
     */
    protected function assetsByCategory(): array
    {
        $all = SupplierPortalAsset::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $out[$key] = collect();
        }
        foreach ($all as $asset) {
            $key = SupplierPortalAsset::resolveCategoryKey((string) $asset->category);
            if ($key === null || ! isset($out[$key])) {
                continue;
            }
            $out[$key]->push($asset);
        }

        return $out;
    }
}
