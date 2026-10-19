<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    public const STATUSES = ['valid', 'used', 'void'];

    protected $fillable = ['order_item_id', 'code', 'qr_token', 'holder_name', 'visit_date', 'status', 'used_at'];

    protected $hidden = ['qr_token'];

    protected $casts = ['visit_date' => 'date:Y-m-d', 'used_at' => 'datetime'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
