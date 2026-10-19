<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourStep extends Model
{
    use Translatable;

    protected string $translationModel = TourStepTranslation::class;
    protected string $translationForeignKey = 'tour_step_id';

    protected $fillable = ['tour_id', 'step_order', 'scene_id', 'hotspot_id', 'pitch', 'yaw', 'hfov', 'dwell_seconds', 'auto_advance'];

    protected $casts = ['auto_advance' => 'boolean', 'pitch' => 'float', 'yaw' => 'float', 'hfov' => 'float', 'step_order' => 'integer', 'dwell_seconds' => 'integer'];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function hotspot(): BelongsTo
    {
        return $this->belongsTo(Hotspot::class);
    }

    public function getTitleAttribute(): string
    {
        return $this->tr('title') ?? '';
    }
}
