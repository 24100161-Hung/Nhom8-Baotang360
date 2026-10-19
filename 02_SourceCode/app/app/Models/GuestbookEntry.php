<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestbookEntry extends Model
{
    public const STATUSES = ['pending', 'approved', 'hidden'];

    protected $fillable = ['user_id', 'display_name', 'message', 'locale', 'status', 'approved_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
