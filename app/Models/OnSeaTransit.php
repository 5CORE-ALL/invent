<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnSeaTransit extends Model
{
    protected $table = 'on_sea_transit';
    
    protected $fillable = [
        'container_sl_no', 'bl_check', 'bl_link', 'isf', 'etd', 'eta_port', 'port_arrival',
        'eta_date_ohio', 'status', 'isf_usa_agent', 'duty_calcu',
        'invoice_send_to_dominic', 'arrival_notice_email', 'remarks', 'invoice_value',
        'freight', 'agent', 'paid', 'balance', 'supplier_payments', 'details', 'archived_at'
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'supplier_payments' => 'array',
    ];
    
    public function detailsHistory()
    {
        return $this->hasMany(OnSeaTransitDetailsHistory::class, 'on_sea_transit_id');
    }

    /**
     * Flatten supplier + agent payment lines, skipping empty placeholder rows.
     *
     * @param  array<string, mixed>|list<mixed>|null  $supplierPayments
     * @return list<array<string, mixed>>
     */
    public static function paymentLines(?array $supplierPayments): array
    {
        if (! is_array($supplierPayments)) {
            return [];
        }

        $supplier = [];
        $agent = [];

        if (isset($supplierPayments['supplier']) || isset($supplierPayments['agent'])) {
            $supplier = is_array($supplierPayments['supplier'] ?? null) ? $supplierPayments['supplier'] : [];
            $agent = is_array($supplierPayments['agent'] ?? null) ? $supplierPayments['agent'] : [];
        } else {
            foreach ($supplierPayments as $line) {
                if (! is_array($line)) {
                    continue;
                }
                if (! empty($line['agent']) || (($line['category'] ?? '') === 'agent')) {
                    $agent[] = $line;
                } else {
                    $supplier[] = $line;
                }
            }
        }

        $lines = [];
        foreach (array_merge($supplier, $agent) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $name = trim((string) ($line['name'] ?? $line['supplier_name'] ?? $line['agent'] ?? ''));
            $amount = (float) ($line['amount'] ?? 0);
            $paid = (float) ($line['paid'] ?? 0);
            if ($name === '' && $amount == 0.0 && $paid == 0.0) {
                continue;
            }
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * True when the payments modal has been applied (including empty lines).
     * Null / missing means Due still follows Transit Value.
     *
     * @param  array<string, mixed>|list<mixed>|null  $supplierPayments
     */
    public static function paymentsWereApplied($supplierPayments): bool
    {
        if (! is_array($supplierPayments)) {
            return false;
        }

        return array_key_exists('supplier', $supplierPayments)
            || array_key_exists('agent', $supplierPayments)
            || $supplierPayments !== [];
    }

    /**
     * Due for a row.
     *
     * After payments are applied, Due is the payments Grand Total
     * (Amount + Freight − Paid). An applied Grand Total of 0 (including
     * cleared / empty lines) yields Due 0.
     * Otherwise Due = Transit Value + Freight − Paid.
     */
    public static function computeDue($invoiceValue, $freight, $paid, $supplierPayments = null): float
    {
        $invoiceValue = (float) ($invoiceValue ?? 0);
        $freight = (float) ($freight ?? 0);
        $paid = (float) ($paid ?? 0);
        $payload = is_array($supplierPayments) ? $supplierPayments : null;

        if (self::paymentsWereApplied($payload)) {
            $amount = 0.0;
            $paidFromLines = 0.0;
            foreach (self::paymentLines($payload) as $line) {
                $amount += (float) ($line['amount'] ?? 0);
                $paidFromLines += (float) ($line['paid'] ?? 0);
            }

            return round($amount + $freight - $paidFromLines, 2);
        }

        return round(($invoiceValue + $freight) - $paid, 2);
    }
}
