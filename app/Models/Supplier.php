<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'category_id', 'name', 'company', 'alias', 'sku', 'parent', 'phone', 'city',
        'email', 'whatsapp', 'wechat', 'alibaba', 'link_1688', 'qq', 'others', 'address', 'bank_details',
        'approval_status', 'next_followup',
    ];

    public function ratings()
    {
        return $this->hasMany(SupplierRating::class);
    }

    public function remarkHistories()
    {
        return $this->hasMany(SupplierRemarkHistory::class)->orderByDesc('id');
    }

    public function latestRemark()
    {
        return $this->hasOne(SupplierRemarkHistory::class)->latestOfMany();
    }

    public function bankAccounts()
    {
        return $this->hasMany(SupplierBankAccount::class);
    }

    public function bankAccountHistories()
    {
        return $this->hasMany(SupplierBankAccountHistory::class)->orderByDesc('id');
    }

    public function advances()
    {
        return $this->hasMany(SupplierAdvance::class)->orderByDesc('id');
    }

    public function latestAdvance()
    {
        return $this->hasOne(SupplierAdvance::class)->latestOfMany();
    }

    /**
     * Distinct non-empty supplier names, ordered by name — same catalog as /supplier.list with no type/category filters.
     */
    public static function distinctNamesForListPage(): Collection
    {
        return static::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();
    }

    /**
     * One row per distinct name (lowest id first) for JSON dropdowns — aligned with {@see distinctNamesForListPage()}.
     *
     * @return Collection<int, self>
     */
    public static function distinctNameRowsForDropdownJson(): Collection
    {
        return static::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->unique('name')
            ->values();
    }

    /**
     * Split free-text bank_details into label/value rows for the view modal.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function parsedBankDetailPairs(): array
    {
        $raw = trim((string) ($this->bank_details ?? ''));
        if ($raw === '') {
            return [];
        }

        $labels = [
            'SWIFT/BIC Code',
            'SWIFT/BIC',
            'BIC Code',
            'Account Number',
            'Account No',
            'Account Name',
            'Bank Address',
            'Bank Name',
            'Country/Region',
            'Beneficiary',
            'Country',
            'Branch',
            'SWIFT',
            'IBAN',
        ];
        usort($labels, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $pairs = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^([^:]{2,40})\s*:\s*(.+)$/u', $line, $m)) {
                $pairs[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
                continue;
            }

            $matched = false;
            foreach ($labels as $label) {
                if (stripos($line, $label) === 0) {
                    $value = trim(substr($line, strlen($label)));
                    $value = ltrim($value, ":\t -");
                    $pairs[] = ['label' => $label, 'value' => $value !== '' ? $value : '—'];
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $pairs[] = ['label' => '', 'value' => $line];
            }
        }

        return $pairs;
    }

}
