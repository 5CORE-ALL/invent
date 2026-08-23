<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRawImageAiPrompt extends Model
{
    protected $table = 'product_raw_image_ai_prompts';

    protected $fillable = [
        'kind',
        'prompt',
    ];
}
