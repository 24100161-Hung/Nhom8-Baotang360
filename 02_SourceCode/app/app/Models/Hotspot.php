<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Điểm nóng trên ảnh 360: info (thông tin), scene (chuyển phòng), artifact (mở hiện vật). */
class Hotspot extends Model
{
    use HasFactory, Translatable;

    public const TYPES = ['info', 'scene', 'artifact'];

    protected string $translationModel = HotspotTranslation::class;
    protected string $translationForeignKey = 'hotspot_id';

    protected $fillable = [
        'scene_id', 'type', 'pitch', 'yaw', 'target_scene_id', 'target_pitch', 'target_yaw', 'target_hfov',
        'artifact_id', 'icon', 'image_path', 'sort_order', 'status',
    ];

    protected $casts = [
        'pitch' => 'float', 'yaw' => 'float', 'target_pitch' => 'float', 'target_yaw' => 'float', 'target_hfov' => 'float',
    ];

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function targetScene(): BelongsTo
    {
        return $this->belongsTo(Scene::class, 'target_scene_id');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    public function getTitleAttribute(): string
    {
        return $this->tr('title') ?? '';
    }
}
