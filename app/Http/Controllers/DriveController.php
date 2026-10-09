<?php

namespace App\Http\Controllers;

use App\Models\DriveItem;
use App\Models\DriveItemVersion;
use App\Models\DriveShare;
use App\Models\User;
use App\Services\Drive\DriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DriveController extends Controller
{
    public function __construct(private DriveService $drive)
    {
    }

    /* ------------------------------------------------------------------ */
    /* Page                                                                */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return view('drive.index', [
            'chunkSize' => $this->chunkSize(),
            'maxFileBytes' => DriveService::MAX_FILE_BYTES,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Listing                                                             */
    /* ------------------------------------------------------------------ */

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $view = (string) $request->query('view', 'my');
        $q = trim((string) $request->query('q', ''));
        $folder = null;
        $folderRole = null;
        $breadcrumbs = [];

        if ($request->filled('folder')) {
            $folder = $this->findItem((string) $request->query('folder'));
            $folderRole = $this->drive->roleFor($folder, $user);
            abort_unless($folderRole && $folder->isFolder() && ! $this->drive->isTrashedChain($folder), 404);

            foreach (array_merge($this->drive->ancestors($folder), [$folder]) as $crumb) {
                if ($this->drive->roleFor($crumb, $user)) {
                    $breadcrumbs[] = ['id' => $crumb->uuid, 'name' => $crumb->name];
                }
            }
            $children = DriveItem::with('owner:id,name,email')->where('parent_id', $folder->id)->whereNull('trashed_at')->get();
            $items = $this->drive->serializeMany($children, $user, $folderRole);

            return response()->json([
                'items' => $items,
                'breadcrumbs' => $breadcrumbs,
                'root' => $folderRole === 'owner' ? 'my' : 'shared',
                'folder' => $this->drive->serialize($folder, $folderRole),
            ]);
        }

        $query = DriveItem::with('owner:id,name,email');
        $resolveEach = false;

        switch ($view) {
            case 'shared':
                $ids = $this->sharedWithMeIds($user);
                $query->whereIn('id', $ids)->whereNull('trashed_at')->where('owner_id', '!=', $user->id);
                $resolveEach = true;
                break;
            case 'shared_by_me':
                $query->where('owner_id', $user->id)->whereNull('trashed_at')
                    ->where(fn ($w) => $w->whereIn('id', DriveShare::select('item_id'))->orWhere('link_access', 'view'));
                break;
            case 'recent':
                $ids = $this->sharedWithMeIds($user);
                $query->where('type', 'file')->whereNull('trashed_at')
                    ->where(fn ($w) => $w->where('owner_id', $user->id)->orWhereIn('id', $ids))
                    ->orderByDesc('updated_at')->limit(60);
                $resolveEach = true;
                break;
            case 'starred':
                $starIds = DB::table('drive_stars')->where('user_id', $user->id)->pluck('item_id')->all();
                $query->whereIn('id', $starIds)->whereNull('trashed_at');
                $resolveEach = true;
                break;
            case 'trash':
                $query->where('owner_id', $user->id)->whereNotNull('trashed_at')->whereColumn('trashed_root_id', 'id')
                    ->orderByDesc('trashed_at');
                break;
            case 'search':
                $ids = $this->sharedWithMeIds($user);
                $query->whereNull('trashed_at')
                    ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->where(fn ($w) => $w->where('owner_id', $user->id)->orWhereIn('id', $ids))
                    ->limit(200);
                $resolveEach = true;
                break;
            default:
                $view = 'my';
                $query->where('owner_id', $user->id)->whereNull('parent_id')->whereNull('trashed_at');
        }

        $rows = $query->get();
        if (in_array($view, ['starred', 'recent', 'search'], true)) {
            $rows = $rows->filter(fn ($i) => (int) $i->owner_id === (int) $user->id || $this->drive->roleFor($i, $user))->values();
        }

        return response()->json([
            'items' => $this->drive->serializeMany($rows, $user, null, $resolveEach),
            'breadcrumbs' => [],
            'root' => $view,
            'folder' => null,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = DriveItem::where('owner_id', $user->id)->where('type', 'file')
            ->groupBy('mime_type', 'extension')
            ->selectRaw('mime_type, extension, SUM(size) total, COUNT(*) files')
            ->get();

        $byCategory = [];
        $total = 0;
        $files = 0;
        foreach ($rows as $row) {
            $cat = DriveItem::categoryFor((string) $row->mime_type, (string) $row->extension);
            $bucket = match ($cat) {
                'image' => 'images',
                'video' => 'videos',
                'audio' => 'audio',
                'pdf', 'doc', 'sheet', 'slide', 'text' => 'documents',
                default => 'other',
            };
            $byCategory[$bucket] = ($byCategory[$bucket] ?? 0) + (int) $row->total;
            $total += (int) $row->total;
            $files += (int) $row->files;
        }
        $versions = (int) DriveItemVersion::whereIn('item_id', DriveItem::where('owner_id', $user->id)->select('id'))->sum('size');

        return response()->json([
            'total' => $total + $versions,
            'files' => $files,
            'versions' => $versions,
            'by_category' => $byCategory,
            'folders' => DriveItem::where('owner_id', $user->id)->where('type', 'folder')->whereNull('trashed_at')->count(),
        ]);
    }

    /** Folder tree for the "Move to" dialog: folders the user can put things into. */
    public function folders(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($request->filled('parent')) {
            $parent = $this->findItem((string) $request->query('parent'));
            $role = $this->drive->roleFor($parent, $user);
            abort_unless($role, 404);
            $rows = DriveItem::where('parent_id', $parent->id)->where('type', 'folder')->whereNull('trashed_at')->orderBy('name')->get();
            $crumbs = array_map(fn ($a) => ['id' => $a->uuid, 'name' => $a->name], array_merge($this->drive->ancestors($parent), [$parent]));
        } else {
            $rows = DriveItem::where('owner_id', $user->id)->whereNull('parent_id')->where('type', 'folder')->whereNull('trashed_at')->orderBy('name')->get();
            $role = 'owner';
            $crumbs = [];
        }

        return response()->json([
            'folders' => $rows->map(fn ($f) => ['id' => $f->uuid, 'name' => $f->name, 'color' => $f->color])->values(),
            'can_edit' => $this->drive->canEditRole($role),
            'breadcrumbs' => $crumbs,
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $item = $this->findItem($uuid);
        $role = $this->drive->roleFor($item, $user);
        abort_unless($role, 404);

        $item->load(['owner:id,name,email', 'creator:id,name', 'shares.user:id,name,email,avatar', 'versions.uploader:id,name', 'activities' => fn ($q) => $q->limit(30), 'activities.user:id,name']);

        $data = $this->drive->serializeMany(collect([$item]), $user, null, true)[0];
        $ancestors = $this->drive->ancestors($item);
        $data['location'] = $ancestors ? collect($ancestors)->pluck('name')->implode(' / ') : ($role === 'owner' ? 'My Drive' : 'Shared with me');
        $parent = $ancestors ? end($ancestors) : null;
        $data['parent'] = ($parent && $this->drive->roleFor($parent, $user)) ? $parent->uuid : null;
        $data['created_by'] = $item->creator?->name;
        $data['public_url'] = $item->link_access === 'view' ? $this->drive->publicPageUrl($item) : null;
        $data['direct_url'] = $item->link_access === 'view' ? $this->drive->publicRawUrl($item) : null;
        $data['internal_url'] = route('drive.index').'#/item/'.$item->uuid;

        $inherited = [];
        foreach ($ancestors as $ancestor) {
            foreach ($ancestor->shares()->get() as $s) {
                $inherited[] = ['email' => $s->email, 'role' => $s->role, 'from' => $ancestor->name];
            }
        }

        $data['shares'] = $item->shares->map(fn (DriveShare $s) => [
            'id' => $s->id,
            'email' => $s->email,
            'name' => $s->user?->name,
            'role' => $s->role,
            'registered' => (bool) $s->user_id,
        ])->values();
        $data['inherited_shares'] = $inherited;
        $data['versions'] = $item->versions->map(fn (DriveItemVersion $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'size' => $v->size,
            'by' => $v->uploader?->name,
            'created_at' => $v->created_at?->toIso8601String(),
            'download_url' => route('drive.version.file', [$item->uuid, $v->id]),
        ])->values();
        $data['activity'] = $item->activities->map(fn ($a) => [
            'action' => $a->action,
            'details' => $a->details,
            'by' => $a->user?->name,
            'at' => $a->created_at?->toIso8601String(),
        ])->values();

        return response()->json($data);
    }

    /* ------------------------------------------------------------------ */
    /* Create                                                              */
    /* ------------------------------------------------------------------ */

    public function createFolder(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:250', 'parent' => 'nullable|string']);
        $user = $request->user();
        $parent = $this->editableParent($request->input('parent'), $user);
        $ownerId = $parent ? (int) $parent->owner_id : (int) $user->id;
        $name = $this->drive->uniqueName($ownerId, $parent?->id, $this->drive->cleanName($request->input('name')), 'folder');

        $folder = DriveItem::create([
            'owner_id' => $ownerId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'parent_id' => $parent?->id,
            'type' => 'folder',
            'name' => $name,
            'color' => $request->input('color'),
        ]);
        $this->drive->log($folder, $user, 'created', 'Folder created');

        return response()->json(['ok' => true, 'item' => $this->drive->serializeMany(collect([$folder->load('owner')]), $user, null, true)[0]]);
    }

    public function createTextFile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:250',
            'parent' => 'nullable|string',
            'content' => 'nullable|string|max:'.DriveService::MAX_TEXT_EDIT_BYTES,
        ]);
        $user = $request->user();
        $parent = $this->editableParent($request->input('parent'), $user);
        $ownerId = $parent ? (int) $parent->owner_id : (int) $user->id;

        $name = $this->drive->cleanName($request->input('name'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, DriveService::TEXT_EXTENSIONS, true)) {
            $name .= '.txt';
            $ext = 'txt';
        }
        $name = $this->drive->uniqueName($ownerId, $parent?->id, $name, 'file');
        $content = (string) $request->input('content', '');
        [$disk, $path] = $this->drive->storeContents($content, $ownerId, $ext);

        $item = DriveItem::create([
            'owner_id' => $ownerId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'parent_id' => $parent?->id,
            'type' => 'file',
            'name' => $name,
            'extension' => $ext,
            'mime_type' => $this->drive->detectMime(Storage::disk($disk)->path($path), $ext),
            'size' => strlen($content),
            'disk' => $disk,
            'path' => $path,
        ]);
        $this->drive->log($item, $user, 'created', 'File created');

        return response()->json(['ok' => true, 'item' => $this->drive->serializeMany(collect([$item->load('owner')]), $user, null, true)[0]]);
    }

    /**
     * Chunked upload. Chunks of one file must arrive in order; the last chunk creates the item.
     * conflict=replace saves into the existing same-named file as a new version; keep creates a copy.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]{8,64}$/'],
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1|max:100000',
            'name' => 'required|string|max:500',
            'relative_path' => 'nullable|string|max:1000',
            'parent' => 'nullable|string',
            'conflict' => 'nullable|in:keep,replace',
            'replace' => 'nullable|string',
            'chunk' => 'required|file',
        ]);
        $user = $request->user();
        $index = (int) $request->input('chunk_index');
        $total = (int) $request->input('total_chunks');

        $tmpDir = storage_path('app/drive-tmp/'.$user->id);
        File::ensureDirectoryExists($tmpDir);
        $part = $tmpDir.'/'.$request->input('upload_id').'.part';
        $meta = $part.'.json';

        if ($index === 0) {
            foreach (glob($tmpDir.'/*') ?: [] as $stale) {
                if (filemtime($stale) < time() - 86400) {
                    @unlink($stale);
                }
            }
            // Validate permissions before accepting any bytes.
            $this->resolveUploadTarget($request, $user);
            @unlink($part);
            file_put_contents($meta, json_encode(['next' => 0]));
        }

        $state = is_file($meta) ? json_decode((string) file_get_contents($meta), true) : null;
        if (! $state || (int) $state['next'] !== $index) {
            return response()->json(['ok' => false, 'message' => 'Upload out of sync, please retry.'], 409);
        }

        $in = fopen($request->file('chunk')->getRealPath(), 'rb');
        $out = fopen($part, 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        clearstatcache(true, $part);

        if (filesize($part) > DriveService::MAX_FILE_BYTES) {
            @unlink($part);
            @unlink($meta);

            return response()->json(['ok' => false, 'message' => 'File is larger than the 5 GB limit.'], 413);
        }

        if ($index < $total - 1) {
            file_put_contents($meta, json_encode(['next' => $index + 1]));

            return response()->json(['ok' => true, 'done' => false]);
        }

        @unlink($meta);

        try {
            $item = $this->finalizeUpload($request, $user, $part);
        } finally {
            @unlink($part);
        }

        return response()->json(['ok' => true, 'done' => true, 'item' => $this->drive->serializeMany(collect([$item->load('owner')]), $user, null, true)[0]]);
    }

    /* ------------------------------------------------------------------ */
    /* Update                                                              */
    /* ------------------------------------------------------------------ */

    public function rename(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:250']);
        $user = $request->user();
        $item = $this->editableItem($uuid, $user);
        $old = $item->name;
        $name = $this->drive->cleanName($request->input('name'));
        if (! $item->isFolder()) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $item->extension = $ext !== '' ? $ext : $item->extension;
        }
        $item->name = $this->drive->uniqueName($item->owner_id, $item->parent_id, $name, $item->type, $item->id);
        $item->updated_by = $user->id;
        $item->save();
        $this->drive->log($item, $user, 'renamed', $old.' → '.$item->name);

        return response()->json(['ok' => true, 'name' => $item->name]);
    }

    public function updateMeta(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => 'nullable|string|max:2000',
        ]);
        $item = $this->editableItem($uuid, $request->user());
        if ($request->has('color')) {
            $item->color = $request->input('color');
        }
        if ($request->has('description')) {
            $item->description = $request->input('description');
        }
        $item->save();

        return response()->json(['ok' => true]);
    }

    public function move(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'string', 'target' => 'nullable|string']);
        $user = $request->user();
        $target = $request->filled('target') ? $this->findItem((string) $request->input('target')) : null;
        if ($target) {
            abort_unless($target->isFolder() && $this->drive->canEditRole($this->drive->roleFor($target, $user)), 403, 'You cannot add items to that folder.');
        }

        $moved = 0;
        $skipped = [];
        foreach ($this->findItems($request->input('ids')) as $item) {
            $role = $this->drive->roleFor($item, $user);
            $targetOwner = $target ? (int) $target->owner_id : (int) $user->id;
            $reason = null;
            if (! $this->drive->canEditRole($role)) {
                $reason = 'no edit access';
            } elseif ((int) $item->owner_id !== $targetOwner) {
                $reason = "can't move between different owners' drives";
            } elseif ($target && ($target->id === $item->id || $this->drive->isInside($target, $item))) {
                $reason = 'cannot move a folder into itself';
            } elseif ($role !== 'owner' && ! $this->parentEditable($item, $user)) {
                $reason = 'only the owner can move it out of this folder';
            }
            if ($reason) {
                $skipped[] = $item->name.' ('.$reason.')';

                continue;
            }
            if ((int) $item->parent_id === (int) $target?->id) {
                continue;
            }
            $item->parent_id = $target?->id;
            $item->name = $this->drive->uniqueName($item->owner_id, $target?->id, $item->name, $item->type, $item->id);
            $item->updated_by = $user->id;
            $item->save();
            $this->drive->log($item, $user, 'moved', 'Moved to '.($target?->name ?? 'My Drive'));
            $moved++;
        }

        return response()->json(['ok' => true, 'moved' => $moved, 'skipped' => $skipped]);
    }

    public function copy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'string', 'target' => 'nullable|string']);
        $user = $request->user();
        $explicitTarget = $request->filled('target') ? $this->editableParent((string) $request->input('target'), $user) : null;

        $copied = 0;
        foreach ($this->findItems($request->input('ids')) as $item) {
            abort_unless($this->drive->roleFor($item, $user), 404);
            $target = $explicitTarget;
            if (! $request->filled('target') && $item->parent_id) {
                $parent = DriveItem::find($item->parent_id);
                $target = ($parent && $this->drive->canEditRole($this->drive->roleFor($parent, $user))) ? $parent : null;
            }
            $copy = $this->drive->copy($item, $target, $user);
            $this->drive->log($copy, $user, 'created', 'Copied from '.$item->name);
            $copied++;
        }

        return response()->json(['ok' => true, 'copied' => $copied]);
    }

    public function star(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'starred' => 'required|boolean']);
        $user = $request->user();
        foreach ($this->findItems($request->input('ids')) as $item) {
            abort_unless($this->drive->roleFor($item, $user), 404);
            if ($request->boolean('starred')) {
                DB::table('drive_stars')->insertOrIgnore(['user_id' => $user->id, 'item_id' => $item->id, 'created_at' => now()]);
            } else {
                DB::table('drive_stars')->where('user_id', $user->id)->where('item_id', $item->id)->delete();
            }
        }

        return response()->json(['ok' => true]);
    }

    public function trash(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1']);
        $user = $request->user();
        $done = 0;
        $denied = [];
        foreach ($this->findItems($request->input('ids')) as $item) {
            if ((int) $item->owner_id !== (int) $user->id) {
                $denied[] = $item->name;

                continue;
            }
            $this->drive->trash($item);
            $this->drive->log($item, $user, 'trashed');
            $done++;
        }

        return response()->json(['ok' => true, 'trashed' => $done, 'denied' => $denied]);
    }

    public function restore(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1']);
        $user = $request->user();
        foreach ($this->findItems($request->input('ids')) as $item) {
            abort_unless((int) $item->owner_id === (int) $user->id, 403);
            $this->drive->restore($item);
            $this->drive->log($item, $user, 'restored');
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1']);
        $user = $request->user();
        foreach ($this->findItems($request->input('ids')) as $item) {
            abort_unless((int) $item->owner_id === (int) $user->id && $item->trashed_at, 403, 'Only the owner can delete, and only from Trash.');
            $this->drive->deleteForever($item);
        }

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(Request $request): JsonResponse
    {
        $user = $request->user();
        DriveItem::where('owner_id', $user->id)->whereNotNull('trashed_at')->whereColumn('trashed_root_id', 'id')
            ->get()->each(fn ($item) => $this->drive->deleteForever($item));

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* Text editing                                                        */
    /* ------------------------------------------------------------------ */

    public function content(Request $request, string $uuid): JsonResponse
    {
        $item = $this->findItem($uuid);
        $role = $this->drive->roleFor($item, $request->user());
        abort_unless($role && ! $item->isFolder(), 404);
        abort_if($item->size > DriveService::MAX_TEXT_EDIT_BYTES, 422, 'File is too large to edit here.');
        $abs = $this->drive->absolutePath($item->disk, $item->path);

        return response()->json(['content' => $abs ? (string) file_get_contents($abs) : '', 'can_edit' => $this->drive->canEditRole($role)]);
    }

    public function saveContent(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['content' => 'nullable|string|max:'.DriveService::MAX_TEXT_EDIT_BYTES]);
        $user = $request->user();
        $item = $this->editableItem($uuid, $user);
        abort_if($item->isFolder() || ! in_array(strtolower((string) $item->extension), DriveService::TEXT_EXTENSIONS, true), 422, 'This file type cannot be edited as text.');

        $content = (string) $request->input('content', '');
        [$disk, $path] = $this->drive->storeContents($content, $item->owner_id, (string) $item->extension);
        $this->drive->replaceContent($item, $disk, $path, strlen($content), (string) $item->mime_type, $user);
        $this->drive->log($item, $user, 'edited', 'Content saved');

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* Sharing                                                             */
    /* ------------------------------------------------------------------ */

    public function users(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $users = User::query()
            ->where(fn ($w) => $w->where('is_active', 1)->orWhereNull('is_active'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    public function share(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'emails' => 'required|array|min:1|max:50',
            'emails.*' => 'required|email:rfc|max:191',
            'role' => 'required|in:viewer,editor',
            'notify' => 'nullable|boolean',
            'message' => 'nullable|string|max:1000',
        ]);
        $user = $request->user();
        $item = $this->editableItem($uuid, $user);
        $isOwner = (int) $item->owner_id === (int) $user->id;

        $added = [];
        foreach (array_unique(array_map(fn ($e) => strtolower(trim($e)), $request->input('emails'))) as $email) {
            if ($item->owner && strtolower((string) $item->owner->email) === $email) {
                continue;
            }
            $target = User::whereRaw('LOWER(email) = ?', [$email])->first();
            $existing = DriveShare::where('item_id', $item->id)->where('email', $email)->first();
            if ($existing) {
                // Only the owner may lower someone's access.
                if ($isOwner || $request->input('role') === 'editor') {
                    $existing->update(['role' => $request->input('role'), 'user_id' => $target?->id]);
                }

                continue;
            }
            DriveShare::create([
                'item_id' => $item->id,
                'user_id' => $target?->id,
                'email' => $email,
                'role' => $request->input('role'),
                'shared_by' => $user->id,
            ]);
            $added[] = $email;
        }

        if ($added) {
            $this->drive->log($item, $user, 'shared', 'Shared with '.implode(', ', $added).' as '.$request->input('role'));
            if ($request->boolean('notify', true)) {
                $this->notifyShare($item, $user, $added, (string) $request->input('role'), (string) $request->input('message', ''));
            }
        }

        return response()->json(['ok' => true, 'added' => $added]);
    }

    public function updateShare(Request $request, string $uuid, int $share): JsonResponse
    {
        $request->validate(['role' => 'required|in:viewer,editor']);
        $user = $request->user();
        $item = $this->findItem($uuid);
        abort_unless((int) $item->owner_id === (int) $user->id, 403, 'Only the owner can change access.');
        $row = DriveShare::where('item_id', $item->id)->findOrFail($share);
        $row->update(['role' => $request->input('role')]);
        $this->drive->log($item, $user, 'access changed', $row->email.' is now '.$row->role);

        return response()->json(['ok' => true]);
    }

    public function removeShare(Request $request, string $uuid, int $share): JsonResponse
    {
        $user = $request->user();
        $item = $this->findItem($uuid);
        abort_unless((int) $item->owner_id === (int) $user->id, 403, 'Only the owner can remove access.');
        $row = DriveShare::where('item_id', $item->id)->findOrFail($share);
        $row->delete();
        $this->drive->log($item, $user, 'access removed', $row->email);

        return response()->json(['ok' => true]);
    }

    public function linkAccess(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['access' => 'required|in:none,view', 'regenerate' => 'nullable|boolean']);
        $user = $request->user();
        $item = $this->editableItem($uuid, $user);
        $isOwner = (int) $item->owner_id === (int) $user->id;
        abort_if(! $isOwner && ($request->input('access') === 'none' || $request->boolean('regenerate')), 403, 'Only the owner can turn off or reset the link.');

        if ($request->boolean('regenerate')) {
            $item->share_token = null;
        }
        $item->link_access = $request->input('access');
        $item->save();
        if ($item->link_access === 'view') {
            $this->drive->ensureShareToken($item);
        }
        $this->drive->log($item, $user, 'link', $item->link_access === 'view' ? 'Anyone with the link can view' : 'Link disabled');

        return response()->json([
            'ok' => true,
            'link_access' => $item->link_access,
            'public_url' => $item->link_access === 'view' ? $this->drive->publicPageUrl($item) : null,
            'direct_url' => $item->link_access === 'view' ? $this->drive->publicRawUrl($item) : null,
        ]);
    }

    /** Turns on the public link for many files at once and returns their direct URLs (for listings). */
    public function bulkLinks(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1|max:500']);
        $user = $request->user();
        $links = [];
        foreach ($this->findItems($request->input('ids')) as $item) {
            if ($item->isFolder() || ! $this->drive->canEditRole($this->drive->roleFor($item, $user))) {
                continue;
            }
            if ($item->link_access !== 'view') {
                $item->update(['link_access' => 'view']);
                $this->drive->log($item, $user, 'link', 'Anyone with the link can view');
            }
            $this->drive->ensureShareToken($item);
            $links[] = ['name' => $item->name, 'url' => $this->drive->publicRawUrl($item)];
        }

        return response()->json(['ok' => true, 'links' => $links]);
    }

    public function restoreVersion(Request $request, string $uuid, int $version): JsonResponse
    {
        $user = $request->user();
        $item = $this->editableItem($uuid, $user);
        $v = DriveItemVersion::where('item_id', $item->id)->findOrFail($version);
        $src = $this->drive->absolutePath($v->disk, $v->path);
        abort_unless($src, 404, 'Version file is missing.');

        $relative = 'drive/'.$item->owner_id.'/'.date('Y/m').'/'.Str::uuid().($item->extension ? '.'.$item->extension : '');
        Storage::disk(DriveService::DISK)->makeDirectory(dirname($relative));
        copy($src, Storage::disk(DriveService::DISK)->path($relative));
        $this->drive->replaceContent($item, DriveService::DISK, $relative, (int) $v->size, (string) $v->mime_type, $user);
        $this->drive->log($item, $user, 'version restored', 'Restored version from '.$v->created_at?->format('M j, Y g:i A'));

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* Downloads                                                           */
    /* ------------------------------------------------------------------ */

    public function file(Request $request, string $uuid)
    {
        $item = $this->findItem($uuid);
        abort_unless(! $item->isFolder() && $this->drive->roleFor($item, $request->user()), 404);

        return $this->streamItem($item->disk, $item->path, $item->name, $item->mime_type, $request->boolean('download'));
    }

    public function versionFile(Request $request, string $uuid, int $version)
    {
        $item = $this->findItem($uuid);
        abort_unless($this->drive->roleFor($item, $request->user()), 404);
        $v = DriveItemVersion::where('item_id', $item->id)->findOrFail($version);

        return $this->streamItem($v->disk, $v->path, $v->name, $v->mime_type, true);
    }

    public function zip(Request $request)
    {
        $ids = (array) $request->query('ids', []);
        abort_if(! $ids, 422);
        $user = $request->user();
        $items = $this->findItems($ids)->filter(fn ($i) => $this->drive->roleFor($i, $user))->values();
        abort_if($items->isEmpty(), 404);

        return $this->zipResponse($items, $items->count() === 1 ? $items[0]->name : '5Core Drive');
    }

    /* ------------------------------------------------------------------ */
    /* Public links (no login)                                             */
    /* ------------------------------------------------------------------ */

    public function publicShow(string $token)
    {
        $item = $this->publicItem($token);
        $children = collect();
        $folder = $item;
        $crumbs = [];
        if ($item->isFolder()) {
            if (request()->filled('f')) {
                $sub = DriveItem::where('uuid', (string) request('f'))->whereNull('trashed_at')->first();
                if ($sub && $sub->isFolder() && $this->drive->isInside($sub, $item)) {
                    $folder = $sub;
                }
            }
            $children = DriveItem::where('parent_id', $folder->id)->whereNull('trashed_at')
                ->orderByRaw("type = 'folder' desc")->orderBy('name')->get();
            if ($folder->id !== $item->id) {
                $chain = array_merge($this->drive->ancestors($folder), [$folder]);
                $started = false;
                foreach ($chain as $c) {
                    if ($c->id === $item->id) {
                        $started = true;
                    }
                    if ($started) {
                        $crumbs[] = $c;
                    }
                }
            }
        }

        return view('drive.public', [
            'item' => $item,
            'folder' => $folder,
            'children' => $children,
            'crumbs' => $crumbs,
            'token' => $token,
            'rawUrl' => $this->drive->publicRawUrl($item),
            'drive' => $this->drive,
        ]);
    }

    public function publicRaw(string $token)
    {
        $item = $this->publicItem($token);
        abort_if($item->isFolder(), 404);

        return $this->streamItem($item->disk, $item->path, $item->name, $item->mime_type, request()->boolean('download'), true);
    }

    public function publicChild(string $token, string $uuid)
    {
        $root = $this->publicItem($token);
        $child = DriveItem::where('uuid', $uuid)->whereNull('trashed_at')->firstOrFail();
        abort_unless($root->isFolder() && ! $child->isFolder() && $this->drive->isInside($child, $root), 404);

        return $this->streamItem($child->disk, $child->path, $child->name, $child->mime_type, request()->boolean('download'), true);
    }

    public function publicZip(string $token)
    {
        $item = $this->publicItem($token);
        if (! $item->isFolder()) {
            return $this->streamItem($item->disk, $item->path, $item->name, $item->mime_type, true, true);
        }

        return $this->zipResponse(collect([$item]), $item->name);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function findItem(string $uuid): DriveItem
    {
        return DriveItem::where('uuid', $uuid)->firstOrFail();
    }

    private function findItems(array $uuids)
    {
        return DriveItem::with('owner:id,name,email')->whereIn('uuid', array_map('strval', $uuids))->get();
    }

    private function editableItem(string $uuid, User $user): DriveItem
    {
        $item = $this->findItem($uuid);
        abort_unless($this->drive->canEditRole($this->drive->roleFor($item, $user)), 403, 'You only have view access to this item.');
        abort_if($this->drive->isTrashedChain($item), 422, 'Restore the item from Trash first.');

        return $item;
    }

    private function editableParent(?string $uuid, User $user): ?DriveItem
    {
        if (! $uuid) {
            return null;
        }
        $parent = $this->findItem($uuid);
        abort_unless($parent->isFolder() && $this->drive->canEditRole($this->drive->roleFor($parent, $user)), 403, 'You only have view access to this folder.');
        abort_if($this->drive->isTrashedChain($parent), 422, 'That folder is in Trash.');

        return $parent;
    }

    private function parentEditable(DriveItem $item, User $user): bool
    {
        if (! $item->parent_id) {
            return (int) $item->owner_id === (int) $user->id;
        }
        $parent = DriveItem::find($item->parent_id);

        return $parent && $this->drive->canEditRole($this->drive->roleFor($parent, $user));
    }

    /** @return int[] */
    private function sharedWithMeIds(User $user): array
    {
        return DriveShare::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('email', strtolower((string) $user->email)))
            ->pluck('item_id')->unique()->values()->all();
    }

    /** @return array{0:?DriveItem,1:?DriveItem} [parent, replaceTarget] */
    private function resolveUploadTarget(Request $request, User $user): array
    {
        if ($request->filled('replace')) {
            $target = $this->editableItem((string) $request->input('replace'), $user);
            abort_if($target->isFolder(), 422);

            return [null, $target];
        }

        return [$this->editableParent($request->input('parent'), $user), null];
    }

    private function finalizeUpload(Request $request, User $user, string $part): DriveItem
    {
        [$parent, $replaceTarget] = $this->resolveUploadTarget($request, $user);
        $name = $this->drive->cleanName(basename(str_replace('\\', '/', (string) $request->input('name'))));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = (int) filesize($part);

        $relativeDir = trim(dirname(str_replace('\\', '/', (string) $request->input('relative_path', ''))), './');
        if (! $replaceTarget && $relativeDir !== '') {
            $parent = $this->drive->ensureFolderPath($parent, $relativeDir, $user);
        }
        $ownerId = $parent ? (int) $parent->owner_id : (int) $user->id;

        if (! $replaceTarget && $request->input('conflict') === 'replace') {
            $replaceTarget = $this->drive->findSibling($ownerId, $parent?->id, $name, 'file');
        }

        $mime = $this->drive->detectMime($part, $ext);

        if ($replaceTarget) {
            [$disk, $path] = $this->drive->storeLocalFile($part, (int) $replaceTarget->owner_id, (string) ($replaceTarget->extension ?: $ext));
            $this->drive->replaceContent($replaceTarget, $disk, $path, $size, $mime, $user);
            $this->drive->log($replaceTarget, $user, 'new version', 'Uploaded '.$name);

            return $replaceTarget->refresh();
        }

        [$disk, $path] = $this->drive->storeLocalFile($part, $ownerId, $ext);
        $item = DriveItem::create([
            'owner_id' => $ownerId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'parent_id' => $parent?->id,
            'type' => 'file',
            'name' => $this->drive->uniqueName($ownerId, $parent?->id, $name, 'file'),
            'extension' => $ext !== '' ? $ext : null,
            'mime_type' => $mime,
            'size' => $size,
            'disk' => $disk,
            'path' => $path,
        ]);
        $this->drive->log($item, $user, 'uploaded');

        return $item;
    }

    private function publicItem(string $token): DriveItem
    {
        $item = DriveItem::with('owner:id,name')->where('share_token', $token)->where('link_access', 'view')->firstOrFail();
        abort_if($this->drive->isTrashedChain($item), 404);

        return $item;
    }

    private function streamItem(?string $disk, ?string $path, string $name, ?string $mime, bool $download, bool $public = false): BinaryFileResponse
    {
        $abs = $this->drive->absolutePath($disk, $path);
        abort_unless($abs, 404, 'File is missing from storage.');

        $inline = ! $download && $this->drive->isInlineSafe($mime);
        $headers = [
            'Content-Type' => $inline ? ($mime ?: 'application/octet-stream') : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $public ? 'public, max-age=86400' : 'private, max-age=3600',
        ];
        // Chrome's PDF viewer will not render under a sandbox CSP.
        if (strtolower((string) $mime) !== 'application/pdf') {
            $headers['Content-Security-Policy'] = 'sandbox';
        }
        $response = response()->file($abs, $headers);
        if ($public) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
        $fallback = preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($name)) ?: 'file';
        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $name,
            $fallback
        );

        return $response;
    }

    private function zipResponse($items, string $baseName)
    {
        abort_unless(class_exists(\ZipArchive::class), 500, 'Zip support is not available on this server.');
        $tmp = tempnam(sys_get_temp_dir(), 'drivezip');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $count = 0;
        $add = function (DriveItem $item, string $prefix, int $depth) use (&$add, $zip, &$count) {
            if ($item->trashed_at || $depth > 30) {
                return;
            }
            if ($item->isFolder()) {
                $zip->addEmptyDir($prefix.$item->name);
                foreach ($item->children()->whereNull('trashed_at')->get() as $child) {
                    $add($child, $prefix.$item->name.'/', $depth + 1);
                }

                return;
            }
            $abs = $this->drive->absolutePath($item->disk, $item->path);
            if ($abs) {
                $zip->addFile($abs, $prefix.$item->name);
                $count++;
            }
        };
        foreach ($items as $item) {
            $add($item, '', 0);
        }
        $zip->close();

        $fallback = preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($baseName)) ?: 'download';

        return response()->download($tmp, $baseName.'.zip', ['Content-Type' => 'application/zip'])
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $baseName.'.zip', $fallback.'.zip')
            ->deleteFileAfterSend(true);
    }

    private function notifyShare(DriveItem $item, User $sharer, array $emails, string $role, string $message): void
    {
        $link = route('drive.index').'#/item/'.$item->uuid;
        $kind = $item->isFolder() ? 'folder' : 'file';
        $body = "{$sharer->name} shared the {$kind} \"{$item->name}\" with you on 5Core Drive ({$role} access).\n\n"
            .($message !== '' ? "Message: {$message}\n\n" : '')
            ."Open it here: {$link}\n";

        foreach ($emails as $email) {
            try {
                Mail::raw($body, function ($m) use ($email, $sharer, $item) {
                    $m->to($email)->subject($sharer->name.' shared "'.$item->name.'" with you');
                });
            } catch (\Throwable $e) {
                Log::warning('Drive share email failed', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }
    }

    private function chunkSize(): int
    {
        $toBytes = function ($v) {
            $v = trim((string) $v);
            if ($v === '' || $v === '0' || $v === '-1') {
                return PHP_INT_MAX;
            }
            $n = (float) $v;

            return (int) match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3,
                'm' => $n * 1024 ** 2,
                'k' => $n * 1024,
                default => $n,
            };
        };
        $limit = min($toBytes(ini_get('upload_max_filesize')), $toBytes(ini_get('post_max_size')));

        return (int) max(256 * 1024, min(8 * 1024 * 1024, $limit - 128 * 1024));
    }
}
