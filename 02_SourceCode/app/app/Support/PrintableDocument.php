<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Ticket;
use App\Http\Resources\TicketItemResource;

/**
 * Tài liệu HTML in được (hóa đơn, vé, thư xác nhận) dựng bằng PHP; mọi giá trị qua e() để chống XSS.
 * Phần thân dựng bằng PHP; riêng chân trang học thuật dùng partial Blade chung.
 */
final class PrintableDocument
{
    private const STYLE = 'body{font-family:system-ui,Segoe UI,Arial,sans-serif;color:#1b1b1b;max-width:760px;margin:24px auto;padding:0 16px}'
        .'h1{font-size:22px;margin:0 0 4px}table{width:100%;border-collapse:collapse;margin:16px 0}'
        .'th,td{border-bottom:1px solid #ddd;padding:8px;text-align:left}td.r,th.r{text-align:right}'
        .'.muted{color:#666;font-size:13px}.ticket{border:2px dashed #2f6f4f;border-radius:12px;padding:16px;display:flex;gap:16px;align-items:center}'
        .'.bt360-academic-footer{margin-top:32px;border-top:1px solid #ddd;padding-top:8px;text-align:center}.bt360-academic-footer p{margin:2px 0}'
        .'@media print{.noprint{display:none}}';

    public static function invoice(Order $order): string
    {
        $rows = '';
        foreach ($order->items as $item) {
            $rows .= '<tr><td>'.e($item->package?->name).'</td><td>'.e($item->visit_date?->format('d/m/Y') ?? '—').'</td>'
                .'<td class="r">'.(int) $item->quantity.'</td><td class="r">'.self::money($item->unit_price_vnd).'</td>'
                .'<td class="r">'.self::money($item->line_total_vnd).'</td></tr>';
        }
        $body = '<h1>'.e(__('shop.invoice.title')).' '.e($order->code).'</h1>'
            .'<p class="muted">'.e(config('bt360.museum.name')).' · '.e(config('bt360.museum.address')).'</p>'
            .'<p>'.e(__('shop.invoice.customer')).': <strong>'.e($order->contact_name).'</strong><br>'
            .e($order->contact_email).' · '.e($order->contact_phone)
            .($order->invoice_address ? '<br>'.e($order->invoice_address) : '').'</p>'
            .'<p>'.e(__('shop.invoice.status')).': <strong>'.e(__('enums.order_statuses.'.$order->status)).'</strong>'
            .' · '.e(__('enums.payment_methods.'.$order->payment_method))
            .($order->paid_at ? ' · '.e($order->paid_at->format('d/m/Y H:i')) : '').'</p>'
            .'<table><thead><tr><th>'.e(__('shop.invoice.package')).'</th><th>'.e(__('shop.invoice.visit_date')).'</th>'
            .'<th class="r">'.e(__('shop.invoice.quantity')).'</th><th class="r">'.e(__('shop.invoice.unit_price')).'</th>'
            .'<th class="r">'.e(__('shop.invoice.line_total')).'</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p class="r" style="text-align:right">'.e(__('shop.invoice.subtotal')).': '.self::money($order->subtotal_vnd)
            .'<br>'.e(__('shop.invoice.discount')).': -'.self::money($order->discount_vnd)
            .'<br><strong>'.e(__('shop.invoice.total')).': '.self::money($order->total_vnd).'</strong></p>'
            .'<p class="muted">'.e(__('shop.invoice.sandbox_note')).'</p>'
            .'<button class="noprint" id="bt-print" type="button">'.e(__('shop.invoice.print')).'</button>';

        return self::page(__('shop.invoice.title').' '.$order->code, $body);
    }

    public static function ticket(Ticket $ticket, string $qrSvg): string
    {
        $order = $ticket->orderItem?->order;
        $body = '<div class="ticket"><div>'.$qrSvg.'</div><div>'
            .'<h1>'.e(__('shop.ticket.title')).'</h1>'
            .'<p><strong>'.e($ticket->code).'</strong></p>'
            .'<p>'.e(__('shop.ticket.holder')).': '.e($ticket->holder_name).'<br>'
            .e(__('shop.ticket.visit_date')).': '.e($ticket->visit_date?->format('d/m/Y') ?? '—').'<br>'
            .e(__('shop.ticket.package')).': '.e($ticket->orderItem?->package?->name).'<br>'
            .e(__('shop.ticket.status')).': '.e(__('enums.ticket_statuses.'.$ticket->status)).'</p>'
            .'<p class="muted">'.e(config('bt360.museum.name')).' · '.e(__('shop.ticket.order')).' '.e($order?->code).'</p>'
            .'</div></div><button class="noprint" id="bt-print" type="button">'.e(__('shop.invoice.print')).'</button>';

        return self::page(__('shop.ticket.title').' '.$ticket->code, $body);
    }

