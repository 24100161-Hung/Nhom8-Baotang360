<?php

namespace App\Models;

use App\Models\Concerns\HasInterpretations;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\ResolvesByIdOrKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Không gian 360° (một phòng trưng bày) với ảnh equirectangular và tiles đa phân giải. */
class Scene extends Model
{
    use HasFactory, HasInterpretations, HasMedia, HasTags, Publishable, ResolvesByIdOrKey, SoftDeletes;

    public const AUDIO_LOCALES = ['vi', 'en', 'de', 'nl'];

    protected $fillable = [
        'museum_id', 'slug', 'code', 'floor', 'sort_order', 'panorama_path', 'tiles_path', 'tile_config', 'tiles_state',
        'preview_path', 'hfov_default', 'pitch_default', 'yaw_default', 'minimap_x', 'minimap_y', 'north_offset',
        'status', 'view_count', 'source_name', 'source_url', 'author', 'license', 'is_simulated',
    ];

    protected $casts = [
        'tile_config' => 'array', 'is_simulated' => 'boolean', 'floor' => 'integer',
        'hfov_default' => 'float', 'pitch_default' => 'float', 'yaw_default' => 'float',
        'minimap_x' => 'float', 'minimap_y' => 'float', 'north_offset' => 'float',
    ];

    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    public function hotspots(): HasMany
    {
        return $this->hasMany(Hotspot::class)->orderBy('sort_order')->orderBy('id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function tourSteps(): HasMany
    {
        return $this->hasMany(TourStep::class);
    }

    public function hasTiles(): bool
    {
        return $this->tiles_path !== null && is_array($this->tile_config) && $this->tiles_state === 'done';
    }

    public function getPanoramaUrlAttribute(): ?string
    {
        return storage_url($this->panorama_path);
    }

    public function getPreviewUrlAttribute(): ?string
    {
        return storage_url($this->preview_path) ?? $this->panorama_url;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
