<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use App\Models\SupplierPortalAsset;
use App\Models\SupplierPortalHeader;
use App\Models\SupplierPortalSetting;
use App\Support\SupplierPortalDimWtData;
use App\Support\SupplierPortalPackingData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupplierPortalAdminController extends Controller
{
    public function index(): View
    {
        SupplierPortalSetting::ensureDatabaseReachable();
        SupplierPortalAsset::ensureSkuParentColumns();
        $settings = SupplierPortalSetting::current();
        $grouped = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $grouped[$key] = collect();
        }
        $assets = SupplierPortalAsset::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        foreach ($assets as $asset) {
            $key = SupplierPortalAsset::resolveCategoryKey((string) $asset->category);
            if ($key === null || ! isset($grouped[$key])) {
                continue;
            }
            $grouped[$key]->push($asset);
        }

        $activeTab = SupplierPortalAsset::resolveCategoryKey((string) request('tab'))
            ?? array_key_first(SupplierPortalAsset::CATEGORIES);

        return view('supplier-portal.admin', [
            'title' => 'Supplier Portal',
            'settings' => $settings,
            'grouped' => $grouped,
            'headers' => SupplierPortalHeader::groupedByCategory(),
            'categories' => SupplierPortalAsset::CATEGORIES,
            'activeTab' => $activeTab,
            'publicUrl' => url('/supplier-portal'),
        ]);
    }

    public function storeHeaders(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('supplier_portal_headers')) {
            return back()->withErrors(['headers' => 'Page headers are not ready yet. Run migrations first.']);
        }

        $request->validate([
            'headers' => ['nullable', 'array'],
            'headers.*' => ['nullable', 'array'],
            'headers.*.*.title' => ['nullable', 'string', 'max:200'],
            'headers.*.*.instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $posted = $request->input('headers', []);
        if (! is_array($posted)) {
            $posted = [];
        }

        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $category) {
            SupplierPortalHeader::query()->where('category', $category)->delete();
            $rows = $posted[$category] ?? [];
            if (! is_array($rows)) {
                continue;
            }
            $order = 1;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $instructions = trim((string) ($row['instructions'] ?? ''));
                if ($title === '' && $instructions === '') {
                    continue;
                }
                SupplierPortalHeader::query()->create([
                    'category' => $category,
                    'title' => $title !== '' ? mb_substr($title, 0, 200) : 'Guidelines',
                    'instructions' => $instructions !== '' ? $instructions : null,
                    'sort_order' => $order,
                ]);
                $order++;
            }
        }

        $tab = SupplierPortalAsset::resolveCategoryKey((string) $request->input('tab'))
            ?? array_key_first(SupplierPortalAsset::CATEGORIES);

        return $this->adminRedirect($tab)->with('success', 'Page headers saved.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'hero_title' => ['required', 'string', 'max:200'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'footer_tagline' => ['nullable', 'string', 'max:200'],
            'hero_image' => ['nullable', 'image', 'max:10240'],
        ]);

        $settings = SupplierPortalSetting::current();
        $settings->fill([
            'company_name' => $data['company_name'],
            'hero_title' => $data['hero_title'],
            'hero_subtitle' => $data['hero_subtitle'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'footer_tagline' => $data['footer_tagline'] ?? null,
        ]);

        if ($request->hasFile('hero_image')) {
            if ($settings->hero_image_path) {
                Storage::disk('public')->delete($settings->hero_image_path);
            }
            $settings->hero_image_path = $request->file('hero_image')->store('supplier-portal/hero', 'public');
        }

        $settings->save();

        return back()->with('success', 'Supplier Portal page text saved.');
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        SupplierPortalAsset::ensureSkuParentColumns();
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(SupplierPortalAsset::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:120'],
            'parent' => ['nullable', 'string', 'max:120'],
            'add_to_siblings' => ['sometimes', 'boolean'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
        ]);
        $sku = $this->nullableText($data['sku'] ?? null);
        $parent = $this->nullableText($data['parent'] ?? null);
        $addToSiblings = $request->boolean('add_to_siblings');
        if ($addToSiblings && $sku === null && $parent === null) {
            return back()->withErrors([
                'add_to_siblings' => 'Choose a parent or SKU before adding this file to siblings.',
            ])->withInput();
        }
        $targets = [['sku' => $sku, 'parent' => $parent]];
        if ($addToSiblings) {
            $family = $this->siblingProducts($parent, $sku);
            if ($family !== []) {
                $targets = $family;
            }
        }

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'ai', 'eps', 'zip', 'psd', 'tif', 'tiff'];
        $prefix = trim((string) ($data['title'] ?? ''));
        $sort = SupplierPortalAsset::nextSortOrder($data['category']);
        $saved = 0;
        $fileCount = 0;
        $skipped = [];

        foreach ($files as $index => $file) {
            if ($file === null) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension() ?: '');
            if (! in_array($ext, $allowed, true)) {
                $skipped[] = $file->getClientOriginalName();
                continue;
            }

            $original = $file->getClientOriginalName();
            $base = pathinfo($original, PATHINFO_FILENAME);
            $title = $prefix !== ''
                ? ($fileCount === 0 && count($files) === 1 ? $prefix : $prefix.' — '.$base)
                : $base;
            $safeName = Str::slug($base);
            $stored = $file->storeAs(
                'supplier-portal/'.$data['category'],
                ($safeName !== '' ? $safeName : 'file').'-'.Str::lower(Str::random(6)).'.'.$ext,
                'public'
            );

            foreach ($targets as $target) {
                $row = [
                    'category' => $data['category'],
                    'title' => mb_substr($title, 0, 160),
                    'file_name' => $original,
                    'file_path' => $stored,
                    'mime' => $file->getMimeType(),
                    'file_size' => (int) $file->getSize(),
                    'sort_order' => $sort,
                ];
                if (Schema::hasColumn('supplier_portal_assets', 'sku')) {
                    $row['sku'] = $target['sku'];
                }
                if (Schema::hasColumn('supplier_portal_assets', 'parent')) {
                    $row['parent'] = $target['parent'] ?? $parent;
                }
                SupplierPortalAsset::query()->create($row);
                $sort++;
                $saved++;
            }
            $fileCount++;
        }

        if ($saved === 0) {
            return back()->withErrors([
                'files' => $skipped !== []
                    ? 'None of the selected files could be uploaded. Use an image, PDF, AI, EPS, PSD, TIFF, or ZIP.'
                    : 'Choose one or more files to upload.',
            ]);
        }

        if ($addToSiblings && count($targets) > 1) {
            $message = $fileCount === 1
                ? '1 file uploaded and added to '.count($targets).' SKUs in this parent.'
                : $fileCount.' files uploaded and added to '.count($targets).' SKUs in this parent.';
        } else {
            $message = $fileCount === 1
                ? '1 file uploaded. Suppliers can download it from the public page.'
                : $fileCount.' files uploaded. Suppliers can download them from the public page.';
        }
        if ($skipped !== []) {
            $message .= ' Skipped: '.implode(', ', $skipped).'.';
        }

        return $this->adminRedirect($data['category'])->with('success', $message);
    }

    public function updateAsset(Request $request, SupplierPortalAsset $asset): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'in:'.implode(',', array_keys(SupplierPortalAsset::CATEGORIES))],
            'sku' => ['nullable', 'string', 'max:120'],
            'parent' => ['nullable', 'string', 'max:120'],
        ]);
        $payload = [
            'title' => $data['title'],
            'category' => $data['category'],
        ];
        if (Schema::hasColumn('supplier_portal_assets', 'sku')) {
            $payload['sku'] = $this->nullableText($data['sku'] ?? null);
        }
        if (Schema::hasColumn('supplier_portal_assets', 'parent')) {
            $payload['parent'] = $this->nullableText($data['parent'] ?? null);
        }
        $current = SupplierPortalAsset::resolveCategoryKey((string) $asset->category) ?? $asset->category;
        if ($current !== $data['category']) {
            $payload['sort_order'] = SupplierPortalAsset::nextSortOrder($data['category']);
        }
        $asset->update($payload);

        return $this->adminRedirect($data['category'])->with('success', 'Asset updated.');
    }

    public function destroyAsset(SupplierPortalAsset $asset): RedirectResponse
    {
        $tab = SupplierPortalAsset::resolveCategoryKey((string) $asset->category) ?? $asset->category;
        $path = $asset->file_path;
        $asset->delete();
        $stillUsed = SupplierPortalAsset::query()->where('file_path', $path)->exists();
        if (! $stillUsed) {
            Storage::disk('public')->delete($path);
        }

        return $this->adminRedirect($tab)->with('success', 'Asset removed from the supplier page.');
    }

    public function destroyHero(): RedirectResponse
    {
        $settings = SupplierPortalSetting::current();
        if ($settings->hero_image_path) {
            Storage::disk('public')->delete($settings->hero_image_path);
            $settings->hero_image_path = null;
            $settings->save();
        }

        return back()->with('success', 'Hero image removed.');
    }

    public function packingData(): JsonResponse
    {
        return response()->json([
            'data' => SupplierPortalPackingData::rows(),
            'fields' => SupplierPortalPackingData::FIELDS,
            'source' => url('/packing-instructions-master'),
        ]);
    }

    public function dimWtData(): JsonResponse
    {
        return response()->json([
            'data' => SupplierPortalDimWtData::rows(),
            'source' => url('/dim-wt-master'),
        ]);
    }

    public function storeSkuFile(Request $request): JsonResponse
    {
        SupplierPortalAsset::ensureSkuParentColumns();
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', SupplierPortalDimWtData::SKU_CATEGORIES)],
            'sku' => ['required', 'string', 'max:120'],
            'parent' => ['nullable', 'string', 'max:120'],
            'file' => ['required', 'file', 'max:51200'],
        ]);
        $file = $request->file('file');
        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: ''));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'ai', 'eps', 'zip', 'psd', 'tif', 'tiff', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
        if (! in_array($ext, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Use an image, PDF, Office document, or ZIP.',
            ], 422);
        }

        $original = $file->getClientOriginalName();
        $base = pathinfo($original, PATHINFO_FILENAME);
        $safeName = Str::slug($base);
        $stored = $file->storeAs(
            'supplier-portal/'.$data['category'],
            ($safeName !== '' ? $safeName : 'file').'-'.Str::lower(Str::random(6)).'.'.$ext,
            'public'
        );
        $asset = SupplierPortalAsset::query()->create([
            'category' => $data['category'],
            'title' => mb_substr($base !== '' ? $base : $original, 0, 160),
            'sku' => $this->nullableText($data['sku']),
            'parent' => $this->nullableText($data['parent'] ?? null),
            'file_name' => $original,
            'file_path' => $stored,
            'mime' => $file->getMimeType(),
            'file_size' => (int) $file->getSize(),
            'sort_order' => SupplierPortalAsset::nextSortOrder($data['category']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded.',
            'file' => SupplierPortalDimWtData::filePayload($asset),
        ]);
    }

    public function destroySkuFile(SupplierPortalAsset $asset): JsonResponse
    {
        $this->deletePortalAsset($asset);

        return response()->json([
            'success' => true,
            'message' => 'File removed.',
        ]);
    }

    public function destroySkuFiles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', SupplierPortalDimWtData::SKU_CATEGORIES)],
            'sku' => ['required', 'string', 'max:120'],
        ]);
        $sku = str_replace("\u{00a0}", ' ', trim($data['sku']));
        $assets = SupplierPortalAsset::query()
            ->where('category', $data['category'])
            ->where('sku', $sku)
            ->get();
        foreach ($assets as $asset) {
            $this->deletePortalAsset($asset);
        }

        return response()->json([
            'success' => true,
            'message' => $assets->isEmpty() ? 'No files to remove.' : 'Files removed.',
        ]);
    }

    private function deletePortalAsset(SupplierPortalAsset $asset): void
    {
        $path = $asset->file_path;
        $asset->delete();
        $stillUsed = SupplierPortalAsset::query()->where('file_path', $path)->exists();
        if (! $stillUsed) {
            Storage::disk('public')->delete($path);
        }
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $type = strtolower((string) $request->query('type', 'sku'));
        if (mb_strlen($q) < 1 || ! Schema::hasTable('product_master')) {
            return response()->json(['items' => []]);
        }

        if ($type === 'siblings') {
            $family = $this->siblingProducts($q, null);
            if ($family === []) {
                $family = $this->siblingProducts(null, $q);
            }

            return response()->json([
                'items' => $family,
                'count' => count($family),
            ]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        if ($type === 'parent') {
            $rows = ProductMaster::query()
                ->select('parent')
                ->whereNotNull('parent')
                ->where('parent', '!=', '')
                ->where('parent', 'like', $like)
                ->orderBy('parent')
                ->limit(80)
                ->get();

            $seen = [];
            $items = [];
            foreach ($rows as $row) {
                $parent = trim((string) ($row->parent ?? ''));
                $key = strtoupper($parent);
                if ($parent === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $items[] = ['parent' => $parent, 'sku' => ''];
                if (count($items) >= 20) {
                    break;
                }
            }

            return response()->json(['items' => $items]);
        }

        $rows = ProductMaster::query()
            ->select(['sku', 'parent'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'like', $like)
            ->orderBy('sku')
            ->limit(20)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $items[] = [
                'sku' => $sku,
                'parent' => trim((string) ($row->parent ?? '')),
            ];
        }

        return response()->json(['items' => $items]);
    }

    private function adminRedirect(?string $tab = null): RedirectResponse
    {
        $key = SupplierPortalAsset::resolveCategoryKey((string) $tab)
            ?? array_key_first(SupplierPortalAsset::CATEGORIES);

        return redirect()->route('supplier-portal.admin.index', ['tab' => $key]);
    }

    private function nullableText(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 120);
    }

    /**
     * @return list<array{sku: string, parent: string}>
     */
    private function siblingProducts(?string $parent, ?string $sku): array
    {
        $parent = $this->resolveParent($parent, $sku);
        if ($parent === null || ! Schema::hasTable('product_master')) {
            return [];
        }

        $rows = ProductMaster::query()
            ->select(['sku', 'parent'])
            ->whereRaw('UPPER(TRIM(parent)) = ?', [strtoupper($parent)])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('sku')
            ->limit(200)
            ->get();

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $child = trim((string) ($row->sku ?? ''));
            if ($child === '' || str_starts_with(strtoupper($child), 'PARENT')) {
                continue;
            }
            $key = strtoupper($child);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'sku' => $child,
                'parent' => trim((string) ($row->parent ?? '')) ?: $parent,
            ];
        }

        return $out;
    }

    private function resolveParent(?string $parent, ?string $sku): ?string
    {
        $parent = $this->nullableText($parent);
        if ($parent !== null) {
            return $parent;
        }

        $sku = $this->nullableText($sku);
        if ($sku === null || ! Schema::hasTable('product_master')) {
            return null;
        }

        $row = ProductMaster::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();
        $found = trim((string) ($row->parent ?? ''));

        return $found !== '' ? $found : null;
    }
}
