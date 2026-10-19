<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['order_id', 'gateway', 'method', 'txn_ref', 'amount_vnd', 'currency', 'status', 'gateway_payload', 'signature', 'paid_at', 'refunded_at'];

    protected $hidden = ['signature', 'gateway_payload'];

    protected $casts = ['gateway_payload' => 'array', 'paid_at' => 'datetime', 'refunded_at' => 'datetime', 'amount_vnd' => 'integer'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRouteKeyName(): string
    {
        return 'txn_ref';
    }
}
