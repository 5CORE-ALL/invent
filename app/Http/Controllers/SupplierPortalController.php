<?php

namespace App\Http\Controllers;

use App\Models\SupplierPortalAsset;
use App\Models\SupplierPortalSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPortalController extends Controller
{
    public function index()
    {
        if (! Schema::hasTable('supplier_portal_assets')) {
            abort(503, 'Supplier Portal is not ready yet.');
        }

        $settings = SupplierPortalSetting::current();
        $grouped = $this->assetsByCategory();

        return view('supplier-portal.public', [
            'settings' => $settings,
            'grouped' => $grouped,
            'section' => null,
            'title' => $settings->company_name.' Supplier Portal',
        ]);
    }

    public function section(string $category)
    {
        $category = strtolower(trim($category));
        if (! isset(SupplierPortalAsset::CATEGORIES[$category])) {
            abort(404);
        }

        $settings = SupplierPortalSetting::current();
        $grouped = $this->assetsByCategory();

        return view('supplier-portal.public', [
            'settings' => $settings,
            'grouped' => $grouped,
            'section' => $category,
            'title' => SupplierPortalAsset::CATEGORIES[$category].' — '.$settings->company_name,
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
            ->get()
            ->groupBy('category');

        $out = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $out[$key] = $all->get($key, collect());
        }

        return $out;
    }
}
