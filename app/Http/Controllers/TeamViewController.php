<?php

namespace App\Http\Controllers;

use App\Models\DesignationRrItem;
use App\Models\ManagerJunior;
use App\Models\TeamReview;
use App\Models\User;
use App\Support\ChatWorkspace;
use App\Support\DesignationKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Team directory with reporting hierarchy, visible to every logged-in team
 * member. The only write action is a peer star-review (0-5) of another
 * member across the Personal / Team / R&R sections.
 */
class TeamViewController extends Controller
{
    private const LEVEL_ORDER = ['director' => 0, 'mgr' => 1, 'exec' => 2];

    /**
     * Review sections and their sub-sections (factors). Each factor is rated 0-5 stars.
     */
    private const REVIEW_SECTIONS = [
        'personal' => [
            'label' => 'Personal',
            'factors' => [
                'attitude' => 'Attitude & Positivity',
                'communication' => 'Communication',
                'punctuality' => 'Punctuality & Discipline',
                'learning' => 'Learning & Growth',
            ],
        ],
        'team' => [
            'label' => 'Team',
            'factors' => [
                'collaboration' => 'Collaboration',
                'helpfulness' => 'Helpfulness & Support',
                'responsiveness' => 'Responsiveness',
                'respect' => 'Respect & Trust',
            ],
        ],
        'rr' => [
            'label' => 'R&R',
            'factors' => [
                'quality' => 'Quality of Work',
                'timeliness' => 'Timeliness',
                'ownership' => 'Ownership & Accountability',
                'results' => 'Results & Impact',
            ],
        ],
    ];

