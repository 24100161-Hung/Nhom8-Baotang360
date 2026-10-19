<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'package_id', 'visit_date', 'quantity', 'unit_price_vnd', 'line_total_vnd'];

    protected $casts = ['visit_date' => 'date:Y-m-d', 'quantity' => 'integer', 'unit_price_vnd' => 'integer', 'line_total_vnd' => 'integer'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TicketPackage::class, 'package_id')->withTrashed();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('id');
    }
}
