<?php

namespace Database\Seeders;

use App\Models\VisitDay;
use Illuminate\Database\Seeder;

/**
 * Lịch 120 ngày (30 ngày qua + 90 ngày tới): thứ Hai đóng cửa, sức chứa 300, vài ngày tới hết vé (mô phỏng).
 * Chỉ tạo ngày chưa có (không ghi đè sold của ngày đã bán thật).
 */
class VisitDaySeeder extends Seeder
{
    public const PAST_DAYS = 30;
    public const FUTURE_DAYS = 90;
    /** Ngày tới (offset) bị đặt hết vé để minh họa lịch "hết vé". */
    public const SOLD_OUT_OFFSETS = [4, 11, 18];

    public function run(): void
    {
        $capacity = (int) config('bt360.visit_days.default_capacity', 300);
        $closedWeekday = (int) config('bt360.visit_days.closed_weekday', 1);
        $existing = VisitDay::query()->pluck('visit_date')->map(fn ($d) => $d->toDateString())->flip();
        $rows = [];
        for ($offset = -self::PAST_DAYS; $offset < self::FUTURE_DAYS; $offset++) {
            $date = today()->addDays($offset);
            if (isset($existing[$date->toDateString()])) {
                continue;
            }
            $closed = $date->dayOfWeekIso === $closedWeekday;
            $soldOut = ! $closed && in_array($offset, self::SOLD_OUT_OFFSETS, true);
            $rows[] = [
                'visit_date' => $date->toDateString(),
                'capacity' => $capacity,
                // Ngày đã qua: lượng khách mô phỏng (bán tại quầy); ngày tới bắt đầu từ 0
                'sold' => $closed ? 0 : ($soldOut ? $capacity : ($offset < 0 ? 60 + (abs($offset) * 37) % 200 : 0)),
                'status' => $closed ? VisitDay::CLOSED : ($soldOut ? VisitDay::SOLD_OUT : VisitDay::OPEN),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            VisitDay::query()->insert($chunk);
        }
    }
}
