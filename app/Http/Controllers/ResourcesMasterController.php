<?php

namespace App\Http\Controllers;

use App\Models\ResourceAccessLog;
use App\Models\ResourceAuditLog;
use App\Models\ResourceDepartment;
use App\Models\ResourceMaster;
use App\Models\ResourceTag;
use App\Services\ResourcesMaster\ResourceMasterStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use ZipArchive;

class ResourcesMasterController extends Controller
{
    public function __construct(
        protected ResourceMasterStorageService $storage
    ) {}

    protected function categories(): array
    {
        return array_keys(config('resources_master.categories', []));
    }

    public function dashboard()
    {
        $stats = Cache::remember('resources_master.dashboard_stats', 120, function () {
            return [
                'total' => ResourceMaster::query()->count(),
                'videos' => ResourceMaster::query()->where('file_type', 'video')->count(),
                'recent' => ResourceMaster::query()->latest()->take(5)->get(['id', 'title', 'category', 'created_at']),
                'most_viewed' => ResourceMaster::query()->orderByDesc('watch_count')->orderByDesc('download_count')->take(5)->get(['id', 'title', 'watch_count', 'download_count']),
            ];
        });

        $categories = config('resources_master.categories', []);

        return view('resources-master.dashboard', compact('stats', 'categories'));
    }

    public function section(Request $request, string $section)
    {
        if (! in_array($section, $this->categories(), true)) {
            abort(404);
        }

        $departments = Cache::remember('resource_departments.all', 3600, fn () => ResourceDepartment::orderBy('sort_order')->orderBy('name')->get());
        $tags = Cache::remember('resource_tags.all', 3600, fn () => ResourceTag::orderBy('tag_name')->get());
        $canManage = Gate::allows('resources-master.manage');
        $canForceDelete = Gate::allows('resources-master.force-delete');
        $title = config('resources_master.categories', [])[$section] ?? $section;

        return view('resources-master.section', compact('section', 'title', 'departments', 'tags', 'canManage', 'canForceDelete'));
    }

    public function data(Request $request)
    {
        $section = $request->query('section');
        if (! $section || ! in_array($section, $this->categories(), true)) {
            return response()->json(['data' => []], 422);
        }

        $user = $request->user();
        $managers = array_map('strtolower', config('resources_master.manager_emails', []));
        $isManager = in_array(strtolower((string) $user->email), $managers, true);

        $q = ResourceMaster::query()
            ->with(['departments', 'tags', 'uploader:id,name'])
            ->where('category', $section);

        if (! $isManager) {
            $q->where(function ($qq) use ($user) {
                $qq->whereDoesntHave('departments');
                if ($user->resource_department_id) {
                    $qq->orWhereHas('departments', fn ($d) => $d->whereKey($user->resource_department_id));
                }
            });
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $q->where(function ($qq) use ($s) {
                $qq->where('title', 'like', '%'.$s.'%')
                    ->orWhere('description', 'like', '%'.$s.'%');
            });
        }

        if ($request->filled('department_id')) {
            $q->whereHas('departments', fn ($d) => $d->where('resource_departments.id', $request->integer('department_id')));
        }

        if ($request->filled('tag_id')) {
            $q->whereHas('tags', fn ($t) => $t->where('resource_tags.id', $request->integer('tag_id')));
        }

        if ($request->filled('file_type')) {
            $q->where('file_type', $request->query('file_type'));
        }

        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $q->latest();

        $page = $q->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('resources-master.manage');

        $category = $request->input('category');
        if (! in_array($category, $this->categories(), true)) {
            return response()->json(['message' => 'Invalid category'], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:20000',
            'external_link' => 'nullable|url|max:2000',
            'status' => 'nullable|in:active,draft,archived',
            'version' => 'nullable|string|max:16',
            'duration_seconds' => 'nullable|integer|min:0|max:864000',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer|exists:resource_departments,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:resource_tags,id',
            'checklist_schema' => 'nullable|array',
            'allow_completed_upload' => 'boolean',
            'file' => 'nullable|file',
            'thumbnail' => 'nullable|image|max:5120',
        ]);

        $validator->after(function ($v) use ($request) {
            if (! $request->hasFile('file') && ! $request->filled('external_link')) {
                $v->errors()->add('file', 'Upload a file or provide an external link.');
            }
        });

        $validator->validate();

        $path = null;
        $fileType = 'link';
        $mime = null;
        $size = null;
        $original = null;

        if ($request->hasFile('file')) {
            $stored = $this->storage->store($request->file('file'), $category);
            $path = $stored['path'];
            $fileType = $stored['file_type'];
            $mime = $stored['mime'];
            $size = $stored['size'];
            $original = $stored['original'];
        } elseif ($request->filled('external_link')) {
            if (! $this->storage->isAllowedExternalUrl($request->input('external_link'))) {
                return response()->json(['message' => 'Only YouTube or Google Drive links are allowed.'], 422);
            }
            $fileType = 'link';
        }

        $thumbPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbPath = $this->storage->storeThumbnail($request->file('thumbnail'), $category);
        }

