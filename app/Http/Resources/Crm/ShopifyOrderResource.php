<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Crm\ShopifyOrder */
class ShopifyOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'shopify_order_id' => $this->shopify_order_id,
            'shopify_customer_id' => $this->shopify_customer_id,

            'total' => [
                'amount' => $this->total_price !== null ? (string) $this->total_price : null,
                'currency' => $this->currency,
            ],

            'order_status' => $this->order_status,
            'ordered_at' => $this->order_date?->toIso8601String(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'shopify_customer' => $this->whenLoaded('shopifyCustomer', function () {
                if ($this->shopifyCustomer === null) {
                    return null;
                }

                return [
                    'id' => $this->shopifyCustomer->id,
                    'shopify_customer_id' => $this->shopifyCustomer->shopify_customer_id,
                    'email' => $this->shopifyCustomer->email,
                    'name' => trim(implode(' ', array_filter([
                        $this->shopifyCustomer->first_name,
                        $this->shopifyCustomer->last_name,
                    ]))),
                ];
            }),

            'raw' => $this->when(
                $request->boolean('include_raw_payload'),
                $this->raw_payload
            ),
        ];
    }
}
