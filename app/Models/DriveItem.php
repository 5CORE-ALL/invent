<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DriveItem extends Model
{
    protected $table = 'drive_items';

    protected $fillable = [
        'uuid',
        'owner_id',
        'created_by',
        'parent_id',
        'type',
        'name',
        'extension',
        'mime_type',
        'size',
        'disk',
        'path',
        'color',
        'description',
        'share_token',
        'link_access',
        'trashed_at',
        'trashed_root_id',
        'updated_by',
    ];

    protected $casts = [
        'size' => 'integer',
        'trashed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DriveItem $item) {
            if (! $item->uuid) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent()
    {
        return $this->belongsTo(DriveItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(DriveItem::class, 'parent_id');
    }

    public function shares()
    {
        return $this->hasMany(DriveShare::class, 'item_id');
    }

    public function versions()
    {
        return $this->hasMany(DriveItemVersion::class, 'item_id')->latest('id');
    }

    public function activities()
    {
        return $this->hasMany(DriveActivity::class, 'item_id')->latest('id');
    }

    /** Category used by the UI for icons and type filters. */
    public function category(): string
    {
        if ($this->isFolder()) {
            return 'folder';
        }

        return self::categoryFor((string) $this->mime_type, (string) $this->extension);
    }

    public static function categoryFor(string $mime, string $ext): string
    {
        $ext = strtolower($ext);
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return 'pdf';
        }
        if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods', 'tsv'], true)) {
            return 'sheet';
        }
        if (in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true)) {
            return 'doc';
        }
        if (in_array($ext, ['ppt', 'pptx', 'odp', 'key'], true)) {
            return 'slide';
        }
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'], true)) {
            return 'archive';
        }
        if (in_array($ext, ['txt', 'md', 'json', 'xml', 'html', 'htm', 'css', 'js', 'php', 'log', 'yml', 'yaml', 'ini', 'sql'], true)
            || str_starts_with($mime, 'text/')) {
            return 'text';
        }

        return 'other';
    }
}
