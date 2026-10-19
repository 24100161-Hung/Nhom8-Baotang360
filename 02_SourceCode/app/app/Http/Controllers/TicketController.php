<?php

namespace App\Http\Controllers;

use App\Http\Resources\PackageItemResource;
use App\Models\Ticket;
use App\Models\TicketPackage;
use App\Services\TicketService;
use App\Services\VisitCalendar;
use App\Support\PrintableDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Trang gói vé + lịch; QR và vé in qua signed URL (sửa chữ ký → 403). */
class TicketController extends Controller
{
    public function index(Request $request, VisitCalendar $calendar): Response
    {
        $packages = TicketPackage::query()->published()->with('translations')->orderBy('sort_order')->orderBy('id')->get();
        $membership = $request->user()?->activeMembership();

        return Inertia::render('Tickets/Index', [
            'packages' => PackageItemResource::list($packages),
            'calendar' => $calendar->upcoming(),
            'membership' => $membership ? [
                'package_code' => $membership->package?->code,
                'starts_at' => $membership->starts_at?->toIso8601String(),
                'ends_at' => $membership->ends_at?->toIso8601String(),
                'is_active' => true,
            ] : null,
            'discountPercent' => (int) config('bt360.orders.premium_discount_percent', 20),
        ]);
    }

    public function qr(Request $request, Ticket $ticket, TicketService $tickets): HttpResponse
    {
        $png = $request->query('format') === 'png';
        $body = $png ? $tickets->png($ticket) : $tickets->svg($ticket);

        return response($body)
            ->header('Content-Type', $png ? 'image/png' : 'image/svg+xml')
            ->header('Cache-Control', 'private, max-age=600')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
    }

    public function pdf(Ticket $ticket, TicketService $tickets): HttpResponse
    {
        $ticket->load('orderItem.order', 'orderItem.package.translations');

        return response(PrintableDocument::ticket($ticket, $tickets->svg($ticket, 220)))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
