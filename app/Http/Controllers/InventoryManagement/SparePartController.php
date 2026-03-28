<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\SparePartDetail;
use App\Models\Supplier;
use App\Services\SparePartInventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SparePartController extends Controller
{
    public function __construct(
        protected SparePartInventoryService $inventoryService
    ) {}

    public function index()
    {
        $parentQuery = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('is_spare_part', false)
                    ->orWhereNull('is_spare_part');
            });
        if (Schema::hasTable('product_categories')) {
            $parentQuery->with(['productCategory' => static fn ($q) => $q->select('id', 'category_name')]);
        }
        $parentOptions = $parentQuery->orderBy('sku')
            ->limit(500)
            ->get(['id', 'sku', 'parent', 'category_id']);
        $allPartSkus = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('sku')
            ->limit(100000)
            ->get(['id', 'sku']);

        return view('inventory.spare_parts', [
            'parentOptions' => $parentOptions,
            'partSkus' => $allPartSkus,
            'initialSummary' => $this->buildSummary(),
        ]);
    }

    public function summary()
    {
        return response()->json($this->buildSummary());
    }

    public function sparePartsData(Request $request)
    {
        $parentId = $request->query('parent_id');
        $type = $request->query('type', 'spare');
        $hasSpareDetails = Schema::hasTable('spare_part_details');

        $q = ProductMaster::query()->whereNull('deleted_at');

        if ($type === 'spare') {
            $q->where('is_spare_part', true);
        } elseif ($type === 'parent') {
            $q->where(function ($w) {
                $w->where('is_spare_part', false)->orWhereNull('is_spare_part');
            })->whereHas('childParts');
        }
        // type === 'all': no extra filter beyond parent

        if ($parentId !== null && $parentId !== '') {
            $q->where('parent_id', (int) $parentId);
        }

        if (Schema::hasTable('product_categories')) {
            $q->with(['productCategory' => static fn ($rel) => $rel->select('id', 'category_name')]);
        }
        if ($hasSpareDetails) {
            $q->with(['sparePartDetail.supplier' => static fn ($rel) => $rel->select('id', 'name', 'company')]);
        }
        $rows = $q->orderBy('sku')
            ->limit(400)
            ->get();

        $data = $rows->map(function (ProductMaster $p) use ($hasSpareDetails) {
            $stock = $p->sku ? $this->inventoryService->totalAvailableForSku($p->sku) : 0;

            return [
                'id' => $p->id,
                'part_name' => $hasSpareDetails ? ($p->sparePartDetail?->part_name ?? '') : '',
                'msl_part' => $hasSpareDetails ? ($p->sparePartDetail?->msl_part ?? '') : '',
                'quantity' => $hasSpareDetails ? $p->sparePartDetail?->quantity : null,
                'supplier_name' => $hasSpareDetails
                    ? ($p->sparePartDetail?->supplier?->name
                        ?? $p->sparePartDetail?->supplier?->company
                        ?? null)
                    : null,
                'sku' => $p->sku,
                'category' => $p->productCategory?->category_name,
                'parent_id' => $p->parent_id,
                'parent_sku' => $p->parentPart?->sku,
                'is_spare_part' => (bool) $p->is_spare_part,
                'min_stock_level' => $p->min_stock_level,
                'reorder_level' => $p->reorder_level,
                'max_stock_level' => $p->max_stock_level,
                'lead_time_days' => $p->lead_time_days,
                'stock' => $stock,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function lowStockData(Request $request)
    {
        $parentId = $request->query('parent_id');

        $parts = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('is_spare_part', true)
            ->whereNotNull('reorder_level')
            ->when($parentId !== null && $parentId !== '', fn ($q) => $q->where('parent_id', (int) $parentId))
            ->orderBy('sku')
            ->limit(500)
            ->get();

        $data = $parts->filter(function (ProductMaster $p) {
            if (!$p->sku) {
                return false;
            }
            $stock = $this->inventoryService->totalAvailableForSku($p->sku);

            return $stock <= (int) $p->reorder_level;
        })->values()->map(function (ProductMaster $p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'reorder_level' => $p->reorder_level,
                'stock' => $this->inventoryService->totalAvailableForSku($p->sku),
                'parent_sku' => $p->parentPart?->sku,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function tree(Request $request)
    {
        $parentId = $request->query('parent_id');

        $query = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('is_spare_part', true)
            ->with(['childParts' => function ($q) {
                $q->whereNull('deleted_at')
                    ->where('is_spare_part', true)
                    ->orderBy('sku')
                    ->with(['childParts' => function ($q2) {
                        $q2->whereNull('deleted_at')->where('is_spare_part', true)->orderBy('sku');
                    }]);
            }]);

        if ($parentId !== null && $parentId !== '') {
            $query->where('parent_id', (int) $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        $nodes = $query->orderBy('sku')->limit(200)->get();

        return response()->json([
            'data' => $nodes->map(fn (ProductMaster $p) => $this->mapTreeNode($p)),
        ]);
    }

    public function updatePart(Request $request, int $id)
    {
        $part = ProductMaster::query()->whereNull('deleted_at')->findOrFail($id);

        $validated = $request->validate([
            'is_spare_part' => 'sometimes|boolean',
            'parent_id' => 'nullable|exists:product_master,id',
            'min_stock_level' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
        ]);

        if (isset($validated['parent_id']) && (int) $validated['parent_id'] === $part->id) {
            return response()->json(['message' => 'Part cannot be its own parent.'], 422);
        }

        $part->fill($validated);
        $part->save();

        return response()->json(['message' => 'Updated', 'part' => $part->only([
            'id', 'sku', 'is_spare_part', 'parent_id', 'min_stock_level', 'reorder_level', 'max_stock_level', 'lead_time_days',
        ])]);
    }

    /**
     * Create spare-part linkage + row in spare_part_details (part name, MSL-Part).
     */
    public function storeSparePartDetail(Request $request)
    {
        if (! Schema::hasTable('spare_part_details')) {
            return response()->json(['message' => 'spare_part_details table is missing. Run migrations.'], 503);
        }

        $validated = $request->validate([
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('product_master', 'id')->whereNull('deleted_at'),
            ],
            'product_master_id' => [
                'required',
                'integer',
                Rule::exists('product_master', 'id')->whereNull('deleted_at'),
            ],
            'part_name' => 'required|string|max:255',
            'msl_part' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
        ]);

        $parentId = (int) $validated['parent_id'];
        $partId = (int) $validated['product_master_id'];

        if ($parentId === $partId) {
            return response()->json(['message' => 'Parent and SKU must be different products.'], 422);
        }

        $product = ProductMaster::query()->whereNull('deleted_at')->findOrFail($partId);

        DB::transaction(function () use ($product, $parentId, $validated) {
            $product->is_spare_part = true;
            $product->parent_id = $parentId;
            $product->save();

            SparePartDetail::query()->updateOrCreate(
                ['product_master_id' => $product->id],
                [
                    'part_name' => $validated['part_name'],
                    'msl_part' => $validated['msl_part'] ?? null,
                    'quantity' => (int) $validated['quantity'],
                    'supplier_id' => isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
                ]
            );
        });

        $product->load(['sparePartDetail.supplier']);

        return response()->json([
            'message' => 'Spare part saved',
            'part' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'parent_id' => $product->parent_id,
                'part_name' => $product->sparePartDetail?->part_name,
                'msl_part' => $product->sparePartDetail?->msl_part,
                'quantity' => $product->sparePartDetail?->quantity,
                'supplier_id' => $product->sparePartDetail?->supplier_id,
            ],
        ], 201);
    }

    /**
     * All SKUs from product_master for spare-parts line dropdowns (non-deleted rows only).
     */
    public function allPartSkus(Request $request)
    {
        $limit = (int) $request->query('limit', 50000);
        $limit = max(1, min(100000, $limit));

        $base = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->orderBy('sku')
            ->limit($limit)
            ->get(['id', 'sku']);

        return response()->json([
            'data' => $rows->map(fn (ProductMaster $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
            ]),
            'meta' => [
                'total_in_db' => $total,
                'returned' => $rows->count(),
                'capped' => $total > $rows->count(),
            ],
        ]);
    }

    public function suppliers()
    {
        $rows = Supplier::query()
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'company']);

        return response()->json(['data' => $rows]);
    }

    public function searchParts(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $limit = (int) $request->query('limit', 30);
        $limit = max(1, min(50, $limit));

        $q = ProductMaster::query()->whereNull('deleted_at');
        $this->applyProductMasterSkuSearch($q, $term);

        if (Schema::hasTable('product_categories')) {
            $q->with(['productCategory' => static fn ($rel) => $rel->select('id', 'category_name')]);
        }

        $rows = $q->orderBy('sku')
            ->limit($limit)
            ->get(['id', 'sku', 'is_spare_part', 'category_id']);

        $payload = $rows->map(fn (ProductMaster $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'category' => $p->productCategory?->category_name,
            'is_spare_part' => (bool) $p->is_spare_part,
        ]);

        return response()->json(['data' => $payload]);
    }

    /**
     * Search product_master only (sku + optional title* columns). No legacy `category` column.
     * Optional match on product_categories.category_name when that table exists.
     */
    protected function applyProductMasterSkuSearch(Builder $q, string $term): void
    {
        $tokens = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
        $like = static function (string $s): string {
            return '%'.addcslashes($s, '%_\\').'%';
        };

        $titleCols = ['title60', 'title80', 'title100', 'title150'];
        $hasCategoryTable = Schema::hasTable('product_categories');

        $q->where(function ($w) use ($term, $tokens, $like, $titleCols, $hasCategoryTable) {
            $w->where(function ($skuOrTitle) use ($term, $tokens, $like, $titleCols) {
                $skuOrTitle->where(function ($skuOuter) use ($term, $tokens, $like) {
                    $skuOuter->where('sku', 'like', $like($term));
                    if (count($tokens) > 0) {
                        $skuOuter->orWhere(function ($inner) use ($tokens, $like) {
                            foreach ($tokens as $tok) {
                                $inner->where('sku', 'like', $like($tok));
                            }
                        });
                    }
                });
                foreach ($titleCols as $col) {
                    if (Schema::hasColumn('product_master', $col)) {
                        $skuOrTitle->orWhere($col, 'like', $like($term));
                    }
                }
            });
            if ($hasCategoryTable) {
                $w->orWhereHas('productCategory', function ($c) use ($term, $like) {
                    $c->where('category_name', 'like', $like($term));
                });
            }
        });
    }

    private function mapTreeNode(ProductMaster $p): array
    {
        return [
            'id' => $p->id,
            'sku' => $p->sku,
            'parent_id' => $p->parent_id,
            'children' => $p->childParts->map(fn (ProductMaster $c) => $this->mapTreeNode($c))->values()->all(),
        ];
    }

    private function buildSummary(): array
    {
        $totalSpare = ProductMaster::query()->whereNull('deleted_at')->where('is_spare_part', true)->count();

        $lowStock = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('is_spare_part', true)
            ->whereNotNull('reorder_level')
            ->get()
            ->filter(function (ProductMaster $p) {
                if (!$p->sku) {
                    return false;
                }

                return $this->inventoryService->totalAvailableForSku($p->sku) <= (int) $p->reorder_level;
            })
            ->count();

        $pendingReq = \App\Models\Requisition::query()
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->count();

        $pendingPo = \App\Models\SparePartPurchaseOrder::query()
            ->whereIn('status', ['draft', 'sent', 'partially_received'])
            ->count();

        return [
            'total_spare_parts' => $totalSpare,
            'low_stock_items' => $lowStock,
            'pending_requisitions' => $pendingReq,
            'pending_purchase_orders' => $pendingPo,
        ];
    }
}
