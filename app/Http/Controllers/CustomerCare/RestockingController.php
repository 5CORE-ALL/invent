<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ProductMaster;
use App\Models\Restocking;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RestockingController extends Controller
{
    private const IMAGE_COLUMNS = ['image_path', 'main_image', 'main_image_brand', 'image1', 'image2', 'image3'];

    public function index(): View
    {
        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
            ->orderBy('channel')
            ->pluck('channel')
            ->filter()
            ->values()
            ->all();

        return view('customer-care.restocking', ['channels' => $channels]);
    }

    public function list(): JsonResponse
    {
        $records = Restocking::query()->orderByDesc('date')->orderByDesc('id')->get();
        $images = $this->imagesForSkus($records->pluck('sku')->all());

        return response()->json([
            'data' => $records->map(fn (Restocking $r) => $this->present($r, $images))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = trim((string) (Auth::user()?->name ?? '')) ?: null;

        $record = Restocking::query()->create($data);

        return response()->json([
            'success' => true,
            'row' => $this->present($record, $this->imagesForSkus([$record->sku])),
        ]);
    }

    public function update(Request $request, Restocking $restocking): JsonResponse
    {
        $restocking->fill($this->validated($request, true))->save();

        return response()->json([
            'success' => true,
            'row' => $this->present($restocking, $this->imagesForSkus([$restocking->sku])),
        ]);
    }

    public function destroy(Restocking $restocking): JsonResponse
    {
        $restocking->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'date' => 'nullable|date',
            'order_id' => 'nullable|string|max:100',
            'channel' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:191',
            'qty' => 'nullable|integer|min:0|max:100000',
            'selling_price' => 'nullable|numeric|min:0',
            'refunded_amount' => 'nullable|numeric|min:0',
            'recovered_price' => 'nullable|numeric|min:0',
            'restocked' => 'nullable|boolean',
        ];

        $validated = $request->validate($rules);

        // On partial (inline) edits only touch the fields that were actually sent.
        $data = $partial ? array_intersect_key($validated, $request->all()) : $validated;

        foreach (['order_id', 'channel', 'sku'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;
            }
        }
        if (array_key_exists('qty', $data) && $data['qty'] === null) {
            $data['qty'] = $partial ? 0 : 1;
        }
        if (! $partial && ! array_key_exists('qty', $data)) {
            $data['qty'] = 1;
        }

        return $data;
    }

    /**
     * @param  array<string, string|null>  $images  lower-cased SKU => image URL
     * @return array<string, mixed>
     */
    protected function present(Restocking $r, array $images): array
    {
        return [
            'id' => $r->id,
            'date' => $r->date?->format('Y-m-d'),
            'order_id' => $r->order_id,
            'channel' => $r->channel,
            'sku' => $r->sku,
            'qty' => (int) $r->qty,
            'selling_price' => $r->selling_price,
            'refunded_amount' => $r->refunded_amount,
            'recovered_price' => $r->recovered_price,
            'restocked' => (bool) $r->restocked,
            'image' => $images[strtolower(trim((string) $r->sku))] ?? null,
        ];
    }

    /**
     * Resolve a thumbnail per SKU from product master (falling back to the Shopify image).
     *
     * @param  array<int, string|null>  $skus
     * @return array<string, string|null>
     */
    protected function imagesForSkus(array $skus): array
    {
        $keys = collect($skus)
            ->map(fn ($s) => strtolower(trim((string) $s)))
            ->filter(fn ($k) => $k !== '')
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, $keys->count(), '?'));

        $select = ['id', 'sku'];
        $hasValues = Schema::hasColumn('product_master', 'Values');
        if ($hasValues) {
            $select[] = 'Values';
        }
        $imageColumns = array_values(array_filter(
            self::IMAGE_COLUMNS,
            fn (string $c) => Schema::hasColumn('product_master', $c)
        ));
        $select = array_merge($select, $imageColumns);

        $images = [];

        $products = ProductMaster::query()
            ->select($select)
            ->whereRaw('LOWER(TRIM(sku)) IN ('.$placeholders.')', $keys->all())
            ->get();

        foreach ($products as $p) {
            $key = strtolower(trim((string) $p->sku));
            if (isset($images[$key])) {
                continue;
            }

            $candidates = [];
            $values = $hasValues ? $p->Values : null;
            if (is_string($values)) {
                $values = json_decode($values, true);
            }
            if (is_array($values)) {
                foreach (['image_path', 'main_image', 'image1', 'image2', 'image3'] as $k) {
                    $candidates[] = $values[$k] ?? null;
                }
            }
            foreach ($imageColumns as $col) {
                $candidates[] = $p->{$col} ?? null;
            }

            foreach ($candidates as $candidate) {
                $url = $this->normalizeImageUrl(is_string($candidate) ? $candidate : null);
                if ($url) {
                    $images[$key] = $url;
                    break;
                }
            }
        }

        $missing = $keys->reject(fn ($k) => isset($images[$k]))->values();
        if ($missing->isNotEmpty()) {
            $ph = implode(',', array_fill(0, $missing->count(), '?'));
            $shopifyRows = ShopifySku::query()
                ->select(['sku', 'image_src'])
                ->whereRaw('LOWER(TRIM(sku)) IN ('.$ph.')', $missing->all())
                ->get();

            foreach ($shopifyRows as $s) {
                $key = strtolower(trim((string) $s->sku));
                $url = $this->normalizeImageUrl($s->image_src ?? null);
                if ($url && ! isset($images[$key])) {
                    $images[$key] = $url;
                }
            }
        }

        return $images;
    }

    protected function normalizeImageUrl(?string $path): ?string
    {
        $p = trim((string) ($path ?? ''));
        if ($p === '') {
            return null;
        }
        if (str_starts_with($p, '//')) {
            return 'https:'.$p;
        }
        if (preg_match('#^https?://#i', $p) || str_starts_with($p, 'data:')) {
            return $p;
        }

        return '/'.ltrim(str_replace('\\', '/', $p), '/');
    }
}
