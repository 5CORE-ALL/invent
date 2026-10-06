<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const REMOVED = ['dhgate', 'offerup'];

    /** Source file in public/images/channel-logos => matcher on the normalized channel name. */
    private function logoTargets(): array
    {
        return [
            'shopify-b2c.svg' => fn (string $n) => in_array($n, ['shopify', 'shopifyb2c'], true),
            'tiktok2.svg' => fn (string $n) => in_array($n, ['tiktok2', 'tiktokshop2'], true),
            'vinted.svg' => fn (string $n) => $n === 'vinted',
            'business-5core.png' => fn (string $n) => str_contains($n, '5core')
                && (str_contains($n, 'business') || str_contains($n, 'b2b')),
        ];
    }

    private function normalize(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $name));
    }

    private function isRemoved(?string $name): bool
    {
        $n = $this->normalize($name);
        foreach (self::REMOVED as $prefix) {
            if (str_starts_with($n, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $rows = DB::table('channel_master')->get(['id', 'channel']);

        $removeIds = $rows->filter(fn ($r) => $this->isRemoved($r->channel))->pluck('id')->all();
        if ($removeIds !== []) {
            DB::table('channel_master')->whereIn('id', $removeIds)->update(['status' => 'Inactive']);
        }

        if (! Schema::hasColumn('channel_master', 'logo')) {
            return;
        }

        foreach ($this->logoTargets() as $file => $matches) {
            $source = public_path('images/channel-logos/'.$file);
            if (! is_file($source)) {
                continue;
            }
            $ids = $rows->filter(fn ($r) => $matches($this->normalize($r->channel)))->pluck('id')->all();
            if ($ids === []) {
                continue;
            }
            $path = 'channel-logos/'.$file;
            Storage::disk('public')->put($path, file_get_contents($source));
            DB::table('channel_master')->whereIn('id', $ids)->update(['logo' => $path]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $ids = DB::table('channel_master')->get(['id', 'channel'])
            ->filter(fn ($r) => $this->isRemoved($r->channel))
            ->pluck('id')->all();
        if ($ids !== []) {
            DB::table('channel_master')->whereIn('id', $ids)->update(['status' => 'Active']);
        }
    }
};
