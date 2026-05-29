<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ImportShopifyCustomersRequest;
use App\Http\Requests\Crm\ShopifySyncRequest;
use App\Http\Requests\Crm\StoreShopifyCustomerRequest;
use App\Http\Requests\Crm\StoreShopifyCustomerFollowUpRequest;
use App\Http\Resources\Crm\FollowUpResource;
use App\Models\Crm\Customer;
use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use App\Models\User;
use App\Services\Crm\Contracts\FollowUpServiceInterface;
use App\Services\Crm\Contracts\ShopifyServiceInterface;
use App\Services\Crm\Exceptions\ShopifyApiException;
use App\Services\Crm\ShopifyCustomerClassifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShopifyController extends Controller
{
    public function __construct(
        protected ShopifyServiceInterface $shopifyService,
        protected FollowUpServiceInterface $followUpService,
        protected ShopifyCustomerClassifier $customerClassifier
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
            'tagFilters' => $this->customerClassifier->classificationTagsForTypes(['wholesale', 'dropshipper']),
            'marketplaceChannels' => $this->customerClassifier->marketplaceChannelOptions(),
        ]);
    }

    public function shopifyCustomerTags(Request $request): JsonResponse
    {
        $type = trim((string) $request->input('customer_type', ''));

        $allowedB2bTypes = ['wholesale', 'dropshipper'];
        $types = $type !== '' && in_array($type, $allowedB2bTypes, true)
            ? [$type]
            : $allowedB2bTypes;

        return response()->json($this->customerClassifier->classificationTagsForTypes($types));
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

    public function storeShopifyCustomer(StoreShopifyCustomerRequest $request): JsonResponse
    {
        try {
            $result = $this->syncCustomerPayloadToShopify($request->validated());
        } catch (ShopifyApiException $e) {
            $status = $e->getHttpStatus();

            return response()->json([
                'message' => $e->getMessage(),
                'http_status' => $status > 0 ? $status : null,
            ], ($status >= 400 && $status < 600) ? $status : 502);
        }

        return response()->json([
            'message' => $result['action'] === 'updated'
                ? 'Customer updated in Shopify.'
                : 'Customer created in Shopify.',
            'action' => $result['action'],
            'customer' => $this->shopifyCustomerResponseRow($result['shopify_customer']),
        ], $result['action'] === 'updated' ? 200 : 201);
    }

    public function importShopifyCustomers(ImportShopifyCustomersRequest $request): JsonResponse
    {
        $rows = $this->readCustomerImportRows($request->file('file')->getRealPath());
        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, [
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
                'phone' => ['nullable', 'string', 'max:64', 'required_without:email'],
                'province' => ['nullable', 'string', 'max:128'],
                'zip' => ['nullable', 'string', 'max:32'],
                'tags' => ['nullable', 'string', 'max:1000'],
            ]);

            if ($validator->fails()) {
                $summary['skipped']++;
                $summary['errors'][] = 'Row '.($index + 2).': '.$validator->errors()->first();
                continue;
            }

            try {
                $result = $this->syncCustomerPayloadToShopify($validator->validated());
                $summary[$result['action'] === 'updated' ? 'updated' : 'created']++;
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = 'Row '.($index + 2).': '.$e->getMessage();
            }
        }

        $summary['errors'] = array_slice($summary['errors'], 0, 20);

        return response()->json([
            'message' => sprintf(
                'Import finished: %d created, %d updated, %d skipped, %d failed.',
                $summary['created'],
                $summary['updated'],
                $summary['skipped'],
                $summary['failed']
            ),
            'summary' => $summary,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{action: string, customer: Customer, shopify_customer: ShopifyCustomer}
     */
    protected function syncCustomerPayloadToShopify(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $email = isset($data['email']) ? trim((string) $data['email']) : '';
            $shopifyCustomer = $email !== ''
                ? ShopifyCustomer::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first()
                : null;

            $customer = null;
            if ($shopifyCustomer?->customer_id !== null) {
                $customer = Customer::query()->find($shopifyCustomer->customer_id);
            }
            if ($customer === null && $email !== '') {
                $customer = Customer::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
            }
            if ($customer === null) {
                $customer = Customer::query()->create([
                    'company_id' => null,
                    'name' => $this->customerNameFromImportData($data),
                    'email' => $email !== '' ? $email : null,
                    'phone' => $this->nullableString($data['phone'] ?? null),
                ]);
            }

            $payload = array_merge($data, ['customer_id' => $customer->id]);
            if ($shopifyCustomer !== null) {
                $shopifyCustomer = $this->shopifyService->updateShopifyCustomerFromCrm($shopifyCustomer, $payload);
                $action = 'updated';
            } else {
                $shopifyCustomer = $this->shopifyService->createCustomerFromCrm($payload);
                $action = 'created';
            }

            $this->syncCrmCustomerFromShopifyRecord($customer, $shopifyCustomer);

            return [
                'action' => $action,
                'customer' => $customer->refresh(),
                'shopify_customer' => $shopifyCustomer->refresh(),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readCustomerImportRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);
        if ($rawRows === []) {
            return [];
        }

        $headerRow = array_shift($rawRows);
        $headers = [];
        foreach ($headerRow as $column => $heading) {
            $headers[$column] = $this->normalizeImportHeading((string) $heading);
        }

        $rows = [];
        foreach ($rawRows as $rawRow) {
            $row = [
                'name' => null,
                'email' => null,
                'phone' => null,
                'province' => null,
                'zip' => null,
                'tags' => null,
            ];
            foreach ($rawRow as $column => $value) {
                $field = $headers[$column] ?? null;
                if ($field !== null && array_key_exists($field, $row)) {
                    $row[$field] = is_scalar($value) ? trim((string) $value) : null;
                }
            }

            if (count(array_filter($row, static fn ($value) => $value !== null && $value !== '')) > 0) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function normalizeImportHeading(string $heading): ?string
    {
        $key = mb_strtolower(trim($heading));
        $key = preg_replace('/[^a-z0-9]+/i', '_', $key) ?? $key;
        $key = trim($key, '_');

        return match ($key) {
            'name', 'customer_name', 'full_name' => 'name',
            'email', 'email_address' => 'email',
            'phone', 'phone_number', 'mobile' => 'phone',
            'province', 'state', 'province_state' => 'province',
            'zip', 'zipcode', 'zip_code', 'postal_code' => 'zip',
            'tag', 'tags' => 'tags',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function customerNameFromImportData(array $data): string
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '') {
            return explode('@', $email)[0] ?: 'Shopify customer';
        }

        return 'Shopify customer';
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function syncCrmCustomerFromShopifyRecord(Customer $customer, ShopifyCustomer $shopifyCustomer): void
    {
        $name = trim(implode(' ', array_filter([
            (string) ($shopifyCustomer->first_name ?? ''),
            (string) ($shopifyCustomer->last_name ?? ''),
        ])));

        $customer->forceFill([
            'name' => $name !== '' ? $name : $customer->name,
            'email' => $shopifyCustomer->email ?: $customer->email,
            'phone' => $shopifyCustomer->phone ?: $customer->phone,
        ])->save();
    }

    protected function shopifyCustomerResponseRow(ShopifyCustomer $customer): array
    {
        $payload = is_array($customer->raw_payload) ? $customer->raw_payload : [];
        $defaultAddress = isset($payload['default_address']) && is_array($payload['default_address'])
            ? $payload['default_address']
            : [];

        return [
            'id' => $customer->id,
            'shopify_customer_id' => $customer->shopify_customer_id,
            'customer_id' => $customer->customer_id,
            'name' => trim(implode(' ', array_filter([
                (string) ($customer->first_name ?? ''),
                (string) ($customer->last_name ?? ''),
            ]))) ?: null,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'province' => $defaultAddress['province'] ?? null,
            'zip' => $defaultAddress['zip'] ?? null,
            'tags' => $this->tagsFromPayload($payload),
            'channel' => $this->customerClassifier->channelLabel($customer->marketplace_channel),
            'channel_source' => $customer->classification_reason,
            'customer_type' => $customer->customer_type,
            'marketplace_channel' => $customer->marketplace_channel,
            'marketplace_channel_label' => $this->customerClassifier->channelLabel($customer->marketplace_channel),
            'classification_source' => $customer->classification_source,
            'classification_reason' => $customer->classification_reason,
            'sync_status' => $customer->sync_status,
            'last_synced_at' => $customer->last_synced_at?->toIso8601String(),
        ];
    }

    public function shopifyOthersIndex(): View
    {
        $assignees = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        if ($assignees->isEmpty()) {
            $assignees = User::query()
                ->whereKey(optional(auth()->user())->id)
                ->get(['id', 'name', 'email']);
        }

        return view('crm.shopify.others', [
            'crmAssignees' => $assignees,
            'tagFilters' => $this->customerTagFilters(['marketplace']),
            'marketplaceChannels' => $this->customerClassifier->marketplaceChannelOptions(),
        ]);
    }

    public function shopifyOthersData(Request $request): JsonResponse
    {
        return $this->shopifyCustomerDataResponse($request, ['marketplace']);
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
        return $this->shopifyCustomerDataResponse($request, ['wholesale', 'dropshipper']);
    }

    /**
     * @param  array<int, string>|null  $defaultTypes
     */
    protected function shopifyCustomerDataResponse(Request $request, ?array $defaultTypes = null): JsonResponse
    {
        $perPage = max(5, min(100, (int) $request->input('per_page', 25)));
        $sortBy = $this->normalizeCustomerSortBy($request->input('sort_by'));
        $sortDir = $this->normalizeSortDirection($request->input('sort_dir'));
        $tag = trim((string) $request->input('tag', ''));
        $customerType = trim((string) $request->input('customer_type', ''));
        $marketplaceChannel = trim((string) $request->input('marketplace_channel', ''));
        $classificationSource = trim((string) $request->input('classification_source', ''));
        $syncStatus = trim((string) $request->input('sync_status', ''));

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
                'raw_payload',
                'customer_type',
                'marketplace_channel',
                'classification_source',
                'classification_reason',
                'classification_overridden',
                'classified_at',
                'last_synced_at',
            ]);

        $this->applyCustomerClassificationFilters(
            $query,
            $customerType !== '' ? [$customerType] : $defaultTypes,
            $marketplaceChannel,
            $classificationSource,
            $syncStatus
        );

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

        if ($tag !== '') {
            $tagLike = '%'.addcslashes(mb_strtolower($tag), '%_\\').'%';
            $query->whereRaw(
                "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(shopify_customers.raw_payload, '$.\"tags\"')), '')) LIKE ?",
                [$tagLike]
            );
        }

        $this->applyCustomerSort($query, $sortBy, $sortDir);

        $paginator = $query->paginate($perPage);
        $rows = $paginator->getCollection()
            ->map(fn (ShopifyCustomer $c) => $this->shopifyCustomerResponseRow($c))
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'tag' => $tag,
                'customer_type' => $customerType,
                'marketplace_channel' => $marketplaceChannel,
                'classification_source' => $classificationSource,
                'sync_status' => $syncStatus,
                'summary' => $this->customerClassificationSummary($defaultTypes),
                'filtered_stats' => $this->filteredCustomerStats($query),
            ],
        ]);
    }

    /**
     * @param  array<int, string>|null  $types
     */
    protected function applyCustomerClassificationFilters($query, ?array $types, string $marketplaceChannel, string $classificationSource, string $syncStatus): void
    {
        if ($types !== null && $types !== []) {
            $query->where(function ($q) use ($types) {
                $q->whereIn('customer_type', $types);
                if (in_array('unknown', $types, true)) {
                    $q->orWhereNull('customer_type');
                }
            });
        }

        if ($marketplaceChannel !== '') {
            $query->where('marketplace_channel', $marketplaceChannel);
        }

        if ($classificationSource !== '') {
            $query->where('classification_source', $classificationSource);
        }

        if ($syncStatus !== '') {
            $query->where('sync_status', $syncStatus);
        }
    }

    /**
     * @param  array<int, string>|null  $types
     */
    protected function customerClassificationSummary(?array $types = null): array
    {
        $query = ShopifyCustomer::query();
        if ($types !== null && $types !== []) {
            $query->where(function ($q) use ($types) {
                $q->whereIn('customer_type', $types);
                if (in_array('unknown', $types, true)) {
                    $q->orWhereNull('customer_type');
                }
            });
        }

        $counts = $query
            ->selectRaw("COALESCE(customer_type, 'unknown') as type, COUNT(*) as total")
            ->groupByRaw("COALESCE(customer_type, 'unknown')")
            ->pluck('total', 'type')
            ->all();

        return [
            'all' => array_sum(array_map('intval', $counts)),
            'direct' => (int) ($counts['direct'] ?? 0),
            'marketplace' => (int) ($counts['marketplace'] ?? 0),
            'wholesale' => (int) ($counts['wholesale'] ?? 0),
            'dropshipper' => (int) ($counts['dropshipper'] ?? 0),
            'unknown' => (int) ($counts['unknown'] ?? 0),
        ];
    }

    protected function filteredCustomerStats($baseQuery): array
    {
        // MySQL doesn't allow LIMIT/ORDER in an IN() subquery, so strip them from the clone.
        $idSubquery = clone $baseQuery;
        $idSubquery->getQuery()->limit  = null;
        $idSubquery->getQuery()->offset = null;
        $idSubquery->getQuery()->orders = null;
        $idSubquery->select('shopify_customers.id');

        // Order stats — join filtered customers with their Shopify orders
        $orderStats = ShopifyCustomer::query()
            ->from('shopify_customers')
            ->whereIn('shopify_customers.id', $idSubquery)
            ->join('shopify_orders as so', 'shopify_customers.shopify_customer_id', '=', 'so.shopify_customer_id')
            ->selectRaw(
                'COUNT(so.id) as total_orders,'.
                'SUM(COALESCE(so.total_price, 0)) as total_order_value,'.
                'COUNT(DISTINCT shopify_customers.id) as customers_with_orders'
            )
            ->first();

        $totalOrderValue     = (float) ($orderStats->total_order_value ?? 0);
        $customersWithOrders = (int)   ($orderStats->customers_with_orders ?? 0);
        $totalOrders         = (int)   ($orderStats->total_orders ?? 0);
        $avgOrderValue       = $totalOrders > 0 ? round($totalOrderValue / $totalOrders, 2) : 0;

        // Additional customer-side counts (no join needed)
        $linkedToCrm  = ShopifyCustomer::whereIn('id', $idSubquery)->whereNotNull('customer_id')->count();
        $missingEmail = ShopifyCustomer::whereIn('id', $idSubquery)
            ->where(function ($q) { $q->whereNull('email')->orWhere('email', ''); })
            ->count();

        return [
            'total_orders'          => $totalOrders,
            'total_order_value'     => round($totalOrderValue, 2),
            'avg_order_value'       => $avgOrderValue,
            'customers_with_orders' => $customersWithOrders,
            'linked_to_crm'         => $linkedToCrm,
            'missing_email'         => $missingEmail,
        ];
    }

    /**
     * @param  array<int, string>|null  $types
     */
    protected function customerTagFilters(?array $types = null): array
    {
        $query = ShopifyCustomer::query()
            ->whereNotNull('raw_payload');

        if ($types !== null && $types !== []) {
            $query->where(function ($q) use ($types) {
                $q->whereIn('customer_type', $types);
                if (in_array('unknown', $types, true)) {
                    $q->orWhereNull('customer_type');
                }
            });
        }

        $tagStrings = $query
            ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.\"tags\"')) as tags")
            ->pluck('tags');

        $tags = [];
        foreach ($tagStrings as $rawTags) {
            if (! is_string($rawTags) || trim($rawTags) === '') {
                continue;
            }

            foreach (explode(',', $rawTags) as $tag) {
                $tag = trim($tag);
                if (in_array(mb_strtolower($tag), array_map('mb_strtolower', $this->excludedCustomerTags()), true)) {
                    continue;
                }
                if ($tag !== '') {
                    $tags[mb_strtolower($tag)] = $tag;
                }
            }
        }

        natcasesort($tags);

        return array_values($tags);
    }

    protected function applyBaseCustomerVisibility($query, array $marketplaceDomains): void
    {
        $query->where(function ($visibility) use ($marketplaceDomains) {
            $visibility->whereRaw(
                $this->customerTagsLowerExpression().' LIKE ?',
                ['%wholesale%']
            )->orWhere(function ($q) use ($marketplaceDomains) {
                $q->where(function ($emailQuery) use ($marketplaceDomains) {
                    foreach ($marketplaceDomains as $domain) {
                        $emailQuery->where('email', 'not like', '%@'.$domain);
                    }
                })
                    ->whereDoesntHave('orders', function ($orderQuery) {
                        $this->applyMarketplaceChannelFilter($orderQuery);
                    });

                $this->applyExcludedCustomerTagsFilter($q);
            });
        });
    }

    protected function applyExcludedCustomerTagsFilter($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('raw_payload')
                ->orWhere(function ($tagQuery) {
                    foreach ($this->excludedCustomerTags() as $tag) {
                        $tagQuery->whereRaw(
                            $this->customerTagsLowerExpression().' NOT LIKE ?',
                            ['%'.addcslashes(mb_strtolower($tag), '%_\\').'%']
                        );
                    }
                });

        });
    }

    protected function excludedCustomerTags(): array
    {
        return [
            'iphone',
            'android',
            'VITALS',
            'FB / Offer Up Seller',
            'Login with Shop',
        ];
    }

    protected function customerTagsLowerExpression(): string
    {
        return "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(shopify_customers.raw_payload, '$.\"tags\"')), ''))";
    }

    protected function normalizeCustomerSortBy(mixed $value): string
    {
        $sortBy = mb_strtolower(trim((string) $value));
        $allowed = [
            'shopify_customer_id',
            'name',
            'email',
            'phone',
            'province',
            'zip',
            'channel',
            'customer_type',
            'marketplace_channel',
            'classification_source',
            'tags',
            'sync_status',
            'last_synced_at',
        ];

        return in_array($sortBy, $allowed, true) ? $sortBy : 'last_synced_at';
    }

    protected function normalizeSortDirection(mixed $value): string
    {
        return mb_strtolower(trim((string) $value)) === 'asc' ? 'asc' : 'desc';
    }

    protected function applyCustomerSort($query, string $sortBy, string $sortDir): void
    {
        $dir = $sortDir === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            'shopify_customer_id' => $query->orderBy('shopify_customers.shopify_customer_id', $dir),
            'name' => $query
                ->orderByRaw("LOWER(COALESCE(shopify_customers.first_name, '')) {$dir}")
                ->orderByRaw("LOWER(COALESCE(shopify_customers.last_name, '')) {$dir}"),
            'email' => $query->orderByRaw("LOWER(COALESCE(shopify_customers.email, '')) {$dir}"),
            'phone' => $query->orderByRaw("LOWER(COALESCE(shopify_customers.phone, '')) {$dir}"),
            'province' => $query->orderByRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(shopify_customers.raw_payload, '$.\"default_address\".\"province\"')), '')) {$dir}"),
            'zip' => $query->orderByRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(shopify_customers.raw_payload, '$.\"default_address\".\"zip\"')), '')) {$dir}"),
            'channel', 'marketplace_channel' => $query->orderByRaw("LOWER(COALESCE(shopify_customers.marketplace_channel, '')) {$dir}"),
            'customer_type' => $query->orderByRaw("LOWER(COALESCE(shopify_customers.customer_type, '')) {$dir}"),
            'classification_source' => $query->orderByRaw("LOWER(COALESCE(shopify_customers.classification_source, '')) {$dir}"),
            'tags' => $query->orderByRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(shopify_customers.raw_payload, '$.\"tags\"')), '')) {$dir}"),
            'sync_status' => $query->orderByRaw("LOWER(COALESCE(shopify_customers.sync_status, '')) {$dir}"),
            default => $query->orderBy('shopify_customers.last_synced_at', $dir),
        };

        $query->orderByDesc('shopify_customers.id');
    }

    protected function latestOrderChannelSortExpression(): string
    {
        return "LOWER(COALESCE((
            SELECT COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(so.raw_payload, '$.\"source_name\"')),
                JSON_UNQUOTE(JSON_EXTRACT(so.raw_payload, '$.\"tags\"')),
                ''
            )
            FROM shopify_orders so
            WHERE so.shopify_customer_id = shopify_customers.shopify_customer_id
            ORDER BY so.order_date DESC, so.id DESC
            LIMIT 1
        ), ''))";
    }

    protected function customerChannelSearchTerms(): array
    {
        $terms = [];
        foreach ($this->marketplaceChannelDefinitions() as $channel) {
            $terms[$channel['value']] = $channel['terms'];
        }

        return $terms;
    }

    protected function marketplaceChannelDefinitions(): array
    {
        return [
            ['value' => 'vinted-com', 'label' => 'Vinted.com', 'terms' => ['vinted']],
            ['value' => 'newegg', 'label' => 'Newegg', 'terms' => ['newegg']],
            ['value' => 'pls', 'label' => 'PLS', 'terms' => ['pls', 'prolightsounds']],
            ['value' => 'tiendamia', 'label' => 'Tiendamia', 'terms' => ['tiendamia']],
            ['value' => 'business-5core', 'label' => 'Business 5Core', 'terms' => ['business 5core', 'business5core', 'b5c']],
            ['value' => 'mercari-wo-ship', 'label' => 'Mercari wo ship', 'terms' => ['mercari wo ship', 'mercari without ship']],
            ['value' => 'fb-marketplace', 'label' => 'FB Marketplace', 'terms' => ['fb marketplace', 'facebook marketplace']],
            ['value' => 'instagram-shop', 'label' => 'Instagram Shop', 'terms' => ['instagram shop', 'instagram']],
            ['value' => 'depop', 'label' => 'Depop', 'terms' => ['depop']],
            ['value' => 'mercari-w-ship', 'label' => 'Mercari w ship', 'terms' => ['mercari w ship', 'mercari with ship', 'mercari']],
            ['value' => 'topdawg', 'label' => 'TopDawg', 'terms' => ['topdawg', 'topdwag']],
            ['value' => 'tiktok-2', 'label' => 'TikTok 2', 'terms' => ['tiktok 2', 'tik tok 2']],
            ['value' => 'aliexpress', 'label' => 'Aliexpress', 'terms' => ['aliexpress', 'ali express']],
            ['value' => 'shopify-b2b', 'label' => 'Shopify B2B', 'terms' => ['shopify b2b']],
            ['value' => 'faire', 'label' => 'Faire', 'terms' => ['faire']],
            ['value' => 'ebaytwo', 'label' => 'EbayTwo', 'terms' => ['ebaytwo', 'ebay 2', 'ebay2']],
            ['value' => 'reverb', 'label' => 'Reverb', 'terms' => ['reverb']],
            ['value' => 'shein', 'label' => 'Shein', 'terms' => ['shein']],
            ['value' => 'ebaythree', 'label' => 'EbayThree', 'terms' => ['ebaythree', 'ebay 3', 'ebay3']],
            ['value' => 'wayfair', 'label' => 'Wayfair', 'terms' => ['wayfair']],
            ['value' => 'tiktok-shop', 'label' => 'Tiktok Shop', 'terms' => ['tiktok shop', 'tik tok shop', 'tiktok', 'tik tok']],
            ['value' => 'macys', 'label' => 'Macys', 'terms' => ['macys', "macy's", 'macy']],
            ['value' => 'shopify-b2c', 'label' => 'Shopify B2C', 'terms' => ['shopify b2c']],
            ['value' => 'temu-2', 'label' => 'Temu 2', 'terms' => ['temu 2', 'temu2']],
            ['value' => 'purchasing-power', 'label' => 'Purchasing Power', 'terms' => ['purchasing power']],
            ['value' => 'doba', 'label' => 'Doba', 'terms' => ['doba']],
            ['value' => 'bestbuy-usa', 'label' => 'BestBuy USA', 'terms' => ['bestbuy', 'best buy']],
            ['value' => 'ebay', 'label' => 'eBay', 'terms' => ['ebay']],
            ['value' => 'temu', 'label' => 'Temu', 'terms' => ['temu']],
            ['value' => 'amazon', 'label' => 'Amazon', 'terms' => ['amazon']],
        ];
    }

    protected function applyMarketplaceChannelFilter($query): void
    {
        $terms = array_values(array_unique(array_merge(...array_values($this->customerChannelSearchTerms()))));
        if ($terms === []) {
            return;
        }

        $fields = [
            'source_name',
            'tags',
            'source_identifier',
            'source_url',
            'landing_site',
            'referring_site',
        ];

        $query->where(function ($q) use ($fields, $terms) {
            foreach ($fields as $field) {
                foreach ($terms as $term) {
                    $q->orWhereRaw(
                        "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(shopify_orders.raw_payload, '$.\"{$field}\"')), '')) LIKE ?",
                        ['%'.mb_strtolower($term).'%']
                    );
                }
            }
        });
    }

    protected function tagsFromPayload(array $payload): array
    {
        $rawTags = $payload['tags'] ?? null;
        if (is_string($rawTags) && $rawTags !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $rawTags))));
        }

        if (is_array($rawTags)) {
            return array_values(array_filter(array_map('trim', $rawTags)));
        }

        return [];
    }

    protected function channelDetailsFromOrderPayload(array $payload): ?array
    {
        $parts = [];
        foreach (['source_name', 'tags', 'source_identifier', 'source_url', 'landing_site', 'referring_site'] as $field) {
            $value = $payload[$field] ?? null;
            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(static fn ($item) => is_scalar($item) ? (string) $item : '', $value)));
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $parts[$field] = trim((string) $value);
            }
        }

        if ($parts === []) {
            return null;
        }

        $haystack = mb_strtolower(implode(' ', $parts));
        foreach ($this->customerChannelSearchTerms() as $channel => $terms) {
            foreach ($terms as $term) {
                if (str_contains($haystack, mb_strtolower($term))) {
                    return [
                        'value' => $channel,
                        'label' => $this->customerChannelLabel($channel),
                        'source' => $parts['source_name'] ?? $parts['tags'] ?? null,
                    ];
                }
            }
        }

        $source = $parts['source_name'] ?? $parts['tags'] ?? reset($parts);

        return [
            'value' => 'other',
            'label' => ucwords(str_replace(['_', '-'], ' ', mb_strtolower((string) $source))),
            'source' => $source,
        ];
    }

    protected function customerChannelLabel(string $channel): string
    {
        foreach ($this->marketplaceChannelDefinitions() as $definition) {
            if ($definition['value'] === $channel) {
                return $definition['label'];
            }
        }

        return ucwords(str_replace(['_', '-'], ' ', $channel));
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
