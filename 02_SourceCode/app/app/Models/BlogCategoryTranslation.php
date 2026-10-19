<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategoryTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['category_id', 'locale', 'name'];
}
