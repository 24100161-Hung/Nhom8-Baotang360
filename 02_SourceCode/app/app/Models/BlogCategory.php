<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    use Translatable;

    public const CODES = ['kham_pha', 'su_kien', 'khoa_hoc'];

    protected string $translationModel = BlogCategoryTranslation::class;
    protected string $translationForeignKey = 'category_id';

    protected $fillable = ['code', 'slug', 'sort_order'];

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }
}
