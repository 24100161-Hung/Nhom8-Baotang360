<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** VisitDay {date, capacity, sold, status: open|sold_out|closed, remaining}. */
class VisitDayResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->visit_date->toDateString(),
            'capacity' => (int) $this->capacity,
            'sold' => (int) $this->sold,
            'status' => $this->effectiveStatus(),
            'remaining' => $this->status === 'closed' ? 0 : $this->remaining(),
        ];
    }
}
