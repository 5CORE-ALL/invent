<?php

namespace App\Http\Controllers;

use App\Models\SupplierPortalAsset;
use App\Models\SupplierPortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupplierPortalAdminController extends Controller
{
    public function index(): View
    {
        $settings = SupplierPortalSetting::current();
        $grouped = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $grouped[$key] = SupplierPortalAsset::query()
                ->where('category', $key)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return view('supplier-portal.admin', [
            'title' => 'Supplier Portal',
            'settings' => $settings,
            'grouped' => $grouped,
            'categories' => SupplierPortalAsset::CATEGORIES,
            'publicUrl' => url('/supplier-portal'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'hero_title' => ['required', 'string', 'max:200'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],
            'announcement' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'footer_tagline' => ['nullable', 'string', 'max:200'],
            'hero_image' => ['nullable', 'image', 'max:10240'],
        ]);

        $settings = SupplierPortalSetting::current();
        $settings->fill([
            'company_name' => $data['company_name'],
            'hero_title' => $data['hero_title'],
            'hero_subtitle' => $data['hero_subtitle'] ?? null,
            'announcement' => $data['announcement'] ?? null,
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
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(SupplierPortalAsset::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
        ]);

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'ai', 'eps', 'zip', 'psd', 'tif', 'tiff'];
        $prefix = trim((string) ($data['title'] ?? ''));
        $sort = (int) ($data['sort_order'] ?? 0);
        $saved = 0;
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
                ? ($saved === 0 && count($files) === 1 ? $prefix : $prefix.' — '.$base)
                : $base;
            $safeName = Str::slug($base);
            $stored = $file->storeAs(
                'supplier-portal/'.$data['category'],
                ($safeName !== '' ? $safeName : 'file').'-'.Str::lower(Str::random(6)).'.'.$ext,
                'public'
            );

            SupplierPortalAsset::query()->create([
                'category' => $data['category'],
                'title' => mb_substr($title, 0, 160),
                'file_name' => $original,
                'file_path' => $stored,
                'mime' => $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'sort_order' => $sort + $index,
            ]);
            $saved++;
        }

        if ($saved === 0) {
            return back()->withErrors([
                'files' => $skipped !== []
                    ? 'None of the selected files could be uploaded. Use an image, PDF, AI, EPS, PSD, TIFF, or ZIP.'
                    : 'Choose one or more files to upload.',
            ]);
        }

        $message = $saved === 1
            ? '1 file uploaded. Suppliers can download it from the public page.'
            : $saved.' files uploaded. Suppliers can download them from the public page.';
        if ($skipped !== []) {
            $message .= ' Skipped: '.implode(', ', $skipped).'.';
        }

        return back()->with('success', $message);
    }

    public function updateAsset(Request $request, SupplierPortalAsset $asset): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $asset->update([
            'title' => $data['title'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Asset updated.');
    }

    public function destroyAsset(SupplierPortalAsset $asset): RedirectResponse
    {
        Storage::disk('public')->delete($asset->file_path);
        $asset->delete();

        return back()->with('success', 'Asset removed from the supplier page.');
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
}
