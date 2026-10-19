<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use HasFactory, HasTags, Publishable, ResolvesByIdOrKey, SoftDeletes, Translatable;

    protected string $translationModel = BlogPostTranslation::class;
    protected string $translationForeignKey = 'post_id';

    protected $fillable = ['category_id', 'author_id', 'slug', 'cover_path', 'cover_meta', 'published_at', 'is_featured', 'view_count', 'status'];

    protected $casts = ['published_at' => 'datetime', 'is_featured' => 'boolean', 'cover_meta' => 'array'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Đã công bố và tới ngày đăng. */
    public function scopeVisible($query)
    {
        return $query->published()->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function getTitleAttribute(): string
    {
        return $this->tr('title') ?? $this->slug;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return storage_url($this->cover_path);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
