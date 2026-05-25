<?php

namespace App\Http\Controllers;

use App\Models\DriverData;
use App\Models\DriverFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourcesController extends Controller
{
    public function index(Request $request)
    {
        $folderId = (int) $request->query('folder', 0);
        $folders = DriverFolder::query()
            ->where('parent_id', $folderId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $breadcrumbs = $this->breadcrumbs($folderId);
        $folderTree = $this->folderTreeData($folderId);

        return view('resources.index', compact('folderId', 'folders', 'breadcrumbs', 'folderTree'));
    }

    public function data(Request $request)
    {
        $folderId = (int) $request->query('folder', 0);
        $search = trim((string) $request->query('search', ''));

        $query = DriverData::query()
            ->with('creator:id,name')
            ->where('folder_id', $folderId);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('file_name', 'like', '%'.$search.'%')
                    ->orWhere('location_url', 'like', '%'.$search.'%');
            });
        }

        $type = $request->query('type');
        if ($type && $type !== 'all') {
            $query->where('file_type', $type);
        }

        $items = $query->latest('updated_at')->paginate((int) $request->query('per_page', 24));

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
                'per_page' => $items->perPage(),
            ],
        ]);
    }

    public function storeLink(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'location_url' => 'required|url|max:2000',
            'folder_id' => 'nullable|integer|min:0',
        ]);

        $item = DriverData::create([
            'uuid' => (string) Str::uuid(),
            'title' => $validated['title'],
            'location_url' => $validated['location_url'],
            'folder_id' => (int) ($validated['folder_id'] ?? 0),
            'file_type' => 'link',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'item' => $item->load('creator:id,name')]);
    }

    public function storeFile(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:51200',
            'folder_id' => 'nullable|integer|min:0',
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
            'file_name' => $uploaded->getClientOriginalName(),
            'file_data' => $storedName,
            'file_size' => (string) $uploaded->getSize(),
            'file_extension' => $extension,
            'file_type' => $this->detectFileType($extension),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'item' => $item->load('creator:id,name')]);
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
        ]);

        $driverData->update([
            'title' => $validated['title'],
            'location_url' => $validated['location_url'] ?? $driverData->location_url,
            'folder_id' => (int) ($validated['folder_id'] ?? $driverData->folder_id),
        ]);

        return response()->json(['success' => true, 'item' => $driverData->fresh()->load('creator:id,name')]);
    }

    public function updateFolder(Request $request, DriverFolder $folder)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder->update(['name' => $validated['name']]);

        return response()->json(['success' => true, 'folder' => $folder]);
    }

    public function destroy(DriverData $driverData)
    {
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