        $resource = DB::transaction(function () use ($request, $category, $path, $fileType, $mime, $size, $original, $thumbPath) {
            $r = ResourceMaster::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'category' => $category,
                'file_type' => $fileType,
                'mime_type' => $mime,
                'file_path' => $path,
                'file_size' => $size,
                'original_filename' => $original,
                'external_link' => $request->input('external_link'),
                'thumbnail_path' => $thumbPath,
                'uploaded_by' => $request->user()->id,
                'status' => $request->input('status', 'active'),
                'version' => $request->input('version', '1.0'),
                'duration_seconds' => $request->input('duration_seconds'),
                'checklist_schema' => $request->input('checklist_schema'),
                'allow_completed_upload' => $request->boolean('allow_completed_upload'),
            ]);

            $r->departments()->sync($request->input('department_ids', []));
            $r->tags()->sync($request->input('tag_ids', []));

            ResourceAuditLog::create([
                'resource_id' => $r->id,
                'user_id' => $request->user()->id,
                'action' => 'upload',
                'meta' => ['title' => $r->title],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return $r;
        });

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true, 'resource' => $resource->load(['departments', 'tags'])]);
    }

    public function update(Request $request, ResourceMaster $resource)
    {
        Gate::authorize('update', $resource);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:500',
            'description' => 'nullable|string|max:20000',
            'external_link' => 'nullable|url|max:2000',
            'status' => 'nullable|in:active,draft,archived',
            'version' => 'nullable|string|max:16',
            'duration_seconds' => 'nullable|integer|min:0|max:864000',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer|exists:resource_departments,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:resource_tags,id',
            'checklist_schema' => 'nullable|array',
            'allow_completed_upload' => 'boolean',
            'file' => 'nullable|file',
            'thumbnail' => 'nullable|image|max:5120',
        ]);
        $validator->validate();

        if ($request->hasFile('file')) {
            $stored = $this->storage->store($request->file('file'), $resource->category);
            $this->storage->deleteIfExists($resource->file_path);
            $resource->file_path = $stored['path'];
            $resource->file_type = $stored['file_type'];
            $resource->mime_type = $stored['mime'];
            $resource->file_size = $stored['size'];
            $resource->original_filename = $stored['original'];
        }

        if ($request->filled('external_link')) {
            if (! $this->storage->isAllowedExternalUrl($request->input('external_link'))) {
                return response()->json(['message' => 'Only YouTube or Google Drive links are allowed.'], 422);
            }
            $resource->external_link = $request->input('external_link');
        }

        if ($request->hasFile('thumbnail')) {
            $this->storage->deleteIfExists($resource->thumbnail_path);
            $resource->thumbnail_path = $this->storage->storeThumbnail($request->file('thumbnail'), $resource->category);
        }

        $resource->fill($request->only(['title', 'description', 'status', 'version', 'duration_seconds', 'checklist_schema', 'allow_completed_upload']));
        $resource->save();

        if ($request->has('department_ids')) {
            $resource->departments()->sync($request->input('department_ids', []));
        }
        if ($request->has('tag_ids')) {
            $resource->tags()->sync($request->input('tag_ids', []));
        }

        ResourceAuditLog::create([
            'resource_id' => $resource->id,
            'user_id' => $request->user()->id,
            'action' => 'update',
            'meta' => ['title' => $resource->title],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true, 'resource' => $resource->fresh()->load(['departments', 'tags'])]);
    }

    public function destroy(Request $request, ResourceMaster $resource)
    {
        Gate::authorize('delete', $resource);

        $title = $resource->title;
        $rid = $resource->id;
        $resource->delete();

        ResourceAuditLog::create([
            'resource_id' => $rid,
            'user_id' => $request->user()->id,
            'action' => 'delete',
            'meta' => ['title' => $title],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true]);
    }

    public function forceDestroy(Request $request, int $id)
    {
        $resource = ResourceMaster::withTrashed()->findOrFail($id);
        Gate::authorize('forceDelete', $resource);

        $this->storage->deleteIfExists($resource->file_path);
        $this->storage->deleteIfExists($resource->thumbnail_path);

        $resource->forceDelete();

        ResourceAuditLog::create([
            'resource_id' => null,
            'user_id' => $request->user()->id,
            'action' => 'force_delete',
            'meta' => ['id' => $id],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true]);
    }

    public function restore(Request $request, int $id)
    {
        $resource = ResourceMaster::onlyTrashed()->findOrFail($id);
        Gate::authorize('restore', $resource);

        $resource->restore();

        ResourceAuditLog::create([
            'resource_id' => $resource->id,
            'user_id' => $request->user()->id,
            'action' => 'restore',
            'meta' => ['title' => $resource->title],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true]);
    }

    public function download(Request $request, ResourceMaster $resource)
    {
        Gate::authorize('view', $resource);

        if ($resource->isLinkOnly() && $resource->external_link) {
            ResourceAccessLog::create([
                'resource_id' => $resource->id,
                'user_id' => $request->user()->id,
                'action' => 'view',
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return redirect()->away($resource->external_link);
        }

        if (! $resource->file_path) {
            abort(404);
        }

        $disk = Storage::disk($this->storage->disk());
        if (! $disk->exists($resource->file_path)) {
            abort(404);
        }

        ResourceAccessLog::create([
            'resource_id' => $resource->id,
            'user_id' => $request->user()->id,
            'action' => 'download',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $resource->increment('download_count');

        $name = $resource->original_filename ?: basename($resource->file_path);

        return $disk->download($resource->file_path, $name);
    }

    public function bulkUpload(Request $request)
    {
        Gate::authorize('resources-master.manage');

        $request->validate([
            'category' => 'required|in:'.implode(',', $this->categories()),
            'files' => 'required|array|min:1|max:50',
            'files.*' => 'file',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer|exists:resource_departments,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:resource_tags,id',
        ]);

        $created = [];
        foreach ($request->file('files', []) as $file) {
            $stored = $this->storage->store($file, $request->input('category'));
            $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $r = ResourceMaster::create([
                'title' => Str::limit($title, 500),
                'description' => null,
                'category' => $request->input('category'),
                'file_type' => $stored['file_type'],
                'mime_type' => $stored['mime'],
                'file_path' => $stored['path'],
                'file_size' => $stored['size'],
                'original_filename' => $stored['original'],
                'uploaded_by' => $request->user()->id,
                'status' => 'active',
            ]);
            $r->departments()->sync($request->input('department_ids', []));
            $r->tags()->sync($request->input('tag_ids', []));
            ResourceAuditLog::create([
                'resource_id' => $r->id,
                'user_id' => $request->user()->id,
                'action' => 'upload',
                'meta' => ['bulk' => true, 'title' => $r->title],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
            $created[] = $r->id;
        }

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true, 'created_ids' => $created]);
    }

    public function importCsv(Request $request)
    {
        Gate::authorize('resources-master.manage');

        $request->validate([
            'category' => 'required|in:'.implode(',', $this->categories()),
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $path = $request->file('file')->getRealPath();
        $fh = fopen($path, 'r');
        if (! $fh) {
            return response()->json(['message' => 'Could not read CSV'], 422);
        }
        $header = fgetcsv($fh);
        if (! $header) {
            fclose($fh);

            return response()->json(['message' => 'Empty CSV'], 422);
        }
        $header = array_map(fn ($h) => Str::slug(trim($h), '_'), $header);
        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            if (count($header) !== count($row)) {
                continue;
            }
            $data = array_combine($header, $row);
            if (! $data || empty($data['title'])) {
                continue;
            }
            ResourceMaster::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $request->input('category'),
                'file_type' => 'link',
                'external_link' => $data['external_link'] ?? $data['link'] ?? null,
                'uploaded_by' => $request->user()->id,
                'status' => $data['status'] ?? 'active',
            ]);
            $count++;
        }
        fclose($fh);

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true, 'imported' => $count]);
    }

    public function importZip(Request $request)
    {
        Gate::authorize('resources-master.manage');

        $request->validate([
            'category' => 'required|in:'.implode(',', $this->categories()),
            'file' => 'required|file|mimes:zip|max:'.(int) config('resources_master.max_upload_kb', 102400),
        ]);

        $zipPath = $request->file('file')->getRealPath();
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return response()->json(['message' => 'Invalid ZIP'], 422);
        }

        $category = $request->input('category');
        $count = 0;
        $tempBase = storage_path('app/tmp/zip_import_'.Str::random(12));
        @mkdir($tempBase, 0755, true);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }
                $base = basename($name);
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if ($ext === '' || in_array($ext, config('resources_master.blocked_extensions', []), true)) {
                    continue;
                }
                $dest = $tempBase.'/'.$base;
                if (! @copy('zip://'.$zipPath.'#'.$name, $dest)) {
                    continue;
                }
                $uploaded = HttpUploadedFile::createFromBase(new SymfonyFile($dest), true);
                try {
                    $stored = $this->storage->store($uploaded, $category);
                } catch (\Throwable) {
                    continue;
                }
                $title = pathinfo($base, PATHINFO_FILENAME);
                ResourceMaster::create([
                    'title' => Str::limit($title, 500),
                    'category' => $category,
                    'file_type' => $stored['file_type'],
                    'mime_type' => $stored['mime'],
                    'file_path' => $stored['path'],
                    'file_size' => $stored['size'],
                    'original_filename' => $stored['original'],
                    'uploaded_by' => $request->user()->id,
                    'status' => 'active',
                ]);
                $count++;
            }
        } finally {
            $zip->close();
            if (is_dir($tempBase)) {
                array_map('unlink', glob($tempBase.'/*') ?: []);
                @rmdir($tempBase);
            }
        }

        Cache::forget('resources_master.dashboard_stats');

        return response()->json(['success' => true, 'imported' => $count]);
    }

    public function logView(Request $request, ResourceMaster $resource)
    {
        Gate::authorize('view', $resource);

        ResourceAccessLog::create([
            'resource_id' => $resource->id,
            'user_id' => $request->user()->id,
            'action' => 'view',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function watch(Request $request, ResourceMaster $resource)
    {
        Gate::authorize('view', $resource);

        $resource->increment('watch_count');

        ResourceAccessLog::create([
            'resource_id' => $resource->id,
            'user_id' => $request->user()->id,
            'action' => 'watch',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'watch_count' => $resource->fresh()->watch_count]);
    }

    public function thumbnail(Request $request, ResourceMaster $resource)
    {
        Gate::authorize('view', $resource);

        if (! $resource->thumbnail_path) {
            abort(404);
        }

        return Storage::disk($this->storage->disk())->response($resource->thumbnail_path);
    }
}
