<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitEventsRequest;
use App\Services\VisitEventRecorder;
use Illuminate\Http\Response;

class VisitEventController extends Controller
{
    /** Nhận lô sự kiện (sendBeacon) → 204, không trả dữ liệu. */
    public function store(VisitEventsRequest $request, VisitEventRecorder $recorder): Response
    {
        $recorder->recordBatch($request, $request->validated('events'), $request->validated('device'));

        return response()->noContent();
    }
}
