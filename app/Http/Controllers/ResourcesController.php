<?php

namespace App\Http\Controllers;

use App\Models\DriverData;
use App\Models\DriverFolder;
use App\Models\ResourceDepartment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourcesController extends Controller
{
    public const TAG_OPTIONS = ['SOP', 'CL', 'Form', 'Video', 'L1', 'L2'];

    public const RESOURCE_OPTIONS = ['R&R Files', 'Training Resources', 'Media Gallery', 'Links / Videos', 'Overview'];

    public function index(Request $request)
    {
        $folderId = (int) $request->query('folder', 0);
        $folders = DriverFolder::query()
            ->where('parent_id', $folderId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $breadcrumbs = $this->breadcrumbs($folderId);
        $folderTree = $this->folderTreeData($folderId);
        $departments = ResourceDepartment::orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name']);
        $tags = self::TAG_OPTIONS;
        $resourceOptions = self::RESOURCE_OPTIONS;

        return view('resources.index', compact('folderId', 'folders', 'breadcrumbs', 'folderTree', 'departments', 'tags', 'resourceOptions'));
    }

    public function data(Request $request)
    {
        $folderId = (int) $request->query('folder', 0);
        $search = trim((string) $request->query('search', ''));
        $words = $this->searchWords($search);

        $query = DriverData::query()
            ->with(['creator:id,name', 'department:id,slug,name']);

        // Typing in the search box looks in every folder; without a search only the open folder is listed.
        if ($words === []) {
            $query->where('folder_id', $folderId);
        }

        $type = $request->query('type');
        if ($type && $type !== 'all') {
            $query->where('file_type', $type);
        }

        $department = $request->query('department');
        if ($department !== null && $department !== '' && $department !== 'all') {
            $query->where('department_id', (int) $department);
        }

        $tag = $request->query('tag');
        if ($tag !== null && $tag !== '' && $tag !== 'all') {
            $query->where('tag', $tag);
        }

        $resource = $request->query('resource');
        if ($resource !== null && $resource !== '' && $resource !== 'all') {
            $query->where('resource', $resource);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 24)));

        if ($words !== []) {
            return $this->searchResponse($request, $query, $words, $perPage);
        }

        $items = $query->latest('updated_at')->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'folders' => [],
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
                'per_page' => $items->perPage(),
            ],
        ]);
    }

    /**
     * Search across every folder. A name matches when every typed word is found in the title, file
     * name, link, tag or resource (separators such as _ - . are ignored), or is within a typo of a word there.
     */
    protected function searchResponse(Request $request, $query, array $words, int $perPage)
    {
        $page = max(1, (int) $request->query('page', 1));

        $scored = [];
        $rows = (clone $query)->toBase()
            ->select(['id', 'title', 'file_name', 'location_url', 'tag', 'resource'])
            ->cursor();
        foreach ($rows as $row) {
            $hay = $this->normalizeSearch(implode(' ', [$row->title, $row->file_name, $row->location_url, $row->tag, $row->resource]));
            $score = $this->fuzzyScore($hay, $words);
            if ($score > 0) {
                // A title match outranks a match found only in the file name or URL.
                $score += $this->fuzzyScore($this->normalizeSearch((string) $row->title), $words) > 0 ? 5 : 0;
                $scored[(int) $row->id] = $score;
            }
        }
        arsort($scored);
        $total = count($scored);
        $ids = array_slice(array_keys($scored), ($page - 1) * $perPage, $perPage);

        $folderPaths = $this->folderPaths();
        $found = $ids === [] ? collect() : $query->whereIn('id', $ids)->get()->keyBy('id');
        $data = [];
        foreach ($ids as $id) {
            if ($item = $found->get($id)) {
                $item->setAttribute('folder_path', $folderPaths[(int) $item->folder_id] ?? 'All Resources');
                $data[] = $item;
            }
        }

        return response()->json([
            'data' => $data,
            'folders' => $this->matchingFolders($words, $folderPaths),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'total' => $total,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function matchingFolders(array $words, array $folderPaths): array
    {
        $counts = DriverData::query()
            ->selectRaw('folder_id, COUNT(*) as total')
            ->groupBy('folder_id')
            ->pluck('total', 'folder_id');

        $scored = [];
        foreach (DriverFolder::query()->get(['id', 'name', 'parent_id']) as $folder) {
            $score = $this->fuzzyScore($this->normalizeSearch((string) $folder->name), $words);
            if ($score > 0) {
                $scored[(int) $folder->id] = ['score' => $score, 'folder' => $folder];
            }
        }
        uasort($scored, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcasecmp($a['folder']->name, $b['folder']->name));

        $out = [];
        foreach (array_slice($scored, 0, 30, true) as $entry) {
            $folder = $entry['folder'];
            $path = $folderPaths[(int) $folder->id] ?? $folder->name;
            $parent = trim(substr($path, 0, max(0, strlen($path) - strlen($folder->name))), ' /');
            $out[] = [
                'id' => (int) $folder->id,
                'name' => $folder->name,
                'parent_path' => $parent !== '' ? $parent : 'All Resources',
                'count' => (int) ($counts[$folder->id] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string> folder id => "Parent / Child"
     */
    protected function folderPaths(): array
    {
        $all = DriverFolder::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
        $paths = [];
        foreach ($all as $folder) {
            $parts = [];
            $current = $folder;
            $guard = 0;
            while ($current && $guard < 20) {
                array_unshift($parts, $current->name);
                $current = $all->get((int) $current->parent_id);
                $guard++;
            }
            $paths[(int) $folder->id] = implode(' / ', $parts);
        }

        return $paths;
    }

    protected function normalizeSearch(string $value): string
    {
        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    protected function searchWords(string $search): array
    {
        $words = array_filter(explode(' ', $this->normalizeSearch($search)), fn ($w) => $w !== '');

        return array_values(array_map(fn ($w) => mb_substr($w, 0, 60), $words));
    }

    /** 0 = no match; higher = better. Every word must match, exactly or within a small typo. */
    protected function fuzzyScore(string $hay, array $words): int
    {
        if ($hay === '') {
            return 0;
        }

        $compact = str_replace(' ', '', $hay);
        $tokens = null;
        $score = 0;
        foreach ($words as $word) {
            if (str_contains($hay, $word)) {
                $score += str_contains(' '.$hay, ' '.$word) ? 5 : 3;

                continue;
            }
            if (str_contains($compact, $word)) {
                $score += 2;

                continue;
            }

            $len = strlen($word);
            $tolerance = $len >= 7 ? 2 : ($len >= 4 ? 1 : 0);
            if ($tolerance === 0) {
                return 0;
            }

            $tokens ??= explode(' ', $hay);
            $matched = false;
            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }
                $token = substr($token, 0, 60);
                // Compare with the whole word and with its start (so a half-typed word with a typo still matches).
                $distance = min(levenshtein($word, $token), levenshtein($word, substr($token, 0, $len)));
                if ($distance <= $tolerance) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return 0;
            }
            $score += 1;
        }

        return $score;
    }

    public function storeLink(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'location_url' => 'required|url|max:2000',
            'folder_id' => 'nullable|integer|min:0',
            'department_id' => 'nullable|integer|exists:resource_departments,id',
            'tag' => ['nullable', Rule::in(self::TAG_OPTIONS)],
            'resource' => ['nullable', Rule::in(self::RESOURCE_OPTIONS)],
        ]);

        $item = DriverData::create([
            'uuid' => (string) Str::uuid(),
            'title' => $validated['title'],
            'location_url' => $validated['location_url'],
            'folder_id' => (int) ($validated['folder_id'] ?? 0),
            'department_id' => $validated['department_id'] ?? null,
            'tag' => $validated['tag'] ?? null,
            'resource' => $validated['resource'] ?? null,
            'file_type' => $this->detectLinkType($validated['location_url']),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'item' => $item->load(['creator:id,name', 'department:id,slug,name'])]);
    }

    public function storeFile(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:51200',
            'folder_id' => 'nullable|integer|min:0',
            'department_id' => 'nullable|integer|exists:resource_departments,id',
            'tag' => ['nullable', Rule::in(self::TAG_OPTIONS)],
            'resource' => ['nullable', Rule::in(self::RESOURCE_OPTIONS)],
        ]);

        $uploaded = $request->file('file');
        $extension = strtolower($uploaded->getClientOriginalExtension() ?: 'file');
        $storedName = Str::slug(pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(6).'.'.$extension;
        $storedName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $storedName) ?: Str::random(12).'.'.$extension;

        Storage::disk('local')->putFileAs('driver-files', $uploaded, $storedName);

        $item = DriverData::create([
            'uuid' => (string) Str::uuid(),
            'title' => $validated['title'],
            'folder_id' => (int) ($validated['folder_id'] ?? 0),
            'department_id' => $validated['department_id'] ?? null,
            'tag' => $validated['tag'] ?? null,
            'resource' => $validated['resource'] ?? null,
            'file_name' => $uploaded->getClientOriginalName(),
            'file_data' => $storedName,
            'file_size' => (string) $uploaded->getSize(),
            'file_extension' => $extension,
            'file_type' => $this->detectFileType($extension),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'item' => $item->load(['creator:id,name', 'department:id,slug,name'])]);
    }

    public function storeFolder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|min:0',
        ]);

        $folder = DriverFolder::create([
            'name' => $validated['name'],
            'parent_id' => (int) ($validated['parent_id'] ?? 0),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'folder' => $folder]);
    }

    public function update(Request $request, DriverData $driverData)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'location_url' => 'nullable|url|max:2000',
            'folder_id' => 'nullable|integer|min:0',
            'department_id' => 'nullable|integer|exists:resource_departments,id',
            'tag' => ['nullable', Rule::in(self::TAG_OPTIONS)],
            'resource' => ['nullable', Rule::in(self::RESOURCE_OPTIONS)],
        ]);

        $newUrl = $validated['location_url'] ?? $driverData->location_url;

        $attributes = [
            'title' => $validated['title'],
            'location_url' => $newUrl,
            'folder_id' => (int) ($validated['folder_id'] ?? $driverData->folder_id),
            'department_id' => $request->has('department_id') ? ($validated['department_id'] ?? null) : $driverData->department_id,
            'tag' => $request->has('tag') ? ($validated['tag'] ?? null) : $driverData->tag,
            'resource' => $request->has('resource') ? ($validated['resource'] ?? null) : $driverData->resource,
        ];

        if (in_array($driverData->file_type, ['link', 'gsheet'], true)) {
            $attributes['file_type'] = $this->detectLinkType($newUrl);
        }

        $driverData->update($attributes);

        return response()->json(['success' => true, 'item' => $driverData->fresh()->load(['creator:id,name', 'department:id,slug,name'])]);
    }

    public function updateFolder(Request $request, DriverFolder $folder)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Folder name cannot be empty.'], 422);
        }

        $duplicate = DriverFolder::query()
            ->where('parent_id', $folder->parent_id)
            ->where('id', '!=', $folder->id)
            ->where('name', $name)
            ->exists();
        if ($duplicate) {
            return response()->json(['success' => false, 'message' => 'A folder named "'.$name.'" already exists here.'], 422);
        }

        $folder->update(['name' => $name]);

        return response()->json(['success' => true, 'folder' => $folder]);
    }

    public function destroy(Request $request, DriverData $driverData)
    {
        abort_unless($request->user()?->email === 'president@5core.com', 403);

        $driverData->deleteStoredFile();
        $driverData->delete();

        return response()->json(['success' => true]);
    }

    public function destroyFolder(DriverFolder $folder)
    {
        if (DriverData::where('folder_id', $folder->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Folder is not empty. Move or delete items first.'], 422);
        }

        if (DriverFolder::where('parent_id', $folder->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Folder has subfolders. Remove them first.'], 422);
        }

        $folder->delete();

        return response()->json(['success' => true]);
    }

    public function download(DriverData $driverData): StreamedResponse
    {
        $path = $driverData->storagePath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $driverData->file_name ?: basename($path));
    }

    public function preview(DriverData $driverData)
    {
        $path = $driverData->storagePath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        if ($driverData->file_type === 'image') {
            return response()->file(Storage::disk('local')->path($path));
        }

        return redirect()->route('resources.download', $driverData);
    }

    protected function detectLinkType(?string $url): string
    {
        if ($url && preg_match('#https?://(docs|sheets)\.google\.com/spreadsheets#i', $url)) {
            return 'gsheet';
        }

        return 'link';
    }

    protected function detectFileType(string $extension): string
    {
        return match ($extension) {
            'xls', 'xlsx', 'csv' => 'spreadsheet',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'image',
            'pdf' => 'pdf',
            'mp4', 'webm', 'mov' => 'video',
            'doc', 'docx' => 'document',
            default => $extension ?: 'type',
        };
    }

    protected function breadcrumbs(int $folderId): array
    {
        $crumbs = [['id' => 0, 'name' => 'All Resources']];
        $current = $folderId;

        $guard = 0;
        while ($current > 0 && $guard < 20) {
            $folder = DriverFolder::find($current);
            if (! $folder) {
                break;
            }
            array_splice($crumbs, 1, 0, [['id' => $folder->id, 'name' => $folder->name]]);
            $current = (int) $folder->parent_id;
            $guard++;
        }

        return $crumbs;
    }

    protected function folderTreeData(int $activeFolderId): array
    {
        $all = DriverFolder::query()->orderBy('name')->get(['id', 'name', 'parent_id']);
        $counts = DriverData::query()
            ->selectRaw('folder_id, COUNT(*) as total')
            ->groupBy('folder_id')
            ->pluck('total', 'folder_id');

        $byParent = $all->groupBy(fn ($folder) => (int) $folder->parent_id);
        $ancestorIds = $this->ancestorFolderIds($activeFolderId);

        $build = function (int $parentId) use (&$build, $byParent, $counts, $activeFolderId, $ancestorIds): array {
            return ($byParent->get($parentId, collect()))->map(function ($folder) use (&$build, $counts, $activeFolderId, $ancestorIds) {
                $children = $build((int) $folder->id);

                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'count' => (int) ($counts[$folder->id] ?? 0),
                    'active' => $folder->id === $activeFolderId,
                    'open' => in_array($folder->id, $ancestorIds, true) || $folder->id === $activeFolderId,
                    'children' => $children,
                ];
            })->values()->all();
        };

        return [
            'root_count' => (int) ($counts[0] ?? 0),
            'folders' => $build(0),
            'active_folder_id' => $activeFolderId,
            'ancestor_ids' => $ancestorIds,
        ];
    }

    protected function ancestorFolderIds(int $folderId): array
    {
        $ids = [];
        $current = $folderId;

        while ($current > 0) {
            $ids[] = $current;
            $folder = DriverFolder::find($current);
            $current = $folder ? (int) $folder->parent_id : 0;
        }

        return $ids;
    }
}
