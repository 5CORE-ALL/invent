<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ShopifySyncRequest;
use App\Http\Requests\Crm\StoreShopifyCustomerFollowUpRequest;
use App\Http\Resources\Crm\FollowUpResource;
use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use App\Models\User;
use App\Services\Crm\Contracts\FollowUpServiceInterface;
use App\Services\Crm\Contracts\ShopifyServiceInterface;
use App\Services\Crm\Exceptions\ShopifyApiException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopifyController extends Controller
{
    public function __construct(
        protected ShopifyServiceInterface $shopifyService,
        protected FollowUpServiceInterface $followUpService
    ) {}

    public function shopifyCustomersIndex(): View
    {
        $assignees = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        if ($assignees->isEmpty()) {
            $fallback = User::query()
                ->whereKey(optional(auth()->user())->id)
                ->get(['id', 'name', 'email']);
            $assignees = $fallback;
        }

        return view('crm.shopify.customers', [
            'crmAssignees' => $assignees,
        ]);
    }

    public function storeCustomerFollowUp(StoreShopifyCustomerFollowUpRequest $request, ShopifyCustomer $shopify_customer): JsonResponse
    {
        try {
            $crmCustomer = $this->shopifyService->ensureCrmCustomerForShopifyRecord($shopify_customer);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $data = $request->validated();

        $followUp = $this->followUpService->createFollowUp([
            'customer_id' => $crmCustomer->id,
            'company_id' => $crmCustomer->company_id,
            'assigned_user_id' => (int) $data['assigned_user_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'follow_up_type' => $data['follow_up_type'],
            'priority' => $data['priority'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'reminder_at' => null,
            'next_follow_up_at' => null,
            'outcome' => null,
        ], $request->user(), ['source' => 'shopify_customers']);

        return FollowUpResource::make($followUp->loadMissing(['customer', 'assignedUser', 'company']))
            ->additional([
                'message' => 'Follow-up created.',
                'show_url' => route('crm.follow-ups.show', $followUp),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function shopifyOrdersIndex(): View
    {
        return view('crm.shopify.orders');
    }

    public function shopifyOrdersData(Request $request): JsonResponse
    {
        $perPage = max(5, min(100, (int) $request->input('per_page', 25)));

        $query = ShopifyOrder::query()
            ->with([
                'shopifyCustomer:id,shopify_customer_id,customer_id,email,first_name,last_name',
            ])
            ->select([
                'id',
                'shopify_order_id',
                'shopify_customer_id',
                'total_price',
                'currency',
                'order_status',
                'order_date',
            ])
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($q) use ($like, $term) {
                $q->where('order_status', 'like', $like);
                if ($term !== '' && ctype_digit($term)) {
                    $q->orWhere('shopify_order_id', (int) $term);
                    $q->orWhere('shopify_customer_id', (int) $term);
                }
                $q->orWhereHas('shopifyCustomer', function ($sub) use ($like) {
                    $sub->where('email', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            });
        }

        $paginator = $query->paginate($perPage);

        $rows = $paginator->getCollection()->map(static function (ShopifyOrder $o) {
            $sc = $o->shopifyCustomer;
            $name = null;
            if ($sc !== null) {
                $name = trim(implode(' ', array_filter([
                    (string) ($sc->first_name ?? ''),
                    (string) ($sc->last_name ?? ''),
                ])));
                if ($name === '') {
                    $name = null;
                }
            }
            $email = $sc?->email;
            $customerDisplay = $name ?? $email;
            if ($customerDisplay === null || $customerDisplay === '') {
                $customerDisplay = $o->shopify_customer_id !== null
                    ? 'Shopify customer #'.$o->shopify_customer_id
                    : null;
            }

            $total = $o->total_price;
            $curr = $o->currency ?? '';
            $totalDisplay = $total !== null
                ? number_format((float) $total, 2).' '.trim((string) $curr)
                : '—';

            return [
                'id' => $o->id,
                'shopify_order_id' => $o->shopify_order_id,
                'shopify_customer_id' => $o->shopify_customer_id,
                'crm_customer_id' => $sc?->customer_id,
                'customer_display' => $customerDisplay,
                'total_display' => trim($totalDisplay) !== '' ? trim($totalDisplay) : '—',
                'order_status' => $o->order_status,
                'order_date' => $o->order_date?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function shopifyCustomersData(Request $request): JsonResponse
    {
        $perPage = max(5, min(100, (int) $request->input('per_page', 25)));

        $query = ShopifyCustomer::query()
            ->select([
                'id',
                'shopify_customer_id',
                'customer_id',
                'email',
                'first_name',
                'last_name',
                'phone',
                'sync_status',
                'last_synced_at',
            ])
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($q) use ($like, $term) {
                $q->where('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);
                if ($term !== '' && ctype_digit($term)) {
                    $q->orWhere('shopify_customer_id', (int) $term);
                }
            });
        }

        $paginator = $query->paginate($perPage);

        $rows = $paginator->getCollection()->map(static function (ShopifyCustomer $c) {
            $name = trim(implode(' ', array_filter([(string) ($c->first_name ?? ''), (string) ($c->last_name ?? '')])));

            return [
                'id' => $c->id,
                'shopify_customer_id' => $c->shopify_customer_id,
                'customer_id' => $c->customer_id,
                'name' => $name !== '' ? $name : null,
                'email' => $c->email,
                'phone' => $c->phone,
                'sync_status' => $c->sync_status,
                'last_synced_at' => $c->last_synced_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function syncCustomers(ShopifySyncRequest $request): JsonResponse|RedirectResponse
    {
        return $this->runSync(
            $request,
            fn () => $this->shopifyService->syncCustomers(),
            'customers'
        );
    }

    public function syncOrders(ShopifySyncRequest $request): JsonResponse|RedirectResponse
    {
        return $this->runSync(
            $request,
            fn () => $this->shopifyService->syncOrders(),
            'orders'
        );
    }

    public function syncProducts(ShopifySyncRequest $request): JsonResponse|RedirectResponse
    {
        return $this->runSync(
            $request,
            fn () => $this->shopifyService->syncProducts(),
            'products'
        );
    }

    protected function runSync(Request $request, \Closure $sync, string $label): JsonResponse|RedirectResponse
    {
        if (! $this->shopifyService->isConfigured()) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Shopify is not configured (check SHOPIFY_STORE_URL and SHOPIFY_ACCESS_TOKEN).',
                    'synced' => 0,
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['shopify' => 'Shopify is not configured.']);
        }

        try {
            $count = $sync();
        } catch (ShopifyApiException $e) {
            if ($request->expectsJson() || $request->wantsJson()) {
                $status = $e->getHttpStatus();

                return response()->json([
                    'message' => $e->getMessage(),
                    'synced' => 0,
                    'http_status' => $status > 0 ? $status : null,
                ], ($status >= 400 && $status < 600) ? $status : 502);
            }

            return redirect()
                ->back()
                ->withErrors(['shopify' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'resource' => $label,
                'synced' => $count,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', ucfirst($label)." sync completed ({$count} records processed).");
    }
}
