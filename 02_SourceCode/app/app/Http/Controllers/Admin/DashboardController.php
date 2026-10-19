<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Dashboard quản trị: 8 KPI + biểu đồ doanh thu 7 ngày, trạng thái đơn, lượt tham quan, phòng xem nhiều. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $this->authorize('manage-content');

        return Inertia::render('Admin/Dashboard', $dashboard->build($request->user()));
    }
}
