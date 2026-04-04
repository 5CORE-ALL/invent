<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyHtmlDescriptionTemplate extends Model
{
    protected $table = 'shopify_html_description_templates';

    protected $fillable = [
        'name',
        'html_content',
    ];
}
