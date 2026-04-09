<?php

namespace App\Services\Crm\Contracts;

use App\Models\Crm\Customer;
use App\Models\Crm\ShopifyCustomer;

interface ShopifyServiceInterface
{
    public function isConfigured(): bool;

    /**
     * Fetch a page of Shopify customers from Admin REST API.
     *
     * @return array{customers: array<int, array<string, mixed>>, next_page_info: string|null, has_next_page: bool}
     */
    public function getCustomers(?int $since_id = null): array;

    /**
     * Fetch a page of Shopify orders from Admin REST API.
     *
     * @return array{
     *     orders: array<int, array{
     *         shopify_order_id: int,
     *         shopify_customer_id: int|null,
     *         total_price: mixed,
     *         currency: mixed,
     *         order_status: string|null,
     *         order_date: string|null,
     *         raw_payload: array<string, mixed>
     *     }>,
     *     next_page_info: string|null,
     *     has_next_page: bool
     * }
     */
    public function getOrders(?int $since_id = null): array;

    /**
     * Pull customers from Shopify Admin REST API into shopify_customers.
     */
    public function syncCustomers(int $pageLimit = 250): int;

    /**
     * Pull orders (status=any) into shopify_orders.
     */
    public function syncOrders(int $pageLimit = 250): int;

    /**
     * Pull products into shopify_products.
     */
    public function syncProducts(int $pageLimit = 250): int;

    /**
     * Ensure the Shopify customer row is linked to a CRM customer (match existing email, or auto-create if allowed).
     *
     * @throws \InvalidArgumentException When no CRM customer can be resolved
     */
    public function ensureCrmCustomerForShopifyRecord(ShopifyCustomer $shopifyCustomer): Customer;
}
