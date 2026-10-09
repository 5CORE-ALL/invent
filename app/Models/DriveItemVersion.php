<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveItemVersion extends Model
{
    protected $table = 'drive_item_versions';

    protected $fillable = ['item_id', 'name', 'mime_type', 'size', 'disk', 'path', 'uploaded_by'];

    protected $casts = ['size' => 'integer'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
