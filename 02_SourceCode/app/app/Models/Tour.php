<?php

namespace App\Models;

use App\Models\Concerns\HasReviews;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tour tham quan 360° theo trình tự các bước (scene + góc nhìn + lời dẫn). */
class Tour extends Model
{
    use HasFactory, HasReviews, HasTags, Publishable, ResolvesByIdOrKey, SoftDeletes, Translatable;

    public const DIFFICULTIES = ['de', 'vua', 'kho'];
    public const MODES = ['free', 'guided', 'both'];

    protected string $translationModel = TourTranslation::class;
    protected string $translationForeignKey = 'tour_id';

    protected $fillable = [
        'slug', 'theme_tag_id', 'duration_minutes', 'difficulty', 'mode', 'cover_media_id', 'sort_order',
        'is_featured', 'status', 'view_count', 'created_by',
    ];

    protected $casts = ['is_featured' => 'boolean', 'duration_minutes' => 'integer'];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'theme_tag_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TourStep::class)->orderBy('step_order');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(TourProgress::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTitleAttribute(): string
    {
        return $this->name;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