    public function index()
    {
        $users = User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'avatar', 'designation', 'org_level']);

        $ratings = $this->ratingSummary();
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

        // Flat rows (depth-first, so people stay next to their manager).
        $rows = [];
        $toRows = function (array $m) use (&$rows, &$toRows, $ratings) {
            $rows[] = [
                'id' => $m['id'],
                'name' => $m['name'],
                'avatar' => $m['avatar'],
                'designation' => $m['designation'],
                'level' => $m['level'],
                'email' => $m['email'],
                'phone' => $m['phone'],
                'ratings' => $ratings[$m['id']] ?? $this->emptyRatings(),
                'rr' => $m['rr'],
                // "Contact For": the first few R&R titles, i.e. what to reach this person about.
                'contact_for' => array_slice($m['rr'], 0, 3),
                'reports' => count($m['children']),
                // Searchable text: name, designation, R&R and Contact For.
                'search' => mb_strtolower(trim(
                    $m['name'] . ' ' . $m['designation'] . ' ' . implode(' ', $m['rr']) . ' ' . implode(' ', array_slice($m['rr'], 0, 3))
                )),
            ];
            foreach ($m['children'] as $child) {
                $toRows($child);
            }
        };

        foreach ($tree as $m) {
            $toRows($m);
        }
        foreach ($unassigned as $m) {
            $toRows($m);
        }

        $counts = ['director' => 0, 'mgr' => 0, 'exec' => 0];
        foreach ($members as $m) {
            if (isset($counts[$m['level']])) {
                $counts[$m['level']]++;
            }
        }

        return view('team-view.index', [
            'rows' => $rows,
            'total' => count($members),
            'counts' => $counts,
            'slogan' => $this->dailySlogan(),
            'teamAvg' => $this->teamAverage(array_keys($members)),
            'meId' => (int) auth()->id(),
        ]);
    }

    /**
     * Average star rating across all reviews received by the (active) team, overall and per section.
     *
     * @param  list<int>  $userIds
     * @return array{avg: float|null, reviews: int, reviewees: int, sections: array<string, float|null>}
     */
    private function teamAverage(array $userIds): array
    {
        $out = ['avg' => null, 'reviews' => 0, 'reviewees' => 0, 'sections' => []];
        foreach (self::REVIEW_SECTIONS as $key => $def) {
            $out['sections'][$key] = null;
        }
        if (! $userIds || ! Schema::hasTable('team_reviews')) {
            return $out;
        }

        $all = DB::table('team_reviews')->whereIn('reviewee_id', $userIds)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as n, COUNT(DISTINCT reviewee_id) as reviewees')->first();
        if ($all && (int) $all->n > 0) {
            $out['avg'] = round((float) $all->avg_rating, 1);
            $out['reviews'] = (int) $all->n;
            $out['reviewees'] = (int) $all->reviewees;
        }

        DB::table('team_reviews')->whereIn('reviewee_id', $userIds)
            ->groupBy('section')->selectRaw('section, AVG(rating) as avg_rating')->get()
            ->each(function ($r) use (&$out) {
                if (array_key_exists($r->section, $out['sections'])) {
                    $out['sections'][$r->section] = round((float) $r->avg_rating, 1);
                }
            });

        return $out;
    }

    /**
     * The logged-in user's own received reviews: overall average plus per-section / per-factor
     * averages. Reviewers stay anonymous (only counts are returned).
     */
    public function myReview()
    {
        $meId = (int) auth()->id();
        abort_unless($meId > 0, 403);

        $factorRows = [];
        $overall = ['avg' => null, 'reviewers' => 0];
        $sectionStats = [];

        if (Schema::hasTable('team_reviews')) {
            $all = DB::table('team_reviews')->where('reviewee_id', $meId)
                ->selectRaw('AVG(rating) as avg_rating, COUNT(DISTINCT reviewer_id) as reviewers')->first();
            if ($all && (int) $all->reviewers > 0) {
                $overall = ['avg' => round((float) $all->avg_rating, 1), 'reviewers' => (int) $all->reviewers];
            }

            DB::table('team_reviews')->where('reviewee_id', $meId)
                ->groupBy('section')
                ->selectRaw('section, AVG(rating) as avg_rating, COUNT(DISTINCT reviewer_id) as reviewers')
                ->get()
                ->each(function ($r) use (&$sectionStats) {
                    $sectionStats[$r->section] = ['avg' => round((float) $r->avg_rating, 1), 'reviewers' => (int) $r->reviewers];
                });

            DB::table('team_reviews')->where('reviewee_id', $meId)
                ->groupBy('section', 'factor')
                ->selectRaw('section, factor, AVG(rating) as avg_rating, COUNT(*) as n')
                ->get()
                ->each(function ($r) use (&$factorRows) {
                    $factorRows[$r->section][$r->factor] = ['avg' => round((float) $r->avg_rating, 1), 'n' => (int) $r->n];
                });
        }

        $sections = [];
        foreach (self::REVIEW_SECTIONS as $key => $def) {
            $factors = [];
            foreach ($def['factors'] as $fKey => $fLabel) {
                $factors[] = [
                    'label' => $fLabel,
                    'avg' => $factorRows[$key][$fKey]['avg'] ?? null,
                    'n' => $factorRows[$key][$fKey]['n'] ?? 0,
                ];
            }
            $sections[] = [
                'key' => $key,
                'label' => $def['label'],
                'avg' => $sectionStats[$key]['avg'] ?? null,
                'reviewers' => $sectionStats[$key]['reviewers'] ?? 0,
                'factors' => $factors,
            ];
        }

        return response()->json([
            'ok' => true,
            'name' => (string) (auth()->user()->name ?? ''),
            'avg' => $overall['avg'],
            'reviewers' => $overall['reviewers'],
            'sections' => $sections,
        ]);
    }

    /**
     * Find or create the 1:1 chat with a member and redirect into it.
     */
    public function chatWith(User $user)
    {
        $me = auth()->user();
        abort_unless($me, 403);
        $other = User::query()->active()->findOrFail($user->id);

        if ((int) $other->id === (int) $me->id || ! ChatWorkspace::tablesReady()) {
            return redirect()->route('chat.index');
        }

        ChatWorkspace::bootstrap($me);
        $channel = ChatWorkspace::dmBetween($me, $other);

        return redirect()->route('chat.index', ['channel' => (int) $channel->id]);
    }

    /**
     * Current user's own ratings for a member, plus the member's overall per-factor averages.
     */
    public function reviewShow(User $user)
    {
        $member = User::query()->active()->findOrFail($user->id);
        $meId = (int) auth()->id();
        $mine = [];
        $overall = [];

        if (Schema::hasTable('team_reviews')) {
            TeamReview::query()->where('reviewee_id', $member->id)->where('reviewer_id', $meId)
                ->get(['section', 'factor', 'rating'])
                ->each(function (TeamReview $r) use (&$mine) {
                    $mine[$r->section][$r->factor] = (int) $r->rating;
                });

            DB::table('team_reviews')->where('reviewee_id', $member->id)
                ->groupBy('section', 'factor')
                ->selectRaw('section, factor, AVG(rating) as avg_rating, COUNT(*) as n')
                ->get()
                ->each(function ($r) use (&$overall) {
                    $overall[$r->section][$r->factor] = ['avg' => round((float) $r->avg_rating, 1), 'n' => (int) $r->n];
                });
        }

        $sections = [];
        foreach (self::REVIEW_SECTIONS as $key => $def) {
            $factors = [];
            foreach ($def['factors'] as $fKey => $fLabel) {
                $factors[] = [
                    'key' => $fKey,
                    'label' => $fLabel,
                    'mine' => $mine[$key][$fKey] ?? null,
                    'avg' => $overall[$key][$fKey]['avg'] ?? null,
                    'n' => $overall[$key][$fKey]['n'] ?? 0,
                ];
            }
            $sections[] = ['key' => $key, 'label' => $def['label'], 'factors' => $factors];
        }

        return response()->json([
            'ok' => true,
            'user' => ['id' => (int) $member->id, 'name' => (string) $member->name],
            'self' => $meId === (int) $member->id,
            'sections' => $sections,
        ]);
    }

    /**
     * Save (create/update) the current user's ratings for a member. Factors left unrated are skipped.
     */
    public function reviewStore(Request $request, User $user)
    {
        $member = User::query()->active()->findOrFail($user->id);
        $meId = (int) auth()->id();
        if ($meId === (int) $member->id) {
            return response()->json(['ok' => false, 'message' => 'You cannot review yourself.'], 422);
        }
        if (! Schema::hasTable('team_reviews')) {
            return response()->json(['ok' => false, 'message' => 'Reviews are not set up yet (run migrations).'], 503);
        }

        $request->validate([
            'ratings' => 'required|array',
            'ratings.*' => 'array',
            'ratings.*.*' => 'nullable|integer|min:0|max:5',
        ]);

        $saved = 0;
        foreach ((array) $request->input('ratings', []) as $section => $factors) {
            if (! isset(self::REVIEW_SECTIONS[$section]) || ! is_array($factors)) {
                continue;
            }
            foreach ($factors as $factor => $rating) {
                if (! isset(self::REVIEW_SECTIONS[$section]['factors'][$factor]) || $rating === null || $rating === '') {
                    continue;
                }
                TeamReview::query()->updateOrCreate(
                    ['reviewee_id' => $member->id, 'reviewer_id' => $meId, 'section' => $section, 'factor' => $factor],
                    ['rating' => (int) $rating]
                );
                $saved++;
            }
        }

        return response()->json([
            'ok' => true,
            'saved' => $saved,
            'ratings' => $this->ratingSummary([(int) $member->id])[(int) $member->id] ?? $this->emptyRatings(),
        ]);
    }

    /**
     * @return array<string, array{avg: float|null, n: int}>
     */
    private function emptyRatings(): array
    {
        $out = [];
        foreach (array_keys(self::REVIEW_SECTIONS) as $key) {
            $out[$key] = ['avg' => null, 'n' => 0];
        }

        return $out;
    }

    /**
     * Per-member, per-section average star rating and number of reviewers.
     *
     * @param  list<int>|null  $userIds  limit to these reviewees (null = everyone)
     * @return array<int, array<string, array{avg: float|null, n: int}>>
     */
    private function ratingSummary(?array $userIds = null): array
    {
        if (! Schema::hasTable('team_reviews')) {
            return [];
        }

        $q = DB::table('team_reviews')
            ->groupBy('reviewee_id', 'section')
            ->selectRaw('reviewee_id, section, AVG(rating) as avg_rating, COUNT(DISTINCT reviewer_id) as n');
        if ($userIds !== null) {
            $q->whereIn('reviewee_id', $userIds);
        }

        $out = [];
        foreach ($q->get() as $r) {
            $out[(int) $r->reviewee_id] ??= $this->emptyRatings();
            if (isset($out[(int) $r->reviewee_id][$r->section])) {
                $out[(int) $r->reviewee_id][$r->section] = ['avg' => round((float) $r->avg_rating, 1), 'n' => (int) $r->n];
            }
        }

        return $out;
    }

    /**
     * One motivational slogan per calendar day (same for everyone, rotates daily).
     *
     * @return array{text: string, author: string}
     */
    private function dailySlogan(): array
    {
        $slogans = [
            ['Alone we can do so little; together we can do so much.', 'Helen Keller'],
            ['Great things in business are never done by one person. They are done by a team.', 'Steve Jobs'],
            ['Talent wins games, but teamwork wins championships.', 'Michael Jordan'],
            ['Coming together is a beginning, staying together is progress, working together is success.', 'Henry Ford'],
            ['The strength of the team is each individual member.', 'Phil Jackson'],
            ['Quality is not an act, it is a habit.', 'Aristotle'],
            ['Small daily improvements are the key to staggering long-term results.', '5Core'],
            ['Dream big. Start small. Act now.', '5Core'],
            ['Your only limit is you. Make today count.', '5Core'],
            ['Success is the sum of small efforts, repeated day in and day out.', 'Robert Collier'],
            ['Don\'t watch the clock; do what it does. Keep going.', 'Sam Levenson'],
            ['The best way to predict the future is to create it.', 'Peter Drucker'],
            ['Believe you can and you\'re halfway there.', 'Theodore Roosevelt'],
            ['Energy and persistence conquer all things.', 'Benjamin Franklin'],
            ['Do something today that your future self will thank you for.', '5Core'],
            ['It always seems impossible until it\'s done.', 'Nelson Mandela'],
            ['Teamwork makes the dream work.', 'John C. Maxwell'],
            ['Act as if what you do makes a difference. It does.', 'William James'],
            ['Opportunities don\'t happen. You create them.', 'Chris Grosser'],
            ['Be the energy you want to attract.', '5Core'],
            ['Winning starts with beginning.', 'Robert H. Schuller'],
            ['Hard work beats talent when talent doesn\'t work hard.', 'Tim Notke'],
            ['Focus on progress, not perfection.', '5Core'],
            ['Every expert was once a beginner.', 'Helen Hayes'],
            ['Strive for excellence, and success will follow.', '5Core'],
            ['Together everyone achieves more.', 'TEAM'],
            ['Make each day your masterpiece.', 'John Wooden'],
            ['What you do today can improve all your tomorrows.', 'Ralph Marston'],
            ['Courage is taking the next step, even when you can\'t see the whole staircase.', '5Core'],
            ['Show up. Work hard. Be kind. Repeat.', '5Core'],
            ['The harder you work for something, the greater you\'ll feel when you achieve it.', 'Unknown'],
        ];

        $pick = $slogans[(int) now()->format('z') % count($slogans)];

        return ['text' => $pick[0], 'author' => $pick[1]];
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
