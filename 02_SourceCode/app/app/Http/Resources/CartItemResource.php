<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** CartItem {id, package: PackageItem, visit_date, quantity, unit_price_vnd, line_total_vnd}. */
class CartItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package' => PackageItemResource::one($this->package),
            'visit_date' => $this->visit_date?->toDateString(),
            'quantity' => (int) $this->quantity,
            'unit_price_vnd' => (int) $this->unit_price_vnd,
            'line_total_vnd' => $this->lineTotal(),
        ];
    }
}
