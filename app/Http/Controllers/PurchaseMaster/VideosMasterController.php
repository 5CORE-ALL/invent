<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideosMasterController extends Controller
{
    /** Video columns of product_master with the name used in the download file. */
    private const VIDEO_FIELDS = [
        'video_product_overview' => 'Product Overview',
        'video_unboxing' => 'Shoppable Videos',
        'video_how_to' => 'How To',
        'video_setup' => 'Setup',
        'video_troubleshooting' => 'Troubleshooting',
        'video_brand_story' => 'Brand Story',
        'video_product_benefits' => 'Product Benefits',
    ];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm', 'avi', 'mkv', 'wmv', 'mpg', 'mpeg'];

    /** Uploaded videos live in this folder of the public disk and are saved as /storage/videos_master/... */
    private const VIDEO_DIR = 'videos_master';

    private const MAX_VIDEO_BYTES = 2 * 1024 * 1024 * 1024;

    public function videosMaster()
    {
        return view('videos-master');
    }

    /**
     * GET /videos-master/upload-info — the size of one upload piece. Videos are sent in pieces so they pass
     * the server's upload limit however big the video is.
     */
    public function uploadInfo()
    {
        $toBytes = static function ($value): int {
            $value = trim((string) $value);
            if ($value === '' || $value === '-1') {
                return PHP_INT_MAX;
            }
            $unit = strtolower(substr($value, -1));
            $number = (float) $value;
            $multiplier = ['g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024][$unit] ?? 1;

            return (int) ($number * $multiplier);
        };
        $limit = min($toBytes(ini_get('upload_max_filesize')), $toBytes(ini_get('post_max_size')));
        $chunk = (int) max(256 * 1024, min(8 * 1024 * 1024, (int) floor($limit * 0.8)));

        return response()->json([
            'success' => true,
            'chunk_size' => $chunk,
            'max_bytes' => self::MAX_VIDEO_BYTES,
            'extensions' => self::VIDEO_EXTENSIONS,
        ]);
    }

    /**
     * POST /videos-master/upload-chunk — one piece of a video. The last piece joins the pieces, stores the
     * video and saves its link (and the Done/Uploaded status) on the SKU.
     */
    public function uploadChunk(Request $request)
    {
        try {
            $data = $request->validate([
                'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
                'index' => 'required|integer|min:0',
                'total' => 'required|integer|min:1|max:5000',
                'size' => 'required|integer|min:1|max:'.self::MAX_VIDEO_BYTES,
                'sku' => 'required|string',
                'field' => 'required|string|in:'.implode(',', array_keys(self::VIDEO_FIELDS)),
                'filename' => 'required|string|max:255',
                'chunk' => 'required|file',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The upload was rejected: '.collect($e->errors())->flatten()->first(),
            ], 422);
        }

        $extension = strtolower(pathinfo($data['filename'], PATHINFO_EXTENSION));
        if (! in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only video files can be uploaded ('.implode(', ', self::VIDEO_EXTENSIONS).').',
            ], 422);
        }
        if ($data['index'] >= $data['total']) {
            return response()->json(['success' => false, 'message' => 'Invalid upload piece.'], 422);
        }

        $product = ProductMaster::where('sku', $data['sku'])->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product with SKU "'.$data['sku'].'" not found.'], 404);
        }

        @set_time_limit(600);
        $tmp = Storage::disk('local');
        $tmpDir = 'videos_master_tmp/'.$data['upload_id'];
        if ((int) $data['index'] === 0) {
            $this->clearStaleUploads();
        }
        $tmp->putFileAs($tmpDir, $request->file('chunk'), $data['index'].'.part');

        if ((int) $data['index'] < (int) $data['total'] - 1) {
            return response()->json(['success' => true, 'done' => false]);
        }

        // Last piece: every piece must be there before the video is joined.
        for ($i = 0; $i < (int) $data['total']; $i++) {
            if (! $tmp->exists($tmpDir.'/'.$i.'.part')) {
                return response()->json(['success' => false, 'message' => 'Upload piece '.($i + 1).' is missing. Please upload again.'], 422);
            }
        }

        $public = Storage::disk('public');
        $folder = self::VIDEO_DIR.'/'.substr(hash('sha256', strtolower(trim((string) $product->sku))), 0, 20);
        $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($data['filename'], PATHINFO_FILENAME)) ?: 'video';
        $relative = $folder.'/'.$data['field'].'_'.time().'_'.substr($base, 0, 60).'.'.$extension;
        $public->makeDirectory($folder);

        $out = fopen($public->path($relative), 'wb');
        if ($out === false) {
            return response()->json(['success' => false, 'message' => 'Could not create the video file on the server.'], 500);
        }
        $written = 0;
        for ($i = 0; $i < (int) $data['total']; $i++) {
            $in = fopen($tmp->path($tmpDir.'/'.$i.'.part'), 'rb');
            if ($in === false) {
                fclose($out);
                $public->delete($relative);

                return response()->json(['success' => false, 'message' => 'Could not read an upload piece. Please upload again.'], 500);
            }
            $written += (int) stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        $tmp->deleteDirectory($tmpDir);

        if ($written !== (int) $data['size']) {
            $public->delete($relative);

            return response()->json(['success' => false, 'message' => 'The video arrived incomplete. Please upload again.'], 422);
        }

        $field = $data['field'];
        $previous = $this->uploadedRelativePath((string) $product->getAttribute($field));
        $url = '/storage/'.$relative;
        $product->setAttribute($field, $url);
        $product->setAttribute($field.'_status', 'Done/Uploaded');
        $product->save();
        if ($previous !== null && $previous !== $relative && $public->exists($previous)) {
            $public->delete($previous);
        }

        return response()->json([
            'success' => true,
            'done' => true,
            'url' => $url,
            'status' => 'Done/Uploaded',
            'message' => 'Video uploaded.',
        ]);
    }

    /**
     * GET /videos-master/download?sku=...&field=... — download the video uploaded for a SKU.
     * Links that point to another site (Dropbox, YouTube...) are not files of this server.
     */
    public function download(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|string',
            'field' => 'required|string|in:'.implode(',', array_keys(self::VIDEO_FIELDS)),
        ]);

        $product = ProductMaster::where('sku', $data['sku'])->first();
        $relative = $product ? $this->uploadedRelativePath((string) $product->getAttribute($data['field'])) : null;
        if ($relative === null || ! Storage::disk('public')->exists($relative)) {
            abort(404, 'No uploaded video for this SKU and field.');
        }

        $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim((string) $product->sku).' '.self::VIDEO_FIELDS[$data['field']]);
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return Storage::disk('public')->download($relative, trim($name, '_').'.'.$extension);
    }

    /** The path inside the public disk when the saved link is a video uploaded here, otherwise null. */
    private function uploadedRelativePath(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, '..')) {
            return null;
        }
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) ? ltrim($path, '/') : '';
        if (! str_starts_with($path, 'storage/'.self::VIDEO_DIR.'/')) {
            return null;
        }

        return substr($path, strlen('storage/'));
    }

    /** Pieces of uploads that were never finished are removed after a day. */
    private function clearStaleUploads(): void
    {
        try {
            $disk = Storage::disk('local');
            foreach ($disk->directories('videos_master_tmp') as $dir) {
                if ($disk->lastModified($dir) < now()->subDay()->getTimestamp()) {
                    $disk->deleteDirectory($dir);
                }
            }
        } catch (\Throwable $e) {
            Log::info('Videos Master: could not clean unfinished uploads: '.$e->getMessage());
        }
    }

    public function getVideosMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function ($item) {
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch amazon data view with buyer and seller links
        $amazonDataViews = \App\Models\AmazonDataView::all()->keyBy(function ($item) {
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch junglescout data for LQS (Listing Quality Score)
        $junglescoutData = \DB::table('junglescout_product_data')
            ->get()
            ->keyBy(function ($item) {
                return str_replace("\u{00a0}", ' ', $item->sku);
            });

        // Prepare data
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                // Add video fields directly from product model
                'video_product_overview' => $product->video_product_overview,
                'video_product_overview_status' => $product->video_product_overview_status,
                'video_unboxing' => $product->video_unboxing,
                'video_unboxing_status' => $product->video_unboxing_status,
                'video_how_to' => $product->video_how_to,
                'video_how_to_status' => $product->video_how_to_status,
                'video_setup' => $product->video_setup,
                'video_setup_status' => $product->video_setup_status,
                'video_troubleshooting' => $product->video_troubleshooting,
                'video_troubleshooting_status' => $product->video_troubleshooting_status,
                'video_brand_story' => $product->video_brand_story,
                'video_brand_story_status' => $product->video_brand_story_status,
                'video_product_benefits' => $product->video_product_benefits,
                'video_product_benefits_status' => $product->video_product_benefits_status,
            ];

            // Merge the Values array (if not null)
            if (is_array($product->Values)) {
                $row = array_merge($row, $product->Values);
            } elseif (is_string($product->Values)) {
                $values = json_decode($product->Values, true);
                if (is_array($values)) {
                    $row = array_merge($row, $values);
                }
            }

            // Normalize the product SKU for lookup
            $normalizedSku = str_replace("\u{00a0}", ' ', $product->sku);

            // Add Shopify data
            if (isset($shopifySkus[$normalizedSku])) {
                $shopifyData = $shopifySkus[$normalizedSku];
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float) $shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float) $shopifyData->quantity : 0;
                $row['ovl30'] = $row['shopify_quantity'];
                
                // Calculate Dil
                $inv = $row['shopify_inv'];
                $ovl30 = $row['shopify_quantity'];
                $dil = ($inv > 0) ? ($ovl30 / $inv) * 100 : 0;
                $row['dil'] = round($dil, 2);
                
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $row['ovl30'] = 0;
                $row['dil'] = 0;
                $shopifyImage = null;
            }

            // Set image path
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage;
            } elseif ($localImage) {
                $row['image_path'] = '/'.ltrim($localImage, '/');
            } else {
                $row['image_path'] = null;
            }

            // Add Amazon buyer and seller links
            if (isset($amazonDataViews[$normalizedSku])) {
                $amazonData = $amazonDataViews[$normalizedSku];
                $amazonValue = is_array($amazonData->value) ? $amazonData->value : json_decode($amazonData->value, true);
                $row['buyer_link'] = $amazonValue['buyer_link'] ?? null;
                $row['seller_link'] = $amazonValue['seller_link'] ?? null;
            } else {
                $row['buyer_link'] = null;
                $row['seller_link'] = null;
            }

            // Add Junglescout LQS data
            if (isset($junglescoutData[$normalizedSku])) {
                $jsData = $junglescoutData[$normalizedSku];
                $jsDataValue = is_array($jsData->data) ? $jsData->data : json_decode($jsData->data, true);
                $row['lqs'] = $jsDataValue['listing_quality_score'] ?? null;
            } else {
                $row['lqs'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200,
        ]);
    }
}
