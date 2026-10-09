<?php

namespace App\Services\Drive;

use App\Models\DriveActivity;
use App\Models\DriveItem;
use App\Models\DriveItemVersion;
use App\Models\DriveShare;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class DriveService
{
    public const DISK = 'local';

    public const MAX_FILE_BYTES = 5 * 1024 * 1024 * 1024;

    public const MAX_TEXT_EDIT_BYTES = 2 * 1024 * 1024;

    public const TEXT_EXTENSIONS = ['txt', 'md', 'csv', 'tsv', 'json', 'xml', 'html', 'htm', 'css', 'js', 'log', 'yml', 'yaml', 'ini', 'sql'];

    /** Mime types that are safe to render inline in a browser on our origin. */
    private const INLINE_SAFE_PREFIXES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif', 'video/', 'audio/', 'application/pdf', 'text/plain', 'text/csv'];

    private const ROLE_RANK = ['viewer' => 1, 'editor' => 2, 'owner' => 3];

    /* ------------------------------------------------------------------ */
    /* Access                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Effective role of the user on the item: owner | editor | viewer | null.
     * Shares on any ancestor folder apply to everything inside it.
     */
    public function roleFor(DriveItem $item, User $user): ?string
    {
        if ((int) $item->owner_id === (int) $user->id) {
            return 'owner';
        }

        $chainIds = array_merge([$item->id], array_map(fn ($a) => $a->id, $this->ancestors($item)));

        $roles = DriveShare::query()
            ->whereIn('item_id', $chainIds)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('email', strtolower((string) $user->email));
            })
            ->pluck('role')
            ->all();

        return $this->highestRole($roles);
    }

    public function highestRole(array $roles): ?string
    {
        $best = null;
        foreach ($roles as $role) {
            if ($role && ($best === null || (self::ROLE_RANK[$role] ?? 0) > (self::ROLE_RANK[$best] ?? 0))) {
                $best = $role;
            }
        }

        return $best;
    }

    public function canEditRole(?string $role): bool
    {
        return in_array($role, ['owner', 'editor'], true);
    }

    /** @return DriveItem[] root first, direct parent last */
    public function ancestors(DriveItem $item): array
    {
        $chain = [];
        $parentId = $item->parent_id;
        $guard = 0;
        while ($parentId && $guard++ < 64) {
            $parent = DriveItem::find($parentId);
            if (! $parent) {
                break;
            }
            array_unshift($chain, $parent);
            $parentId = $parent->parent_id;
        }

        return $chain;
    }

    /** @return int[] ids of every item below the folder */
    public function descendantIds(DriveItem $folder): array
    {
        $all = [];
        $frontier = [$folder->id];
        $guard = 0;
        while ($frontier && $guard++ < 64) {
            $children = DriveItem::whereIn('parent_id', $frontier)->pluck('id')->all();
            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return $all;
    }

    public function isInside(DriveItem $item, DriveItem $folder): bool
    {
        foreach ($this->ancestors($item) as $ancestor) {
            if ($ancestor->id === $folder->id) {
                return true;
            }
        }

        return false;
    }

    /** Folder ids under trash (directly or via a trashed ancestor) are hidden; this checks the chain. */
    public function isTrashedChain(DriveItem $item): bool
    {
        if ($item->trashed_at) {
            return true;
        }
        foreach ($this->ancestors($item) as $ancestor) {
            if ($ancestor->trashed_at) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Names / storage                                                     */
    /* ------------------------------------------------------------------ */

    public function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $name);
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name), " .\t");

        return Str::limit($name === '' ? 'Untitled' : $name, 250, '');
    }

    public function uniqueName(int $ownerId, ?int $parentId, string $name, string $type, ?int $exceptId = null): string
    {
        $exists = function (string $candidate) use ($ownerId, $parentId, $type, $exceptId) {
            return DriveItem::where('owner_id', $ownerId)
                ->where('parent_id', $parentId)
                ->where('type', $type)
                ->where('name', $candidate)
                ->whereNull('trashed_at')
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->exists();
        };

        if (! $exists($name)) {
            return $name;
        }

        $ext = $type === 'file' ? pathinfo($name, PATHINFO_EXTENSION) : '';
        $base = $ext !== '' ? substr($name, 0, -(strlen($ext) + 1)) : $name;
        for ($i = 1; $i < 1000; $i++) {
            $candidate = $base.' ('.$i.')'.($ext !== '' ? '.'.$ext : '');
            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        return $base.' ('.Str::random(4).')'.($ext !== '' ? '.'.$ext : '');
    }

    public function findSibling(int $ownerId, ?int $parentId, string $name, string $type): ?DriveItem
    {
        return DriveItem::where('owner_id', $ownerId)
            ->where('parent_id', $parentId)
            ->where('type', $type)
            ->where('name', $name)
            ->whereNull('trashed_at')
            ->first();
    }

    public function detectMime(string $absolutePath, string $extension): string
    {
        $mime = null;
        if (is_file($absolutePath) && function_exists('finfo_open')) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: null;
        }
        if (! $mime || in_array($mime, ['application/octet-stream', 'text/plain', 'application/zip'], true)) {
            $guess = $extension !== '' ? (MimeTypes::getDefault()->getMimeTypes(strtolower($extension))[0] ?? null) : null;
            if ($guess) {
                $mime = $guess;
            }
        }

        return $mime ?: 'application/octet-stream';
    }

    /** Moves a finished local file into drive storage and returns [disk, path]. */
    public function storeLocalFile(string $sourceAbsolute, int $ownerId, string $extension): array
    {
        $relative = 'drive/'.$ownerId.'/'.date('Y/m').'/'.Str::uuid().($extension !== '' ? '.'.strtolower($extension) : '');
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(dirname($relative));
        $target = $disk->path($relative);
        if (! @rename($sourceAbsolute, $target)) {
            copy($sourceAbsolute, $target);
            @unlink($sourceAbsolute);
        }

        return [self::DISK, $relative];
    }

    public function storeContents(string $contents, int $ownerId, string $extension): array
    {
        $relative = 'drive/'.$ownerId.'/'.date('Y/m').'/'.Str::uuid().($extension !== '' ? '.'.strtolower($extension) : '');
        Storage::disk(self::DISK)->put($relative, $contents);

        return [self::DISK, $relative];
    }

    public function absolutePath(?string $disk, ?string $path): ?string
    {
        if (! $disk || ! $path) {
            return null;
        }
        $abs = Storage::disk($disk)->path($path);

        return is_file($abs) ? $abs : null;
    }

    public function isInlineSafe(?string $mime): bool
    {
        $mime = strtolower((string) $mime);
        foreach (self::INLINE_SAFE_PREFIXES as $prefix) {
            if (str_starts_with($mime, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Keeps the current file as a version, then points the item at the new file. */
    public function replaceContent(DriveItem $item, string $disk, string $path, int $size, string $mime, User $user): void
    {
        if ($item->path) {
            DriveItemVersion::create([
                'item_id' => $item->id,
                'name' => $item->name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'disk' => $item->disk,
                'path' => $item->path,
                'uploaded_by' => $item->updated_by ?: $item->created_by,
            ]);
        }

        $item->update([
            'disk' => $disk,
            'path' => $path,
            'size' => $size,
            'mime_type' => $mime,
            'updated_by' => $user->id,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Mutations                                                           */
    /* ------------------------------------------------------------------ */

    public function log(DriveItem $item, ?User $user, string $action, ?string $details = null): void
    {
        DriveActivity::create([
            'item_id' => $item->id,
            'user_id' => $user?->id,
            'action' => $action,
            'details' => $details ? Str::limit($details, 490) : null,
        ]);
    }

    public function trash(DriveItem $item): void
    {
        $now = now();
        DB::transaction(function () use ($item, $now) {
            $item->update(['trashed_at' => $now, 'trashed_root_id' => $item->id]);
            if ($item->isFolder()) {
                $ids = $this->descendantIds($item);
                if ($ids) {
                    DriveItem::whereIn('id', $ids)->whereNull('trashed_at')
                        ->update(['trashed_at' => $now, 'trashed_root_id' => $item->id]);
                }
            }
        });
    }

    public function restore(DriveItem $item): void
    {
        DB::transaction(function () use ($item) {
            DriveItem::where('trashed_root_id', $item->id)->update(['trashed_at' => null, 'trashed_root_id' => null]);
            $item->refresh();

            $parent = $item->parent_id ? DriveItem::find($item->parent_id) : null;
            $parentId = ($parent && ! $this->isTrashedChain($parent) && (int) $parent->owner_id === (int) $item->owner_id) ? $parent->id : null;
            $item->update([
                'parent_id' => $parentId,
                'name' => $this->uniqueName($item->owner_id, $parentId, $item->name, $item->type, $item->id),
                'trashed_at' => null,
                'trashed_root_id' => null,
            ]);
        });
    }

    public function deleteForever(DriveItem $item): void
    {
        $ids = array_merge([$item->id], $item->isFolder() ? $this->descendantIds($item) : []);

        foreach (array_chunk($ids, 500) as $chunk) {
            $files = DriveItem::whereIn('id', $chunk)->whereNotNull('path')->get(['disk', 'path']);
            $versions = DriveItemVersion::whereIn('item_id', $chunk)->get(['disk', 'path']);
            foreach ($files->concat($versions) as $f) {
                if ($f->disk && $f->path) {
                    Storage::disk($f->disk)->delete($f->path);
                }
            }
            DriveItemVersion::whereIn('item_id', $chunk)->delete();
            DriveShare::whereIn('item_id', $chunk)->delete();
            DB::table('drive_stars')->whereIn('item_id', $chunk)->delete();
            DriveActivity::whereIn('item_id', $chunk)->delete();
            DriveItem::whereIn('id', $chunk)->delete();
        }
    }

    public function copy(DriveItem $item, ?DriveItem $target, User $user, int $depth = 0): DriveItem
    {
        $ownerId = $target ? (int) $target->owner_id : (int) $user->id;
        $parentId = $target?->id;
        $name = $depth === 0
            ? $this->uniqueName($ownerId, $parentId, ($item->isFolder() ? $item->name : 'Copy of '.$item->name), $item->type)
            : $item->name;

        $attrs = [
            'owner_id' => $ownerId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'parent_id' => $parentId,
            'type' => $item->type,
            'name' => $name,
            'extension' => $item->extension,
            'mime_type' => $item->mime_type,
            'size' => $item->size,
            'color' => $item->color,
            'description' => $item->description,
        ];

        if (! $item->isFolder()) {
            $src = $this->absolutePath($item->disk, $item->path);
            if ($src) {
                $relative = 'drive/'.$ownerId.'/'.date('Y/m').'/'.Str::uuid().($item->extension ? '.'.$item->extension : '');
                $disk = Storage::disk(self::DISK);
                $disk->makeDirectory(dirname($relative));
                copy($src, $disk->path($relative));
                $attrs['disk'] = self::DISK;
                $attrs['path'] = $relative;
            }
        }

        $copy = DriveItem::create($attrs);

        if ($item->isFolder() && $depth < 30) {
            foreach ($item->children()->whereNull('trashed_at')->get() as $child) {
                $this->copy($child, $copy, $user, $depth + 1);
            }
        }

        return $copy;
    }

    /** Finds or creates nested folders from "a/b/c" below $parent; returns deepest folder (or $parent). */
    public function ensureFolderPath(?DriveItem $parent, string $relativeDir, User $user): ?DriveItem
    {
        $segments = array_values(array_filter(array_map(fn ($s) => $this->cleanName($s), preg_split('#[\\\\/]+#', $relativeDir)), fn ($s) => $s !== ''));
        $current = $parent;
        foreach ($segments as $segment) {
            $ownerId = $current ? (int) $current->owner_id : (int) $user->id;
            $existing = $this->findSibling($ownerId, $current?->id, $segment, 'folder');
            $current = $existing ?: DriveItem::create([
                'owner_id' => $ownerId,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'parent_id' => $current?->id,
                'type' => 'folder',
                'name' => $segment,
            ]);
        }

        return $current;
    }

    /* ------------------------------------------------------------------ */
    /* Presentation                                                        */
    /* ------------------------------------------------------------------ */

    public function ensureShareToken(DriveItem $item): string
    {
        if (! $item->share_token) {
            $item->update(['share_token' => Str::random(40)]);
        }

        return $item->share_token;
    }

    public function publicPageUrl(DriveItem $item): ?string
    {
        return $item->share_token ? route('drive.public.show', $item->share_token) : null;
    }

    public function publicRawUrl(DriveItem $item): ?string
    {
        if (! $item->share_token || $item->isFolder()) {
            return null;
        }

        return route('drive.public.raw', ['token' => $item->share_token, 'filename' => $this->urlFilename($item->name)]);
    }

    public function urlFilename(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return $base.($ext !== '' ? '.'.$ext : '');
    }

    /**
     * @param  array<int,bool>  $starredIds
     * @param  array<int,int>  $shareCounts
     */
    public function serialize(DriveItem $item, ?string $role, array $starredIds = [], array $shareCounts = []): array
    {
        $category = $item->category();
        $canPreviewInline = ! $item->isFolder() && in_array($category, ['image', 'video', 'audio', 'pdf', 'text', 'sheet'], true);

        return [
            'id' => $item->uuid,
            'type' => $item->type,
            'name' => $item->name,
            'extension' => $item->extension,
            'mime' => $item->mime_type,
            'category' => $category,
            'size' => (int) $item->size,
            'color' => $item->color,
            'description' => $item->description,
            'owner' => $item->owner ? ['id' => $item->owner->id, 'name' => $item->owner->name, 'email' => $item->owner->email] : null,
            'role' => $role,
            'can_edit' => $this->canEditRole($role),
            'is_owner' => $role === 'owner',
            'starred' => isset($starredIds[$item->id]),
            'shared' => ($shareCounts[$item->id] ?? 0) > 0 || $item->link_access === 'view',
            'link_access' => $item->link_access,
            'trashed' => (bool) $item->trashed_at,
            'trashed_at' => $item->trashed_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'thumb_url' => (! $item->isFolder() && $category === 'image') ? route('drive.file', $item->uuid) : null,
            'view_url' => $canPreviewInline ? route('drive.file', $item->uuid) : null,
            'download_url' => $item->isFolder() ? route('drive.zip', ['ids' => [$item->uuid]]) : route('drive.file', ['uuid' => $item->uuid, 'download' => 1]),
            'editable_text' => ! $item->isFolder() && in_array(strtolower((string) $item->extension), self::TEXT_EXTENSIONS, true) && $item->size <= self::MAX_TEXT_EDIT_BYTES,
        ];
    }

    /**
     * Serializes a collection, computing roles efficiently.
     * $inheritedRole is the role the user has on the containing folder (if any).
     */
    public function serializeMany(Collection $items, User $user, ?string $inheritedRole = null, bool $resolveEach = false): array
    {
        if ($items->isEmpty()) {
            return [];
        }
        $ids = $items->pluck('id')->all();
        $starred = array_fill_keys(DB::table('drive_stars')->where('user_id', $user->id)->whereIn('item_id', $ids)->pluck('item_id')->all(), true);
        $shareCounts = DriveShare::whereIn('item_id', $ids)->groupBy('item_id')->selectRaw('item_id, count(*) c')->pluck('c', 'item_id')->all();
        $direct = DriveShare::whereIn('item_id', $ids)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('email', strtolower((string) $user->email)))
            ->get(['item_id', 'role'])->groupBy('item_id');

        return $items->map(function (DriveItem $item) use ($user, $inheritedRole, $resolveEach, $starred, $shareCounts, $direct) {
            if ((int) $item->owner_id === (int) $user->id) {
                $role = 'owner';
            } elseif ($resolveEach) {
                $role = $this->roleFor($item, $user);
            } else {
                $role = $this->highestRole(array_merge([$inheritedRole], ($direct[$item->id] ?? collect())->pluck('role')->all()));
            }

            return $this->serialize($item, $role, $starred, $shareCounts);
        })->values()->all();
    }
}
