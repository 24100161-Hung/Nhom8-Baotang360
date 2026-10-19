<?php

namespace App\Models;

use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    use Translatable, ResolvesByIdOrKey;

    public const GROUPS = ['chat_lieu', 'nguon_goc', 'nhom_sinh_vat', 'chu_de', 'phong'];

    protected string $translationModel = TagTranslation::class;
    protected string $translationForeignKey = 'tag_id';

    protected $fillable = ['slug', 'tag_group', 'parent_id', 'usage_count'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Tag::class, 'parent_id');
    }

    public function artifacts(): MorphToMany
    {
        return $this->morphedByMany(Artifact::class, 'taggable', 'taggables');
    }

    public function scenes(): MorphToMany
    {
        return $this->morphedByMany(Scene::class, 'taggable', 'taggables');
    }

    public function tours(): MorphToMany
    {
        return $this->morphedByMany(Tour::class, 'taggable', 'taggables');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
