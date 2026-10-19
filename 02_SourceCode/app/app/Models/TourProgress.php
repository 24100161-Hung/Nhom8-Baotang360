<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourProgress extends Model
{
    protected $table = 'tour_progress';

    protected $fillable = ['user_id', 'session_key', 'tour_id', 'current_step', 'completed_steps', 'started_at', 'completed_at', 'last_seen_at'];

    protected $casts = [
        'completed_steps' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        'last_seen_at' => 'datetime', 'current_step' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
