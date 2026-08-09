<?php

namespace App\Http\Controllers;

use App\Models\BadgeData;
use App\Models\UserDashboardPreference;
use App\Support\Badges\BadgeDataCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardPreferenceController extends Controller
{
    private function canCustomize(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        $email = strtolower(trim((string) ($user->email ?? '')));

        return in_array($email, array_map('strtolower', config('dashboard_customize.editor_emails', [])), true);
    }

    public function show(): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $pref = UserDashboardPreference::forUser((int) $user->id);

        return response()->json([
            'success' => true,
            'can_customize' => $this->canCustomize($user),
            'preferences' => $pref->asPayload(),
            'kpi_catalog' => BadgeDataCatalog::allCatalogOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }
        if (! $this->canCustomize($user)) {
            return response()->json(['success' => false, 'message' => 'Not allowed to customize the dashboard.'], 403);
        }

        $data = $request->validate([
            'hidden_items' => 'nullable|array',
            'hidden_items.*' => 'string|max:120',
            'custom_links' => 'nullable|array',
            'custom_kpis' => 'nullable|array',
        ]);

        $hidden = array_values(array_unique(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            $data['hidden_items'] ?? []
        ))));

        $customLinks = [];
        foreach (($data['custom_links'] ?? []) as $cardId => $links) {
            $cardKey = trim((string) $cardId);
            if ($cardKey === '' || ! is_array($links)) {
                continue;
            }
            $clean = [];
            foreach ($links as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));
                if ($label === '' || $url === '') {
                    continue;
                }
                $okUrl = str_starts_with($url, '/')
                    || str_starts_with($url, 'http://')
                    || str_starts_with($url, 'https://')
                    || str_starts_with($url, '#');
                if (! $okUrl) {
                    continue;
                }
                $clean[] = ['label' => mb_substr($label, 0, 80), 'url' => mb_substr($url, 0, 500)];
            }
            if ($clean !== []) {
                $customLinks[$cardKey] = $clean;
            }
        }

        $customKpis = [];
        foreach (($data['custom_kpis'] ?? []) as $cardId => $kpis) {
            $cardKey = trim((string) $cardId);
            if ($cardKey === '' || ! is_array($kpis)) {
                continue;
            }
            $clean = [];
            foreach ($kpis as $kpi) {
                if (! is_array($kpi)) {
                    continue;
                }
                $key = trim((string) ($kpi['key'] ?? ''));
                if ($key === '' || ! str_starts_with($key, BadgeDataCatalog::KEY_PREFIX)) {
                    continue;
                }
                $parsed = BadgeDataCatalog::parseKey($key);
                if (! $parsed) {
                    continue;
                }
                $label = trim((string) ($kpi['label'] ?? ''));
                $entry = ['key' => $key];
                if ($label !== '') {
                    $entry['label'] = mb_substr($label, 0, 80);
                }
                $clean[] = $entry;
            }
            if ($clean !== []) {
                $customKpis[$cardKey] = $clean;
            }
        }

        $pref = UserDashboardPreference::forUser((int) $user->id);
        $pref->hidden_items = $hidden;
        $pref->custom_links = $customLinks;
        $pref->custom_kpis = $customKpis;
        $pref->save();

        return response()->json([
            'success' => true,
            'preferences' => $pref->asPayload(),
        ]);
    }

    /**
     * Resolve custom KPI keys to live display values for the dashboard.
     */
    public static function resolveCustomKpis(array $customKpisByCard): array
    {
        $out = [];
        foreach ($customKpisByCard as $cardId => $kpis) {
            if (! is_array($kpis)) {
                continue;
            }
            $resolved = [];
            foreach ($kpis as $kpi) {
                $key = (string) ($kpi['key'] ?? '');
                $parsed = BadgeDataCatalog::parseKey($key);
                if (! $parsed) {
                    continue;
                }
                $page = $parsed['page'];
                $field = $parsed['field'];
                $value = BadgeData::dataForPage($page)[$field] ?? null;
                $resolved[] = [
                    'key' => $key,
                    'item_id' => $cardId.'__custom_'.md5($key),
                    'label' => $kpi['label'] ?? BadgeDataCatalog::labelFor($page, $field),
                    'value' => is_numeric($value) ? (float) $value : null,
                    'value_display' => BadgeDataCatalog::formatDisplay($page, $field, $value),
                    'page' => $page,
                    'field' => $field,
                ];
            }
            $out[$cardId] = $resolved;
        }

        return $out;
    }
}