    /** Thư xác nhận thanh toán (gửi qua mailer log khi demo). */
    public static function paidMail(Order $order): string
    {
        $lines = '';
        foreach ($order->items as $item) {
            $lines .= '<li>'.e($item->package?->name).' × '.(int) $item->quantity
                .($item->visit_date ? ' – '.e($item->visit_date->format('d/m/Y')) : '').'</li>';
        }
        $body = '<p>'.e(__('shop.mail.greeting', ['name' => $order->contact_name])).'</p>'
            .'<p>'.e(__('shop.mail.paid_body', ['code' => $order->code])).'</p><ul>'.$lines.'</ul>'
            .'<p><strong>'.e(__('shop.invoice.total')).': '.self::money($order->total_vnd).'</strong></p>'
            .'<p><a href="'.e(localized_route('orders.show', ['order' => $order->code])).'">'.e(__('shop.mail.view_order')).'</a></p>'
            .'<p><a href="'.e(OrderLookupToken::url($order)).'">'.e(__('shop.mail.lookup_order')).'</a></p>';

        return self::page(__('shop.mail.paid_subject', ['code' => $order->code]), $body);
    }

    /** Trang tra cứu đơn qua token mã hóa: chỉ hiện tên người nhận (không email/điện thoại), trạng thái, vé, hóa đơn. */
    public static function lookup(Order $order): string
    {
        $rows = '';
        foreach ($order->items as $item) {
            $tickets = '';
            foreach ($item->tickets as $ticket) {
                $tickets .= '<li><a href="'.e(TicketItemResource::signed('tickets.pdf', ['ticket' => $ticket->code])).'">'.e($ticket->code).'</a> – '
                    .e(__('enums.ticket_statuses.'.$ticket->status)).'</li>';
            }
            $rows .= '<tr><td>'.e($item->package?->name).'</td><td>'.e($item->visit_date?->format('d/m/Y') ?? '—').'</td>'
                .'<td class="r">'.(int) $item->quantity.'</td><td>'.($tickets !== '' ? '<ul>'.$tickets.'</ul>' : '—').'</td></tr>';
        }
        $body = '<h1>'.e(__('shop.lookup.title')).' '.e($order->code).'</h1>'
            .'<p>'.e(__('shop.invoice.customer')).': <strong>'.e($order->contact_name).'</strong></p>'
            .'<p>'.e(__('shop.invoice.status')).': <strong>'.e(__('enums.order_statuses.'.$order->status)).'</strong>'
            .' · '.e(__('shop.invoice.total')).': '.self::money($order->total_vnd).'</p>'
            .'<table><thead><tr><th>'.e(__('shop.invoice.package')).'</th><th>'.e(__('shop.invoice.visit_date')).'</th>'
            .'<th class="r">'.e(__('shop.invoice.quantity')).'</th><th>'.e(__('shop.lookup.tickets')).'</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p><a href="'.e(TicketItemResource::signed('orders.invoice', ['order' => $order->code])).'">'.e(__('shop.lookup.invoice')).'</a></p>'
            .'<p class="muted">'.e(__('shop.lookup.note')).'</p>';

        return self::page(__('shop.lookup.title').' '.$order->code, $body);
    }

    public static function money(int|float|null $value): string
    {
        return number_format((int) $value, 0, ',', '.').' ₫';
    }

    /** Chân trang học thuật bắt buộc (dùng chung partial Blade với app.blade.php và errors/*). */
    private static function footer(): string
    {
        return view('partials.academic-footer', ['class' => 'muted'])->render();
    }

    private static function page(string $title, string $body): string
    {
        return '<!doctype html><html lang="'.e(app()->getLocale()).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            .'<title>'.e($title).'</title><style>'.self::STYLE.'</style></head><body>'.$body.self::footer()
            // CSP chặn onclick nội tuyến: gắn sự kiện bằng script có nonce
            .'<script nonce="'.e((string) \Illuminate\Support\Facades\Vite::cspNonce()).'">'
            .'document.getElementById("bt-print")?.addEventListener("click",()=>window.print());</script></body></html>';
    }
}
