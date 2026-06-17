<?php

namespace App\Services;

use App\Models\PurchaseExecOption;
use App\Models\PurchasePageExecAssignment;
use Illuminate\Support\Facades\Auth;

class PurchasePageExecService
{
    public const DEFAULT_OPTIONS = ['Ajay', 'Atin', 'Nitish', 'Sruti', 'Candy'];

    public const PAGE_KEYS = ['to_order', 'mip', 'r2s', 'forecast'];

    public static function userCanEdit(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email === 'president@5core.com') {
            return true;
        }

        $name = strtolower(trim((string) ($user->name ?? '')));

        return $name === 'candy';
    }

    public function ensureDefaults(): void
    {
        foreach (self::DEFAULT_OPTIONS as $i => $name) {
            PurchaseExecOption::query()->firstOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1]
            );
        }

        foreach (self::PAGE_KEYS as $pageKey) {
            PurchasePageExecAssignment::query()->firstOrCreate(
                ['page_key' => $pageKey],
                ['assigned_exec' => null]
            );
        }
    }

    public function getOptions(): array
    {
        $this->ensureDefaults();

        return PurchaseExecOption::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function getAssignment(string $pageKey): ?string
    {
        $this->assertValidPageKey($pageKey);
        $this->ensureDefaults();

        $row = PurchasePageExecAssignment::query()
            ->where('page_key', $pageKey)
            ->first();

        $value = trim((string) ($row?->assigned_exec ?? ''));

        return $value !== '' ? $value : null;
    }

    public function setAssignment(string $pageKey, ?string $assignedExec): PurchasePageExecAssignment
    {
        $this->assertValidPageKey($pageKey);
        $this->ensureDefaults();

        $assignedExec = trim((string) ($assignedExec ?? ''));
        if ($assignedExec === '') {
            $assignedExec = null;
        } elseif (! in_array($assignedExec, $this->getOptions(), true)) {
            throw new \InvalidArgumentException('Invalid executive option.');
        }

        return PurchasePageExecAssignment::query()->updateOrCreate(
            ['page_key' => $pageKey],
            ['assigned_exec' => $assignedExec]
        );
    }

    public function addOption(string $name): PurchaseExecOption
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Executive name is required.');
        }
        if (mb_strlen($name) > 64) {
            throw new \InvalidArgumentException('Executive name is too long.');
        }

        $existing = PurchaseExecOption::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])
            ->first();
        if ($existing) {
            return $existing;
        }

        $maxSort = (int) PurchaseExecOption::query()->max('sort_order');

        return PurchaseExecOption::query()->create([
            'name' => $name,
            'sort_order' => $maxSort + 1,
        ]);
    }

    public function pagePayload(string $pageKey): array
    {
        return [
            'page_key' => $pageKey,
            'assigned_exec' => $this->getAssignment($pageKey),
            'options' => $this->getOptions(),
            'can_edit' => self::userCanEdit(),
        ];
    }

    private function assertValidPageKey(string $pageKey): void
    {
        if (! in_array($pageKey, self::PAGE_KEYS, true)) {
            throw new \InvalidArgumentException('Invalid page key.');
        }
    }
}
