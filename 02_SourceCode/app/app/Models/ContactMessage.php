<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    public const STATUSES = ['new', 'read', 'replied'];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'body', 'status', 'replied_by', 'replied_at', 'reply_note', 'ip'];

    protected $casts = ['replied_at' => 'datetime'];

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
