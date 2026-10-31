<?php

namespace App\Http\Controllers;

use App\Models\DesignationRrItem;
use App\Models\ManagerJunior;
use App\Models\User;
use App\Support\DesignationKey;

/**
 * Read-only team directory with reporting hierarchy, visible to every
 * logged-in team member. There are intentionally no write endpoints.
 */
class TeamViewController extends Controller
{
    private const LEVEL_ORDER = ['director' => 0, 'mgr' => 1, 'exec' => 2];

    public function index()
    {
        $users = User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'avatar', 'designation', 'org_level']);

        $rrByDesignation = $this->rrTitlesByDesignation();
        $defaultAvatar = asset('images/users/avatar-2.jpg');

        $members = [];
        foreach ($users as $u) {
            $key = mb_strtolower(DesignationKey::canonical((string) $u->designation));
            $members[$u->id] = [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'email' => (string) ($u->email ?? ''),
                'phone' => (string) ($u->phone ?? ''),
                'designation' => DesignationKey::canonical((string) $u->designation),
                'level' => strtolower((string) ($u->org_level ?? '')),
                'avatar' => $u->avatar ? asset('storage/' . $u->avatar) : $defaultAvatar,
                'rr' => $key !== '' ? ($rrByDesignation[$key] ?? []) : [],
                'children' => [],
            ];
        }

        // Reporting lines (manager -> junior), restricted to active users.
        $childrenOf = [];
        $hasManager = [];
        ManagerJunior::query()->get(['manager_user_id', 'junior_user_id'])->each(
            function (ManagerJunior $r) use ($members, &$childrenOf, &$hasManager) {
                $m = (int) $r->manager_user_id;
                $j = (int) $r->junior_user_id;
                if ($m === $j || ! isset($members[$m], $members[$j])) {
                    return;
                }
                $childrenOf[$m][] = $j;
                $hasManager[$j] = true;
            }
        );

        $sortIds = function (array $ids) use ($members) {
            usort($ids, function ($a, $b) use ($members) {
                $la = self::LEVEL_ORDER[$members[$a]['level']] ?? 3;
                $lb = self::LEVEL_ORDER[$members[$b]['level']] ?? 3;

                return [$la, $members[$a]['name']] <=> [$lb, $members[$b]['name']];
            });

            return $ids;
        };

        $build = function (int $id, array $path) use (&$build, $members, $childrenOf, $sortIds) {
            $node = $members[$id];
            $path[$id] = true;
            foreach ($sortIds(array_unique($childrenOf[$id] ?? [])) as $childId) {
                if (isset($path[$childId])) {
                    continue; // guard against cycles in the reporting data
                }
                $node['children'][] = $build($childId, $path);
            }

            return $node;
        };

        $rootIds = array_filter(array_keys($members), fn ($id) => ! isset($hasManager[$id]));

        $tree = [];
        $unassigned = [];
        foreach ($sortIds($rootIds) as $id) {
            $isLeader = isset($childrenOf[$id]) || in_array($members[$id]['level'], ['director', 'mgr'], true);
            if ($isLeader) {
                $tree[] = $build($id, []);
            } else {
                $unassigned[] = $members[$id];
            }
        }

        // Flatten into Tabulator "dataTree" rows (children go under `_children`).
        $toRow = function (array $m) use (&$toRow) {
            $row = [
                'id' => $m['id'],
                'name' => $m['name'],
                'avatar' => $m['avatar'],
                'designation' => $m['designation'],
                'level' => $m['level'],
                'email' => $m['email'],
                'phone' => $m['phone'],
                'rr' => $m['rr'],
                'reports' => count($m['children']),
                'search' => mb_strtolower(trim(
                    $m['name'] . ' ' . $m['designation'] . ' ' . $m['email'] . ' ' . $m['phone'] . ' ' . implode(' ', $m['rr'])
                )),
            ];
            if (count($m['children'])) {
                $row['_children'] = array_map($toRow, $m['children']);
            }

            return $row;
        };

        $rows = array_map($toRow, $tree);

        if (count($unassigned)) {
            $rows[] = [
                'id' => 0,
                'group' => true,
                'name' => 'Other Team Members (no reporting line set)',
                'reports' => count($unassigned),
                'search' => '',
                '_children' => array_map($toRow, $unassigned),
            ];
        }

        return view('team-view.index', [
            'rows' => $rows,
            'total' => count($members),
        ]);
    }

    /**
     * @return array<string, list<string>> lower-cased canonical designation => R&R titles
     */
    private function rrTitlesByDesignation(): array
    {
        $out = [];
        DesignationRrItem::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['designation', 'title'])
            ->each(function (DesignationRrItem $i) use (&$out) {
                $key = mb_strtolower(DesignationKey::canonical((string) $i->designation));
                $title = trim((string) $i->title);
                if ($key !== '' && $title !== '' && ! in_array($title, $out[$key] ?? [], true)) {
                    $out[$key][] = $title;
                }
            });

        return $out;
    }
}
