<?php

namespace App\Listeners\Crm;

use App\Events\Crm\ShopifyOrderImported;
use App\Models\Crm\Customer;
use App\Models\Crm\FollowUp;
use App\Models\Crm\ShopifyCustomer;
use App\Models\User;
use App\Services\Crm\Contracts\FollowUpServiceInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CreateFollowUpForNewShopifyOrder
{
    public function __construct(
        protected FollowUpServiceInterface $followUpService
    ) {}

    public function handle(ShopifyOrderImported $event): void
    {
        if (! $event->wasRecentlyCreated) {
            return;
        }

        $order = $event->order->fresh();

        if ($order === null || $order->order_status === 'cancelled') {
            return;
        }

        $customerId = $this->resolveCrmCustomerId($order);
        if ($customerId === null) {
            Log::info('CreateFollowUpForNewShopifyOrder: no CRM customer linked; skip', [
                'shopify_order_id' => $order->shopify_order_id,
            ]);

            return;
        }

        $crmCustomer = Customer::query()->find($customerId);
        if ($crmCustomer === null) {
            return;
        }

        if (FollowUp::query()->where('shopify_order_id', $order->shopify_order_id)->exists()) {
            return;
        }

        $assignee = $this->resolveAssigneeUser();
        if ($assignee === null) {
            Log::warning('CreateFollowUpForNewShopifyOrder: no assignee user configured; skip', [
                'shopify_order_id' => $order->shopify_order_id,
            ]);

            return;
        }

        try {
            $this->followUpService->createFollowUp([
                'customer_id' => $crmCustomer->id,
                'shopify_order_id' => $order->shopify_order_id,
                'company_id' => $crmCustomer->company_id,
                'assigned_user_id' => $assignee->id,
                'title' => 'New Shopify Order Follow-up',
                'description' => 'Customer placed new order',
                'follow_up_type' => FollowUp::TYPE_CALL,
                'priority' => FollowUp::PRIORITY_MEDIUM,
                'scheduled_at' => now()->addDay(),
            ], $assignee, [
                'source' => 'shopify_order_import',
                'shopify_order_local_id' => $order->id,
                'shopify_order_id' => $order->shopify_order_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CreateFollowUpForNewShopifyOrder: failed', [
                'shopify_order_id' => $order->shopify_order_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveCrmCustomerId(\App\Models\Crm\ShopifyOrder $order): ?int
    {
        if ($order->shopify_customer_id !== null) {
            $cid = ShopifyCustomer::query()
                ->where('shopify_customer_id', $order->shopify_customer_id)
                ->value('customer_id');
            if ($cid !== null) {
                return (int) $cid;
            }
        }

        $payload = $order->raw_payload;
        if (! is_array($payload)) {
            return null;
        }

        $email = $payload['contact_email'] ?? $payload['email'] ?? null;
        $customerBlock = $payload['customer'] ?? null;
        if ((! is_string($email) || $email === '') && is_array($customerBlock) && isset($customerBlock['email'])) {
            $email = $customerBlock['email'];
        }
        if (! is_string($email) || $email === '') {
            return null;
        }

        $emailLower = mb_strtolower($email);

        $found = Customer::query()
            ->whereRaw('LOWER(email) = ?', [$emailLower])
            ->value('id');

        if ($found !== null) {
            return (int) $found;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (! (bool) Config::get('services.crm.shopify_auto_create_customers', true)) {
            return null;
        }

        $crmCustomer = Customer::query()->create([
            'company_id' => null,
            'name' => $this->guestCustomerNameFromOrderPayload($payload, $email),
            'email' => $email,
            'phone' => $this->guestPhoneFromOrderPayload($payload),
        ]);

        return (int) $crmCustomer->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function guestCustomerNameFromOrderPayload(array $payload, string $email): string
    {
        foreach (['shipping_address', 'billing_address'] as $key) {
            $addr = $payload[$key] ?? null;
            if (! is_array($addr)) {
                continue;
            }
            $name = trim(implode(' ', array_filter([
                isset($addr['first_name']) ? trim((string) $addr['first_name']) : '',
                isset($addr['last_name']) ? trim((string) $addr['last_name']) : '',
            ])));
            if ($name !== '') {
                return $name;
            }
            if (! empty($addr['name'])) {
                return trim((string) $addr['name']);
            }
        }

        $customer = $payload['customer'] ?? null;
        if (is_array($customer)) {
            $name = trim(implode(' ', array_filter([
                isset($customer['first_name']) ? trim((string) $customer['first_name']) : '',
                isset($customer['last_name']) ? trim((string) $customer['last_name']) : '',
            ])));
            if ($name !== '') {
                return $name;
            }
        }

        return $email;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function guestPhoneFromOrderPayload(array $payload): ?string
    {
        foreach (['shipping_address', 'billing_address'] as $key) {
            $addr = $payload[$key] ?? null;
            if (is_array($addr) && ! empty($addr['phone'])) {
                return (string) $addr['phone'];
            }
        }

        $customer = $payload['customer'] ?? null;
        if (is_array($customer) && ! empty($customer['phone'])) {
            return (string) $customer['phone'];
        }

        return null;
    }

    protected function resolveAssigneeUser(): ?User
    {
        $id = Config::get('services.crm.shopify_new_order_followup_assignee_id');
        if ($id !== null && $id !== '') {
            $user = User::query()->whereKey((int) $id)->first();
            if ($user !== null) {
                return $user;
            }
        }

        return User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
