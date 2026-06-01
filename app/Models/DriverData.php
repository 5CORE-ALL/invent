<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DriverData extends Model
{
    protected $table = 'driver_data';

    protected $fillable = [
        'uuid',
        'title',
        'location_url',
        'folder_id',
        'department_id',
        'tag',
        'resource',
        'file_name',
        'file_data',
        'file_size',
        'file_extension',
        'file_type',
        'created_by',
    ];

    protected $casts = [
        'folder_id' => 'integer',
        'department_id' => 'integer',
    ];

    protected $appends = ['icon', 'is_link', 'open_url', 'department_name'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DriverFolder::class, 'folder_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(ResourceDepartment::class, 'department_id');
    }

    public function getDepartmentNameAttribute(): ?string
    {
        return $this->department?->name;
    }

    public function getIsLinkAttribute(): bool
    {
        return in_array($this->file_type, ['link', 'gsheet'], true) && filled($this->location_url);
    }

    public function getOpenUrlAttribute(): ?string
    {
        if ($this->is_link) {
            return $this->location_url;
        }

        if ($this->file_data) {
            return route('resources.download', $this->id);
        }

        return null;
    }

    public function getIconAttribute(): string
    {
        return match ($this->file_type) {
            'link' => 'ri-links-line',
            'gsheet' => 'ri-google-line',
            'spreadsheet' => 'ri-file-excel-2-line',
            'image' => 'ri-image-line',
            'pdf' => 'ri-file-pdf-line',
            'video' => 'ri-video-line',
            'document' => 'ri-file-word-line',
            default => 'ri-file-line',
        };
    }

    public function storagePath(): ?string
    {
        if (! $this->file_data) {
            return null;
        }

        return 'driver-files/'.$this->file_data;
    }

    public function deleteStoredFile(): void
    {
        if ($path = $this->storagePath()) {
            Storage::disk('local')->delete($path);
        }
    }
}
