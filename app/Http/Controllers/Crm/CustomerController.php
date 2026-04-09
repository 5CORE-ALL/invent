<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Customer;
use App\Models\Crm\FollowUp;
use App\Models\Crm\ShopifyOrder;
use App\Services\Crm\Contracts\FollowUpServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected FollowUpServiceInterface $followUpService
    ) {}

    public function show(Request $request, Customer $customer): View
    {
        $customer->loadMissing(['company:id,name']);

        return view('crm.customers.show', [
            'customer' => $customer,
        ]);
    }

    public function tab(Request $request, Customer $customer, string $tab): View
    {
        $allowed = ['overview', 'follow-ups', 'communications', 'shopify-data', 'orders'];
        abort_unless(in_array($tab, $allowed, true), 404);

        $customer->loadMissing(['company:id,name']);

        return match ($tab) {
            'overview' => view('crm.customers.partials.overview', $this->overviewContext($customer)),
            'follow-ups' => view('crm.customers.partials.follow-ups', $this->followUpsContext($request, $customer)),
            'communications' => view('crm.customers.partials.communications', $this->communicationsContext($customer)),
            'shopify-data' => view('crm.customers.partials.shopify-data', $this->shopifyDataContext($customer)),
            'orders' => view('crm.customers.partials.orders', $this->ordersContext($request, $customer)),
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function overviewContext(Customer $customer): array
    {
        $customer->loadCount([
            'followUps' => fn ($q) => $q->where('status', FollowUp::STATUS_PENDING),
            'communicationLogs',
            'shopifyCustomers',
        ]);

        $followUpsTotal = $customer->followUps()->count();

        return [
            'customer' => $customer,
            'followUpsTotal' => $followUpsTotal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function followUpsContext(Request $request, Customer $customer): array
    {
        $followUps = FollowUp::query()
            ->where('customer_id', $customer->id)
            ->select([
                'id',
                'customer_id',
                'assigned_user_id',
                'title',
                'follow_up_type',
                'priority',
                'status',
                'scheduled_at',
                'updated_at',
            ])
            ->with(['assignedUser:id,name'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'customer' => $customer,
            'followUps' => $followUps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function communicationsContext(Customer $customer): array
    {
        $timeline = $this->followUpService->getCustomerTimeline($customer, 100)->values();

        return [
            'customer' => $customer,
            'timeline' => $timeline,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopifyDataContext(Customer $customer): array
    {
        $shopifyCustomers = $customer->shopifyCustomers()
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
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return [
            'customer' => $customer,
            'shopifyCustomers' => $shopifyCustomers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ordersContext(Request $request, Customer $customer): array
    {
        $shopifyIds = $customer->shopifyCustomers()->pluck('shopify_customer_id')->filter()->unique()->values();

        $orders = ShopifyOrder::query()
            ->select([
                'id',
                'shopify_order_id',
                'shopify_customer_id',
                'total_price',
                'currency',
                'order_status',
                'order_date',
            ])
            ->whereIn('shopify_customer_id', $shopifyIds)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'customer' => $customer,
            'orders' => $orders,
        ];
    }
}
