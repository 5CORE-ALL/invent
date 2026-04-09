<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class ResourceMaster extends Model
{
    use SoftDeletes;

    protected $table = 'resources_master';

    protected $fillable = [
        'title',
        'description',
        'category',
        'file_type',
        'mime_type',
        'file_path',
        'file_size',
        'original_filename',
        'external_link',
        'thumbnail_path',
        'uploaded_by',
        'status',
        'version',
        'duration_seconds',
        'watch_count',
        'download_count',
        'checklist_schema',
        'allow_completed_upload',
        'metadata',
    ];

    protected $casts = [
        'checklist_schema' => 'array',
        'metadata' => 'array',
        'allow_completed_upload' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(ResourceDepartment::class, 'resource_department_map', 'resource_id', 'department_id')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ResourceTag::class, 'resource_tag_map', 'resource_id', 'resource_tag_id')
            ->withTimestamps();
    }

    public function isVisibleToUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $managers = array_map('strtolower', config('resources_master.manager_emails', []));
        if (in_array(strtolower((string) $user->email), $managers, true)) {
            return true;
        }

        $deptIds = $this->departments()->pluck('resource_departments.id');
        if ($deptIds->isEmpty()) {
            return true;
        }

        if (! $user->resource_department_id) {
            return false;
        }

        return $deptIds->contains($user->resource_department_id);
    }

    public function isLinkOnly(): bool
    {
        return $this->file_type === 'link' || ($this->external_link && ! $this->file_path);
    }

    public function diskPath(): ?string
    {
        return $this->file_path;
    }

    public static function sanitizeFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $base);
        $base = trim($base, '-');
        $ext = preg_replace('/[^a-zA-Z0-9]+/', '', $ext);

        return ($base !== '' ? $base : 'file').($ext !== '' ? '.'.$ext : '');
    }
}
