<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    protected $fillable = ['user_id', 'package_id', 'order_id', 'starts_at', 'ends_at', 'status'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TicketPackage::class, 'package_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at?->isFuture() && ! $this->starts_at?->isFuture();
    }
}
