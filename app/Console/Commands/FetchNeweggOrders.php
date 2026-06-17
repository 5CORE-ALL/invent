<?php

namespace App\Console\Commands;

use App\Models\NeweggOrder;
use App\Models\NeweggOrderItem;
use App\Services\NeweggApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchNeweggOrders extends Command
{
    /**
     * Fetch raw orders from Newegg. Run on a server reachable to api.newegg.com.
     *
     *   php artisan newegg:orders
     *   php artisan newegg:orders --days=30 --status=0
     *   php artisan newegg:orders --from="2026-05-01 00:00:00" --to="2026-06-01 00:00:00"
     *   php artisan newegg:orders --days=90 --save        (persist to DB, all pages)
     */
    protected $signature = 'newegg:orders
        {--status= : 0 Unshipped, 1 Partially Shipped, 2 Shipped, 3 Invoiced, 4 Voided, 5 Payment Pending. Blank = all}
        {--days=30 : Look back this many days (ignored if --from/--to given)}
        {--from= : Order date from (PST), e.g. "2026-05-01 00:00:00"}
        {--to= : Order date to (PST), e.g. "2026-06-01 00:00:00"}
        {--page=1 : Page index}
        {--size=100 : Page size (max 100)}
        {--apiversion=315 : Newegg API version}
        {--order= : A specific Newegg order number (ignores other criteria)}
        {--save : Persist fetched orders into newegg_orders / newegg_order_items (fetches all pages)}
        {--raw : Print full raw JSON body}';

    protected $description = 'Fetch raw orders from the Newegg Marketplace API (optionally persist to DB)';

    public function handle(NeweggApiService $newegg): int
    {
        $criteria = $this->buildCriteria();

        $this->info('Fetching Newegg orders...');
        $this->line('  SellerID: ' . (config('services.newegg.seller_id') ?: '(not set)'));
        $this->line('  Criteria: ' . json_encode($criteria, JSON_UNESCAPED_SLASHES));
        $this->newLine();

        $pageIndex = (int) $this->option('page');
        $pageSize  = (int) $this->option('size');
        $version   = (string) $this->option('apiversion');

        $result = $newegg->getOrders($criteria, $pageIndex, $pageSize, $version);
        $this->line('HTTP status: ' . $result['status']);

        $json = $this->guardResponse($result);
        if ($json === null) {
            return self::FAILURE;
        }

        if ($this->option('raw') && !$this->option('save')) {
            $this->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $pageInfo  = data_get($json, 'ResponseBody.PageInfo', []);
        $totalPage = (int) data_get($pageInfo, 'TotalPageCount', 1);
        $orders    = data_get($json, 'ResponseBody.OrderInfoList', []) ?: [];

        $this->info('IsSuccess: ' . json_encode(data_get($json, 'IsSuccess')));
        $this->line('  TotalCount:     ' . data_get($pageInfo, 'TotalCount', 0));
        $this->line('  TotalPageCount: ' . $totalPage);
        $this->newLine();

        if (!$this->option('save')) {
            $this->renderTable($orders);
            $this->comment('Use --raw for full JSON, or --save to store into the database.');
            return self::SUCCESS;
        }

        // --save : walk every page and persist.
        $savedOrders = 0;
        $savedItems  = 0;

        $this->persistPage($orders, $savedOrders, $savedItems);

        for ($p = $pageIndex + 1; $p <= $totalPage; $p++) {
            $this->line("  Fetching page {$p}/{$totalPage}...");
            $pageResult = $newegg->getOrders($criteria, $p, $pageSize, $version);
            $pageJson   = $this->guardResponse($pageResult);
            if ($pageJson === null) {
                $this->warn("  Stopping: page {$p} failed.");
                break;
            }
            $this->persistPage(data_get($pageJson, 'ResponseBody.OrderInfoList', []) ?: [], $savedOrders, $savedItems);
        }

        $this->newLine();
        $this->info("Saved/updated {$savedOrders} orders and {$savedItems} line items.");

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function buildCriteria(): array
    {
        $criteria = [];

        if ($this->option('order')) {
            $criteria['OrderNumberList'] = ['OrderNumber' => [(string) $this->option('order')]];
            return $criteria;
        }

        if ($this->option('status') !== null && $this->option('status') !== '') {
            $criteria['Status'] = (int) $this->option('status');
        }

        if ($this->option('from') || $this->option('to')) {
            if ($this->option('from')) {
                $criteria['OrderDateFrom'] = (string) $this->option('from');
            }
            if ($this->option('to')) {
                $criteria['OrderDateTo'] = (string) $this->option('to');
            }
        } else {
            $days = max((int) $this->option('days'), 1);
            $tz   = 'America/Los_Angeles';
            $criteria['OrderDateFrom'] = now($tz)->subDays($days)->format('Y-m-d H:i:s');
            $criteria['OrderDateTo']   = now($tz)->format('Y-m-d H:i:s');
        }

        return $criteria;
    }

    /**
     * Validate a result; returns decoded object JSON or null (after printing why).
     *
     * @param  array{ok:bool,status:int,blocked_by_cloudflare:bool,json:?array,raw:string,error:?string}  $result
     * @return array<string,mixed>|null
     */
    private function guardResponse(array $result): ?array
    {
        if ($result['error']) {
            $this->error('Request error: ' . $result['error']);
            return null;
        }
        if ($result['blocked_by_cloudflare']) {
            $this->error('Blocked by Cloudflare. Run this from a Newegg-whitelisted server.');
            return null;
        }
        if ($result['json'] === null) {
            $this->warn('Non-JSON response:');
            $this->line(substr($result['raw'], 0, 1500));
            return null;
        }
        if (array_is_list($result['json'])) {
            $this->error('Newegg API error:');
            $this->line(json_encode($result['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return null;
        }

        return $result['json'];
    }

    /** @param array<int,array<string,mixed>> $orders */
    private function renderTable(array $orders): void
    {
        if (empty($orders)) {
            $this->warn('No orders returned for the given criteria.');
            return;
        }

        $rows = collect($orders)->map(fn ($o) => [
            data_get($o, 'OrderNumber'),
            data_get($o, 'OrderStatusDescription'),
            data_get($o, 'OrderDate'),
            data_get($o, 'CustomerName'),
            '$' . data_get($o, 'OrderTotalAmount', '0'),
            data_get($o, 'OrderQty'),
        ])->all();

        $this->table(['Order #', 'Status', 'Order Date', 'Customer', 'Total', 'Qty'], $rows);
    }

    /** @param array<int,array<string,mixed>> $orders */
    private function persistPage(array $orders, int &$savedOrders, int &$savedItems): void
    {
        foreach ($orders as $o) {
            $orderNumber = (string) data_get($o, 'OrderNumber');
            if ($orderNumber === '') {
                continue;
            }

            DB::transaction(function () use ($o, $orderNumber, &$savedOrders, &$savedItems) {
                NeweggOrder::updateOrCreate(
                    ['order_number' => $orderNumber],
                    [
                        'seller_id'                => data_get($o, 'SellerID'),
                        'seller_order_number'      => data_get($o, 'SellerOrderNumber'),
                        'invoice_number'           => data_get($o, 'InvoiceNumber'),
                        'order_downloaded'         => $this->toBool(data_get($o, 'OrderDownloaded')),
                        'order_date'               => $this->toDate(data_get($o, 'OrderDate')),
                        'auto_void_time'           => $this->toDate(data_get($o, 'AutoVoidTime')),
                        'order_status'             => data_get($o, 'OrderStatus'),
                        'order_status_description' => data_get($o, 'OrderStatusDescription'),
                        'customer_name'            => data_get($o, 'CustomerName'),
                        'customer_phone_number'    => data_get($o, 'CustomerPhoneNumber'),
                        'customer_email_address'   => data_get($o, 'CustomerEmailAddress'),
                        'on_time_ship_due_date'    => $this->toDate(data_get($o, 'OnTimeShipDueDate')),
                        'deliver_due_date'         => $this->toDate(data_get($o, 'DeliverDueDate')),
                        'ship_to_first_name'       => data_get($o, 'ShipToFirstName'),
                        'ship_to_last_name'        => data_get($o, 'ShipToLastName'),
                        'ship_to_company'          => data_get($o, 'ShipToCompany'),
                        'ship_to_address1'         => data_get($o, 'ShipToAddress1'),
                        'ship_to_address2'         => data_get($o, 'ShipToAddress2'),
                        'ship_to_city_name'        => data_get($o, 'ShipToCityName'),
                        'ship_to_state_code'       => data_get($o, 'ShipToStateCode'),
                        'ship_to_zip_code'         => data_get($o, 'ShipToZipCode'),
                        'ship_to_country_code'     => data_get($o, 'ShipToCountryCode'),
                        'ship_service'             => data_get($o, 'ShipService'),
                        'signature_required'       => $this->toBool(data_get($o, 'SignatureRequired')),
                        'currency_code'            => data_get($o, 'CurrencyCode'),
                        'order_item_amount'        => $this->toDecimal(data_get($o, 'OrderItemAmount')),
                        'shipping_amount'          => $this->toDecimal(data_get($o, 'ShippingAmount')),
                        'discount_amount'          => $this->toDecimal(data_get($o, 'DiscountAmount')),
                        'refund_amount'            => $this->toDecimal(data_get($o, 'RefundAmount')),
                        'sales_tax'                => $this->toDecimal(data_get($o, 'SalesTax')),
                        'order_total_amount'       => $this->toDecimal(data_get($o, 'OrderTotalAmount')),
                        'order_qty'                => data_get($o, 'OrderQty'),
                        'is_auto_void'             => $this->toBool(data_get($o, 'IsAutoVoid')),
                        'sales_channel'            => data_get($o, 'SalesChannel'),
                        'fulfillment_option'       => data_get($o, 'FulfillmentOption'),
                        'raw_json'                 => $o,
                    ]
                );
                $savedOrders++;

                // Replace line items for this order.
                NeweggOrderItem::where('order_number', $orderNumber)->delete();

                foreach (data_get($o, 'ItemInfoList', []) ?: [] as $item) {
                    NeweggOrderItem::create([
                        'order_number'           => $orderNumber,
                        'seller_part_number'     => data_get($item, 'SellerPartNumber'),
                        'newegg_item_number'     => data_get($item, 'NeweggItemNumber'),
                        'mfr_part_number'        => data_get($item, 'MfrPartNumber'),
                        'upc_code'               => data_get($item, 'UPCCode'),
                        'description'            => data_get($item, 'Description'),
                        'ordered_qty'            => data_get($item, 'OrderedQty'),
                        'shipped_qty'            => data_get($item, 'ShippedQty'),
                        'unit_price'             => $this->toDecimal(data_get($item, 'UnitPrice')),
                        'extend_unit_price'      => $this->toDecimal(data_get($item, 'ExtendUnitPrice')),
                        'extend_shipping_charge' => $this->toDecimal(data_get($item, 'ExtendShippingCharge')),
                        'status'                 => data_get($item, 'Status'),
                        'status_description'     => data_get($item, 'StatusDescription'),
                        'raw_json'               => $item,
                    ]);
                    $savedItems++;
                }
            });
        }
    }

    private function toBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['true', '1', 'yes'], true);
    }

    private function toDecimal($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function toDate($value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
