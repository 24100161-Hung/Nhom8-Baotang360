<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sự kiện hành vi trong trình xem 360° (phục vụ phân tích luồng, drop-off). */
class VisitEvent extends Model
{
    public const TYPES = [
        'scene_enter', 'scene_leave', 'hotspot_click', 'tour_start', 'tour_step',
        'tour_complete', 'tour_abandon', 'artifact_view', 'audio_play',
    ];
    public const DEVICES = ['mobile', 'tablet', 'desktop'];

    public $timestamps = false;

    protected $fillable = [
        'session_key', 'user_id', 'event_type', 'scene_id', 'hotspot_id', 'tour_id', 'step_order',
        'artifact_id', 'duration_ms', 'locale', 'device', 'meta', 'created_at',
    ];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];
}
