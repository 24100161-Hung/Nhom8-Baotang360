<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** PackageItem {id, code, name, description, package_type, price_vnd, validity_days, benefits, max_per_order, is_featured}. */
class PackageItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->tr('description'),
            'package_type' => $this->package_type,
            'price_vnd' => (int) $this->price_vnd,
            'validity_days' => (int) $this->validity_days,
            'benefits' => $this->benefitsFor(),
            'max_per_order' => (int) $this->max_per_order,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
        ];
    }
}
