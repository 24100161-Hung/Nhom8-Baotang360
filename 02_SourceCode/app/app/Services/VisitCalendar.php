<?php

namespace App\Services;

use App\Http\Resources\VisitDayResource;
use App\Models\VisitDay;

/** Lịch ngày tham quan N ngày tới: ngày chưa có bản ghi coi như đóng cửa (không bán). */
class VisitCalendar
{
    public function upcoming(?int $days = null): array
    {
        $days ??= (int) config('bt360.visit_days.calendar_days', 60);
        $from = today();
        $to = today()->addDays($days - 1);
        $rows = VisitDay::query()->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('visit_date')->get()->keyBy(fn ($d) => $d->visit_date->toDateString());

        $out = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();
            $out[] = isset($rows[$key])
                ? VisitDayResource::one($rows[$key])
                : ['date' => $key, 'capacity' => 0, 'sold' => 0, 'status' => VisitDay::CLOSED, 'remaining' => 0];
        }

        return $out;
    }
}
