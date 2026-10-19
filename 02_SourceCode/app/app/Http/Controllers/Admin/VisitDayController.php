<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VisitDayUpdateRequest;
use App\Http\Resources\VisitDayResource;
use App\Models\VisitDay;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Lịch vé 90 ngày: sức chứa, đã bán, trạng thái (chỉ admin). */
class VisitDayController extends Controller
{
    public const GRID_DAYS = 90;

    public function index(): Response
    {
        $this->authorize('manage-commerce');
        $rows = VisitDay::query()->whereBetween('visit_date', [today()->toDateString(), today()->addDays(self::GRID_DAYS - 1)->toDateString()])
            ->orderBy('visit_date')->get()->keyBy(fn ($d) => $d->visit_date->toDateString());
        $default = (int) config('bt360.visit_days.default_capacity', 300);
        $days = [];
        for ($i = 0; $i < self::GRID_DAYS; $i++) {
            $date = today()->addDays($i)->toDateString();
            $days[] = isset($rows[$date])
                ? VisitDayResource::one($rows[$date]) + ['raw_status' => $rows[$date]->status, 'exists' => true]
                : ['date' => $date, 'capacity' => $default, 'sold' => 0, 'status' => 'closed', 'remaining' => 0, 'raw_status' => 'closed', 'exists' => false];
        }

        return Inertia::render('Admin/VisitDays/Index', [
            'days' => $days,
            'statuses' => $this->enumOptions('visit_day_statuses', VisitDay::STATUSES),
            'defaultCapacity' => $default,
        ]);
    }

    public function update(VisitDayUpdateRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $result = DB::transaction(function () use ($data) {
            $day = VisitDay::query()->where('visit_date', $data['date'])->lockForUpdate()->first()
                ?? new VisitDay(['visit_date' => $data['date'], 'sold' => 0]);
            if ((int) $data['capacity'] < (int) $day->sold) {
                return ['error' => __('shop.errors.capacity_below_sold', ['sold' => $day->sold])];
            }
            $status = $data['status'];
            // Mở bán nhưng đã đầy → hết vé; hết vé mà còn chỗ (tăng sức chứa) → mở lại
            if ($status === VisitDay::OPEN && $day->sold >= (int) $data['capacity']) {
                $status = VisitDay::SOLD_OUT;
            }
            $day->fill(['capacity' => (int) $data['capacity'], 'status' => $status])->save();

            return ['day' => $day];
        });
        if (isset($result['error'])) {
            return back()->withErrors(['capacity' => $result['error']]);
        }
        $audit->log('admin.visit_day.update', $result['day'], $data);

        return back()->with('success', __('shop.flash.visit_day_saved', ['date' => $data['date']]));
    }
}
