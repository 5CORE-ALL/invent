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
     * Due for a row.
     *
     * When payment lines exist, Due follows the payments Grand Total
     * (Amount + Freight − Paid) so a settled Grand Total of 0 yields Due 0.
     * Otherwise Due = Transit Value + Freight − Paid.
     */
    public static function computeDue($invoiceValue, $freight, $paid, $supplierPayments = null): float
    {
        $invoiceValue = (float) ($invoiceValue ?? 0);
        $freight = (float) ($freight ?? 0);
        $paid = (float) ($paid ?? 0);
        $payload = is_array($supplierPayments) ? $supplierPayments : null;
        $lines = self::paymentLines($payload);

        if ($lines !== []) {
            $amount = 0.0;
            $paidFromLines = 0.0;
            foreach ($lines as $line) {
                $amount += (float) ($line['amount'] ?? 0);
                $paidFromLines += (float) ($line['paid'] ?? 0);
            }

            return round($amount + $freight - $paidFromLines, 2);
        }

        return round(($invoiceValue + $freight) - $paid, 2);
    }
}
