<?php

namespace App\Support\Ace;

use App\Models\ChannelMaster;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data providers for ACE (Automated Chat Event) rules: for each supported page,
 * the list of columns a rule can test and the rows (as plain arrays) to test them on.
 *
 * Each row always has `id` (the row's primary key) and `label` (what to show in logs),
 * plus one entry per column key.
 */
class AcePages
{
    public const PAGES = [
        'users' => 'Users',
        'active_channels' => 'Active Channels',
    ];

    /**
     * @return list<array{key: string, label: string, type: string}>  type: text | number | yesno
     */
    public static function columns(string $page): array
    {
        if ($page === 'users') {
            return [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'designation', 'label' => 'Designation', 'type' => 'text'],
                ['key' => 'org_level', 'label' => 'Role (director / mgr / exec)', 'type' => 'text'],
                ['key' => 'date_of_joining', 'label' => 'Date of Joining', 'type' => 'text'],
                ['key' => 'tenure_days', 'label' => 'Days since Joining', 'type' => 'number'],
                ['key' => 'docs_count', 'label' => 'Docs Count', 'type' => 'number'],
                ['key' => 'docs_ok', 'label' => 'Docs (directors exempt)', 'type' => 'yesno'],
                ['key' => 'has_resume', 'label' => 'Has Resume', 'type' => 'yesno'],
                ['key' => 'has_phone', 'label' => 'Has Phone', 'type' => 'yesno'],
            ];
        }

        if ($page === 'active_channels') {
            $cols = [
                ['key' => 'channel', 'label' => 'Channel', 'type' => 'text'],
                ['key' => 'alias', 'label' => 'Alias', 'type' => 'text'],
                ['key' => 'notes_remarks', 'label' => 'Notes and Remarks', 'type' => 'text'],
            ];
            if (Schema::hasTable('promotion_columns')) {
                foreach (DB::table('promotion_columns')->orderBy('position')->orderBy('id')->get(['id', 'name']) as $c) {
                    $cols[] = ['key' => 'col_'.$c->id, 'label' => (string) $c->name, 'type' => 'text'];
                }
            }

            return $cols;
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(string $page): array
    {
        return match ($page) {
            'users' => self::userRows(),
            'active_channels' => self::channelRows(),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function userRows(): array
    {
        $out = [];
        $users = User::query()->active()->with('docs')->orderBy('name')->get();
        foreach ($users as $u) {
            $joined = $u->date_of_joining;
            $docs = $u->docs ? $u->docs->count() : 0;
            $isDirector = method_exists($u, 'isDirector') && $u->isDirector();
            $out[] = [
                'id' => (int) $u->id,
                'label' => (string) $u->name,
                'user_id' => (int) $u->id,
                'name' => (string) $u->name,
                'email' => (string) ($u->email ?? ''),
                'phone' => (string) ($u->phone ?? ''),
                'designation' => (string) ($u->designation ?? ''),
                'org_level' => strtolower((string) ($u->org_level ?? '')),
                'date_of_joining' => $joined ? $joined->format('Y-m-d') : '',
                'tenure_days' => $joined ? (int) $joined->diffInDays(now()) : '',
                'docs_count' => $docs,
                'docs_ok' => ($docs > 0 || $isDirector) ? 'Yes' : 'No',
                'has_resume' => ! empty($u->resume_path) ? 'Yes' : 'No',
                'has_phone' => trim((string) ($u->phone ?? '')) !== '' ? 'Yes' : 'No',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function channelRows(): array
    {
        if (! Schema::hasTable('channel_master')) {
            return [];
        }

        $hasAlias = Schema::hasColumn('channel_master', 'alias');
        $cols = ['id', 'channel'];
        if ($hasAlias) {
            $cols[] = 'alias';
        }

        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->orderBy('channel')
            ->get($cols);

        $extra = collect();
        if (Schema::hasTable('promotion_channel_data')) {
            $extra = DB::table('promotion_channel_data')
                ->whereIn('channel_master_id', $channels->pluck('id'))
                ->get()
                ->keyBy('channel_master_id');
        }

        $out = [];
        foreach ($channels as $c) {
            $stored = $extra->get($c->id);
            $values = $stored && $stored->values ? (json_decode($stored->values, true) ?: []) : [];
            $row = [
                'id' => (int) $c->id,
                'label' => trim((string) $c->channel),
                'channel' => trim((string) $c->channel),
                'alias' => $hasAlias ? (string) ($c->alias ?? '') : '',
                'notes_remarks' => (string) ($stored->notes_remarks ?? ''),
            ];
            foreach ($values as $columnId => $v) {
                $row['col_'.$columnId] = (string) $v;
            }
            $out[] = $row;
        }

        return $out;
    }
}
