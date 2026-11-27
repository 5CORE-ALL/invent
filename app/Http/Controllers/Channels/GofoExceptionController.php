<?php

namespace App\Http\Controllers\Channels;

use App\Models\GofoException;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * GOFO Exceptions: damaged / oversize / discarded parcels GOFO mailed us about.
 *
 * Typing a tracking number fetches the marketplace order (order id, SKU, qty, SKU image) the same way the
 * Not Authorized tracking page does. The loss is filled from the SKU's selling price on our own site
 * (shopify_skus.price x qty) and can be overwritten by hand.
 *
 * Extends the Sales Order Fulfillment controller only to reuse its tracking -> order lookup and image helpers;
 * every action here is prefixed `ge` so nothing in the parent is overridden.
 */
class GofoExceptionController extends SalesOrderFulfillmentController
{
    /** @var list<string> */
    protected const GE_EDITABLE = [
        'mail_date', 'tracking_number', 'order_id', 'sku', 'quantity',
        'resp_dept', 'mail_response', 'cc_action', 'loss_amount', 'notes',
    ];

    protected const GE_MAX_FILES = 12;

    public function gePage(): View
    {
        return view('channels.gofo_exceptions');
    }

    public function geData(): JsonResponse
    {
        $rows = GofoException::query()
            ->orderByDesc('mail_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (GofoException $r) => $this->geRow($r))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** A blank row dated today; the user then types the tracking number into it. */
    public function geStore(Request $request): JsonResponse
    {
        $row = GofoException::query()->create([
            'mail_date' => now($this->sofTimezone())->toDateString(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'row' => $this->geRow($row)]);
    }

    public function geUpdate(Request $request, int $id): JsonResponse
    {
        $row = GofoException::query()->findOrFail($id);
        $field = (string) $request->input('field', '');
        if (! in_array($field, self::GE_EDITABLE, true)) {
            return $this->geFail('That column cannot be edited.');
        }

        $value = $request->input('value');
        $value = is_string($value) ? trim($value) : $value;
        $lookup = null;
        $duplicate = false;

        switch ($field) {
            case 'mail_date':
                if ($value === null || $value === '') {
                    $row->mail_date = null;
                    break;
                }
                try {
                    $row->mail_date = Carbon::parse((string) $value)->toDateString();
                } catch (\Throwable) {
                    return $this->geFail('Enter a valid date.');
                }
                break;

            case 'tracking_number':
                $tracking = strtoupper((string) preg_replace('/\s+/', '', (string) ($value ?? '')));
                if (strlen($tracking) > 128) {
                    return $this->geFail('Tracking number is too long.');
                }
                $row->tracking_number = $tracking !== '' ? $tracking : null;
                $lookup = $this->geApplyTracking($row);
                if ($tracking !== '') {
                    $duplicate = GofoException::query()
                        ->where('tracking_number', $tracking)
                        ->where('id', '!=', $row->id)
                        ->exists();
                }
                break;

            case 'sku':
                $sku = (string) ($value ?? '');
                $changed = strcasecmp(trim((string) $row->sku), $sku) !== 0;
                $row->sku = $sku !== '' ? mb_substr($sku, 0, 191) : null;
                if ($changed) {
                    $row->loss_manual = false;
                    $row->product_title = null;
                }
                $this->geApplySku($row);
                break;

            case 'quantity':
                if ($value === null || $value === '') {
                    $row->quantity = null;
                } elseif (is_numeric($value) && (int) $value >= 1 && (int) $value <= 100000) {
                    $row->quantity = (int) $value;
                } else {
                    return $this->geFail('Quantity must be a whole number of 1 or more.');
                }
                $this->geApplyLoss($row);
                break;

            case 'loss_amount':
                if ($value === null || $value === '') {
                    // Cleared by hand = go back to the automatic value.
                    $row->loss_manual = false;
                    $this->geApplyLoss($row);
                } elseif (is_numeric($value) && (float) $value >= 0 && (float) $value <= 10000000) {
                    $row->loss_amount = round((float) $value, 2);
                    $row->loss_manual = true;
                } else {
                    return $this->geFail('Loss must be an amount of 0 or more.');
                }
                break;

            case 'notes':
                $row->notes = $value !== null && $value !== '' ? mb_substr((string) $value, 0, 5000) : null;
                break;

            default: // order_id, resp_dept, mail_response, cc_action
                $max = $field === 'order_id' ? 128 : 191;
                $row->{$field} = $value !== null && $value !== '' ? mb_substr((string) $value, 0, $max) : null;
                if ($field === 'order_id') {
                    $row->mm_slug = null;
                    $row->channel_label = null;
                }
        }

        $row->updated_by = $request->user()?->id;
        $row->save();

        return response()->json([
            'success' => true,
            'row' => $this->geRow($row),
            'lookup' => $lookup,
            'duplicate' => $duplicate,
        ]);
    }

    public function geUpload(Request $request, int $id): JsonResponse
    {
        $row = GofoException::query()->findOrFail($id);

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ], [
            'files.*.image' => 'Only image files can be uploaded.',
            'files.*.mimes' => 'Use a JPG, PNG, GIF or WEBP image.',
            'files.*.max' => 'Each image must be 10 MB or smaller.',
        ]);

        $attachments = array_values((array) ($row->attachments ?? []));
        $files = $request->file('files', []);
        if (count($attachments) + count($files) > self::GE_MAX_FILES) {
            return $this->geFail('A row can hold up to '.self::GE_MAX_FILES.' images.');
        }

        foreach ($files as $file) {
            $path = $file->store('gofo_exceptions/'.$row->id, 'public');
            if (! is_string($path) || $path === '') {
                continue;
            }
            $attachments[] = [
                'path' => $path,
                'name' => mb_substr((string) $file->getClientOriginalName(), 0, 120),
                'size' => (int) $file->getSize(),
            ];
        }

        $row->attachments = $attachments;
        $row->updated_by = $request->user()?->id;
        $row->save();

        return response()->json(['success' => true, 'row' => $this->geRow($row)]);
    }

    public function geDeleteFile(Request $request, int $id): JsonResponse
    {
        $row = GofoException::query()->findOrFail($id);
        $path = (string) $request->input('path', '');

        $attachments = array_values((array) ($row->attachments ?? []));
        $kept = array_values(array_filter($attachments, fn ($a) => ($a['path'] ?? '') !== $path));
        if (count($kept) === count($attachments)) {
            return $this->geFail('That image is not on this row.', 404);
        }

        Storage::disk('public')->delete($path);
        $row->attachments = $kept;
        $row->updated_by = $request->user()?->id;
        $row->save();

        return response()->json(['success' => true, 'row' => $this->geRow($row)]);
    }

    public function geDestroy(int $id): JsonResponse
    {
        $row = GofoException::query()->findOrFail($id);
        foreach ((array) ($row->attachments ?? []) as $a) {
            if (! empty($a['path'])) {
                Storage::disk('public')->delete($a['path']);
            }
        }
        $row->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Fill order id / SKU / qty / image / loss from the tracking number.
     *
     * @return array{found: bool, label?: string, order_id?: string}|null null when the tracking was cleared
     */
    protected function geApplyTracking(GofoException $row): ?array
    {
        $oldSku = trim((string) $row->sku);

        foreach (['order_id', 'mm_slug', 'channel_label', 'sku', 'quantity', 'product_title', 'sku_image', 'sku_price'] as $col) {
            $row->{$col} = null;
        }

        $tracking = trim((string) $row->tracking_number);
        if ($tracking === '') {
            $row->lookup_status = null;
            $row->loss_manual = false;
            $this->geApplyLoss($row);

            return null;
        }

        @set_time_limit(90);
        $hit = $this->geFindOrderByTracking($tracking);
        if ($hit === null) {
            $row->lookup_status = 'not_found';
            $this->geApplyLoss($row);

            return ['found' => false];
        }

        $row->lookup_status = 'found';
        $row->order_id = $hit['order_id'] !== '' ? $hit['order_id'] : null;
        $row->mm_slug = $hit['slug'] !== '' ? $hit['slug'] : null;
        $row->channel_label = $hit['label'] !== '' ? $hit['label'] : null;
        $row->sku = $hit['sku'] !== '' ? mb_substr($hit['sku'], 0, 191) : null;
        $row->quantity = $hit['quantity'] ?? ($row->sku !== null ? 1 : null);
        $row->product_title = $hit['title'] !== '' ? mb_substr($hit['title'], 0, 500) : null;

        // A different SKU means a different item: forget a hand-typed loss that belonged to the old one.
        if (strcasecmp($oldSku, (string) $row->sku) !== 0) {
            $row->loss_manual = false;
        }
        $this->geApplySku($row);

        return ['found' => true, 'label' => (string) $row->channel_label, 'order_id' => (string) $row->order_id];
    }

    /**
     * Marketplace order that owns this tracking number: synced marketplace rows first (same lookup as the
     * Not Authorized tracking page), then the tracking numbers Order Fulfillment saved.
     *
     * @return array{order_id: string, slug: string, label: string, sku: string, quantity: ?int, title: string}|null
     */
    protected function geFindOrderByTracking(string $tracking): ?array
    {
        $labels = [];
        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            $slug = (string) ($channel['slug'] ?? '');
            if ($slug !== '') {
                $labels[$slug] = (string) ($channel['label'] ?? $slug);
            }
        }

        try {
            $found = $this->channelOrdersByTrackingNumber([$tracking]);
            $hit = $found !== [] ? reset($found) : null;
            if (is_array($hit) && trim((string) ($hit['order_id'] ?? '')) !== '') {
                $qty = $hit['quantity'] ?? null;
                $out = [
                    'order_id' => trim((string) $hit['order_id']),
                    'slug' => trim((string) ($hit['slug'] ?? '')),
                    'label' => trim((string) ($hit['label'] ?? '')),
                    'sku' => trim((string) ($hit['sku'] ?? '')),
                    'quantity' => is_numeric($qty) && (int) $qty >= 1 ? (int) $qty : null,
                    'title' => trim((string) ($hit['display_title'] ?? '')),
                ];

                // Some order tables (Temu, TikTok…) have no plain `sku` column: read the SKU the same way the
                // order pages do, from the order row itself.
                if ($out['sku'] === '' && $out['slug'] !== '' && (int) ($hit['row_id'] ?? 0) > 0) {
                    $out = $this->geFillFromOrderRow($out, (int) $hit['row_id']);
                }

                return $out;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            if (Schema::hasTable('order_fulfillment_trackings')) {
                $needles = array_values(array_unique([$tracking, $this->trackingLookupKey($tracking)]));
                $saved = DB::table('order_fulfillment_trackings')
                    ->whereIn('tracking_number', $needles)
                    ->whereNotNull('order_id')
                    ->where('order_id', '!=', '')
                    ->orderByDesc('id')
                    ->first(['mm_slug', 'order_id', 'sku']);
                if ($saved) {
                    $slug = trim((string) $saved->mm_slug);

                    return [
                        'order_id' => trim((string) $saved->order_id),
                        'slug' => $slug,
                        'label' => $labels[$slug] ?? ucwords(str_replace(['_', '-'], ' ', $slug)),
                        'sku' => trim((string) ($saved->sku ?? '')),
                        'quantity' => null,
                        'title' => '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * @param  array{order_id: string, slug: string, label: string, sku: string, quantity: ?int, title: string}  $out
     * @return array{order_id: string, slug: string, label: string, sku: string, quantity: ?int, title: string}
     */
    protected function geFillFromOrderRow(array $out, int $rowId): array
    {
        try {
            $query = $this->allOrdersQuery($out['slug']);
            if ($query === null) {
                return $out;
            }
            $order = $query->where($query->getModel()->getTable().'.id', $rowId)->first();
            if (! $order) {
                return $out;
            }

            $n = $this->normalizeOrderFields($out['slug'], $order);
            $candidates = $this->rowLookupSkus([
                'sku' => $n['sku'] ?? '',
                'catalog_sku' => $n['catalog_sku'] ?? '',
                'display_sku' => $n['display_sku'] ?? '',
            ]);
            if ($candidates !== []) {
                // Prefer the candidate our own catalog knows (so the image and price resolve).
                $known = ShopifySku::mapByProductSkus($candidates);
                $out['sku'] = (string) ($known->keys()->first() ?? $candidates[0]);
            }
            if ($out['quantity'] === null && is_numeric($n['quantity'] ?? null) && (int) $n['quantity'] >= 1) {
                $out['quantity'] = (int) $n['quantity'];
            }
            if ($out['title'] === '' && trim((string) ($n['display_title'] ?? '')) !== '') {
                $out['title'] = trim((string) $n['display_title']);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $out;
    }

    /** SKU image + our-site selling price for the row's SKU, then the auto loss. */
    protected function geApplySku(GofoException $row): void
    {
        $sku = trim((string) $row->sku);
        $row->sku_image = null;
        $row->sku_price = null;

        if ($sku !== '') {
            $info = $this->geSkuInfo($sku);
            $row->sku_image = $info['image'];
            $row->sku_price = $info['price'];
            if (trim((string) $row->product_title) === '' && $info['title'] !== '') {
                $row->product_title = mb_substr($info['title'], 0, 500);
            }
        }

        $this->geApplyLoss($row);
    }

    /** Loss = our-site selling price x qty, unless the user typed a loss by hand. */
    protected function geApplyLoss(GofoException $row): void
    {
        if ($row->loss_manual) {
            return;
        }
        $price = $row->sku_price;
        $qty = max(1, (int) ($row->quantity ?? 1));
        $row->loss_amount = $price !== null && (float) $price > 0 ? round((float) $price * $qty, 2) : null;
    }

    /** @return array{image: ?string, price: ?float, title: string} */
    protected function geSkuInfo(string $sku): array
    {
        $image = null;
        $price = null;
        $title = '';

        try {
            $shopify = ShopifySku::mapByProductSkus([$sku])->get($sku);
            if ($shopify) {
                $p = $shopify->price;
                $price = is_numeric($p) && (float) $p > 0 ? round((float) $p, 2) : null;
                $image = $this->resolveSkuImageUrl($shopify->image_src ?? null);
                $title = trim((string) ($shopify->product_title ?? ''));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($image === null && Schema::hasTable('product_master')) {
            try {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                $product = ProductMaster::query()
                    ->where('sku', $sku)
                    ->first(['sku', 'main_image', 'main_image_brand', 'image1']);
                if (! $product && $norm !== '') {
                    $product = ProductMaster::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$norm])
                        ->first(['sku', 'main_image', 'main_image_brand', 'image1']);
                }
                if ($product) {
                    $image = $this->resolveSkuImageUrl($product->main_image ?? null)
                        ?? $this->resolveSkuImageUrl($product->main_image_brand ?? null)
                        ?? $this->resolveSkuImageUrl($product->image1 ?? null);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ['image' => $image, 'price' => $price, 'title' => $title];
    }

    /** @return array<string, mixed> */
    protected function geRow(GofoException $r): array
    {
        $files = [];
        foreach ((array) ($r->attachments ?? []) as $a) {
            $path = (string) ($a['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $files[] = [
                'path' => $path,
                'name' => (string) ($a['name'] ?? basename($path)),
                'url' => '/storage/'.ltrim($path, '/'),
            ];
        }

        return [
            'id' => $r->id,
            'mail_date' => $r->mail_date?->format('Y-m-d'),
            'tracking_number' => $r->tracking_number,
            'order_id' => $r->order_id,
            'mm_slug' => $r->mm_slug,
            'channel_label' => $r->channel_label,
            'sku' => $r->sku,
            'quantity' => $r->quantity,
            'product_title' => $r->product_title,
            'sku_image' => $r->sku_image,
            'sku_price' => $r->sku_price,
            'lookup_status' => $r->lookup_status,
            'resp_dept' => $r->resp_dept,
            'mail_response' => $r->mail_response,
            'cc_action' => $r->cc_action,
            'loss_amount' => $r->loss_amount,
            'loss_manual' => (bool) $r->loss_manual,
            'notes' => $r->notes,
            'files' => $files,
            'files_count' => count($files),
        ];
    }

    protected function geFail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
