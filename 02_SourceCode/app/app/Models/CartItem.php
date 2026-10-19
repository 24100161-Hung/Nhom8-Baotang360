<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'package_id', 'visit_date', 'quantity', 'unit_price_vnd'];

    protected $casts = ['visit_date' => 'date:Y-m-d', 'quantity' => 'integer', 'unit_price_vnd' => 'integer'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TicketPackage::class, 'package_id')->withTrashed();
    }

    public function lineTotal(): int
    {
        return $this->quantity * $this->unit_price_vnd;
    }
}
