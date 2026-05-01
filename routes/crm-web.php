<?php

use App\Http\Controllers\Crm\CommunicationController;
use App\Http\Controllers\Crm\CrmDashboardController;
use App\Http\Controllers\Crm\CustomerController;
use App\Http\Controllers\Crm\FollowUpController;
use App\Http\Controllers\Crm\ShopifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM (web) — auth required
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('crm')
    ->name('crm.')
    ->group(function () {
        Route::get('dashboard', [CrmDashboardController::class, 'index'])->name('dashboard');

        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/tabs/{tab}', [CustomerController::class, 'tab'])
            ->where('tab', 'overview|follow-ups|communications|shopify-data|orders')
            ->name('customers.tabs');

        Route::post('follow-ups/{follow_up}/status', [FollowUpController::class, 'changeStatus'])
            ->name('follow-ups.change-status');

        Route::get('follow-ups/export', [FollowUpController::class, 'exportCsv'])->name('follow-ups.export');

        Route::resource('follow-ups', FollowUpController::class);

        Route::post('communications', [CommunicationController::class, 'store'])->name('communications.store');
        Route::get('customers/{customer}/communications', [CommunicationController::class, 'index'])
            ->name('customers.communications.index');

        Route::get('shopify/customers', [ShopifyController::class, 'shopifyCustomersIndex'])->name('shopify.customers.index');
        Route::get('shopify/customers/data', [ShopifyController::class, 'shopifyCustomersData'])->name('shopify.customers.data');
        Route::post('shopify/customers/{shopify_customer}/follow-ups', [ShopifyController::class, 'storeCustomerFollowUp'])
            ->name('shopify.customers.follow-ups.store');
        Route::get('shopify/others', [ShopifyController::class, 'shopifyOthersIndex'])->name('shopify.others.index');
        Route::get('shopify/others/data', [ShopifyController::class, 'shopifyOthersData'])->name('shopify.others.data');
        Route::get('shopify/orders', [ShopifyController::class, 'shopifyOrdersIndex'])->name('shopify.orders.index');
        Route::get('shopify/orders/data', [ShopifyController::class, 'shopifyOrdersData'])->name('shopify.orders.data');

        Route::prefix('shopify/sync')->name('shopify.sync.')->group(function () {
            Route::post('customers', [ShopifyController::class, 'syncCustomers'])->name('customers');
            Route::post('orders', [ShopifyController::class, 'syncOrders'])->name('orders');
            Route::post('products', [ShopifyController::class, 'syncProducts'])->name('products');
        });

        // Alias route requested for CRM Shopify customer sync action.
        Route::post('shopify/sync-customers', [ShopifyController::class, 'syncCustomers'])
            ->name('shopify.sync-customers');

        // Alias route requested for CRM Shopify order sync action.
        Route::post('shopify/sync-orders', [ShopifyController::class, 'syncOrders'])
            ->name('shopify.sync-orders');
    });
