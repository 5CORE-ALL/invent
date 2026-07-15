<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\QcMastersEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QcMastersController extends Controller
{
    /** Hard cap (KB) regardless of client-requested limit. */
    private const MAX_IMAGE_KB_HARD = 2048;

    /** Default client limit (KB). */
    public const DEFAULT_IMAGE_KB_LIMIT = 500;

    /** Video hard cap (KB) — 15 MB to avoid page slowdown. */
    private const MAX_VIDEO_KB_HARD = 15360;

    /** Default video limit (KB) — 5 MB. */
    public const DEFAULT_VIDEO_KB_LIMIT = 5120;

    private function historyPayload(QcMastersEntry $row): array
    {
        $history = is_array($row->user_history) ? $row->user_history : [];

        return [
            'user_history' => $history,
            'user_history_label' => $row->latestUserHistoryLabel(),
        ];
    }

    /**
     * Save Problem/Issue and/or Suggestion/Improve for one product.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'problem_issue' => 'nullable|string|max:5000',
            'suggestion_improve' => 'nullable|string|max:5000',
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        if (! empty($validated['sku']) && strcasecmp(trim((string) $product->sku), trim((string) $validated['sku'])) !== 0) {
            return response()->json(['success' => false, 'message' => 'SKU mismatch.'], 422);
        }

        $row = QcMastersEntry::firstOrNew(['product_master_id' => $product->id]);
        $changed = false;

        if (array_key_exists('problem_issue', $validated)) {
            $row->problem_issue = mb_substr(trim((string) ($validated['problem_issue'] ?? '')), 0, 5000);
            $row->appendUserHistory('Updated Problem / Issue', 'problem_issue');
            $changed = true;
        }
        if (array_key_exists('suggestion_improve', $validated)) {
            $row->suggestion_improve = mb_substr(trim((string) ($validated['suggestion_improve'] ?? '')), 0, 5000);
            $row->appendUserHistory('Updated Suggestion / Improve', 'suggestion_improve');
            $changed = true;
        }

        if (! $changed) {
            return response()->json(['success' => false, 'message' => 'Nothing to update.'], 422);
        }

        $row->save();

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Saved.',
            'problem_issue' => (string) ($row->problem_issue ?? ''),
            'suggestion_improve' => (string) ($row->suggestion_improve ?? ''),
            'image_path' => $row->image_path ? '/storage/'.ltrim($row->image_path, '/') : null,
            'image_size_kb' => $row->image_size_kb,
        ], $this->historyPayload($row)));
    }

    /**
     * Upload or replace QC Masters image (file upload or clipboard snippet).
     * Client may pass max_kb (default 500); hard cap is 2048 KB.
     */
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'image' => 'required|file|image|mimes:jpeg,jpg,png,gif,webp',
            'max_kb' => 'nullable|integer|min:1|max:'.self::MAX_IMAGE_KB_HARD,
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        if (! empty($validated['sku']) && strcasecmp(trim((string) $product->sku), trim((string) $validated['sku'])) !== 0) {
            return response()->json(['success' => false, 'message' => 'SKU mismatch.'], 422);
        }

        $maxKb = (int) ($validated['max_kb'] ?? self::DEFAULT_IMAGE_KB_LIMIT);
        $maxKb = max(1, min($maxKb, self::MAX_IMAGE_KB_HARD));
        $maxBytes = $maxKb * 1024;

        $file = $request->file('image');
        $sizeBytes = (int) $file->getSize();
        $sizeKb = (int) ceil($sizeBytes / 1024);

        if ($sizeBytes > $maxBytes) {
            return response()->json([
                'success' => false,
                'message' => "Image is {$sizeKb} KB. Limit is {$maxKb} KB.",
            ], 422);
        }

        $row = QcMastersEntry::firstOrNew(['product_master_id' => $product->id]);

        if ($row->image_path && Storage::disk('public')->exists($row->image_path)) {
            Storage::disk('public')->delete($row->image_path);
        }

        $dir = 'qc_masters/'.$product->id;
        $stored = $file->store($dir, 'public');
        $row->image_path = $stored;
        $row->image_size_kb = $sizeKb;
        $row->appendUserHistory('Uploaded image', 'image');
        $row->save();

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Image saved.',
            'image_path' => '/storage/'.ltrim($stored, '/'),
            'image_size_kb' => $sizeKb,
        ], $this->historyPayload($row)));
    }

    /**
     * Remove QC Masters image for one product.
     */
    public function deleteImage(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $row = QcMastersEntry::where('product_master_id', $product->id)->first();
        if (! $row || ! $row->image_path) {
            return response()->json([
                'success' => true,
                'message' => 'No image.',
                'image_path' => null,
                'image_size_kb' => null,
                'user_history' => $row ? (is_array($row->user_history) ? $row->user_history : []) : [],
                'user_history_label' => $row ? $row->latestUserHistoryLabel() : '',
            ]);
        }

        if (Storage::disk('public')->exists($row->image_path)) {
            Storage::disk('public')->delete($row->image_path);
        }

        $row->image_path = null;
        $row->image_size_kb = null;
        $row->appendUserHistory('Removed image', 'image');
        $row->save();

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Image removed.',
            'image_path' => null,
            'image_size_kb' => null,
        ], $this->historyPayload($row)));
    }

    /**
     * Upload or replace QC Masters video.
     * Client may pass max_kb (default 5120 = 5 MB); hard cap is 15360 KB (15 MB).
     */
    public function uploadVideo(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'video' => 'required|file|mimetypes:video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska|mimes:mp4,webm,mov,avi,mkv',
            'max_kb' => 'nullable|integer|min:1|max:'.self::MAX_VIDEO_KB_HARD,
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        if (! empty($validated['sku']) && strcasecmp(trim((string) $product->sku), trim((string) $validated['sku'])) !== 0) {
            return response()->json(['success' => false, 'message' => 'SKU mismatch.'], 422);
        }

        $maxKb = (int) ($validated['max_kb'] ?? self::DEFAULT_VIDEO_KB_LIMIT);
        $maxKb = max(1, min($maxKb, self::MAX_VIDEO_KB_HARD));
        $maxBytes = $maxKb * 1024;

        $file = $request->file('video');
        $sizeBytes = (int) $file->getSize();
        $sizeKb = (int) ceil($sizeBytes / 1024);

        if ($sizeBytes > $maxBytes) {
            return response()->json([
                'success' => false,
                'message' => "Video is {$sizeKb} KB (~".round($sizeKb / 1024, 1).' MB). Limit is '.$maxKb.' KB.',
            ], 422);
        }

        $row = QcMastersEntry::firstOrNew(['product_master_id' => $product->id]);

        if ($row->video_path && Storage::disk('public')->exists($row->video_path)) {
            Storage::disk('public')->delete($row->video_path);
        }

        $dir = 'qc_masters/'.$product->id.'/videos';
        $stored = $file->store($dir, 'public');
        $row->video_path = $stored;
        $row->video_size_kb = $sizeKb;
        $row->appendUserHistory('Uploaded video', 'video');
        $row->save();

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Video saved.',
            'video_path' => '/storage/'.ltrim($stored, '/'),
            'video_size_kb' => $sizeKb,
        ], $this->historyPayload($row)));
    }

    /**
     * Remove QC Masters video for one product.
     */
    public function deleteVideo(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $row = QcMastersEntry::where('product_master_id', $product->id)->first();
        if (! $row || ! $row->video_path) {
            return response()->json([
                'success' => true,
                'message' => 'No video.',
                'video_path' => null,
                'video_size_kb' => null,
                'user_history' => $row ? (is_array($row->user_history) ? $row->user_history : []) : [],
                'user_history_label' => $row ? $row->latestUserHistoryLabel() : '',
            ]);
        }

        if (Storage::disk('public')->exists($row->video_path)) {
            Storage::disk('public')->delete($row->video_path);
        }

        $row->video_path = null;
        $row->video_size_kb = null;
        $row->appendUserHistory('Removed video', 'video');
        $row->save();

        return response()->json(array_merge([
            'success' => true,
            'message' => 'Video removed.',
            'video_path' => null,
            'video_size_kb' => null,
        ], $this->historyPayload($row)));
    }
}
