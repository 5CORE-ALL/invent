<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvrRemark extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'remark',
        'user_id',
        'is_solved',
    ];

    protected $casts = [
        'is_solved' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this remark
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
