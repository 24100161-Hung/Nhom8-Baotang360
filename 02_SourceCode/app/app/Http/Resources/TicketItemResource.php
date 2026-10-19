<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** TicketItem {code, holder_name, visit_date, status, qr_url (signed), pdf_url (signed)}. */
class TicketItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'holder_name' => $this->holder_name,
            'visit_date' => $this->visit_date?->toDateString(),
            'status' => $this->status,
            'status_label' => __('enums.ticket_statuses.'.$this->status),
            'qr_url' => self::signed('tickets.qr', ['ticket' => $this->code]),
            'pdf_url' => self::signed('tickets.pdf', ['ticket' => $this->code]),
        ];
    }

    /** URL ký số có hạn (NFR-19): sửa tham số hoặc chữ ký → 403. */
    public static function signed(string $route, array $params): string
    {
        return URL::temporarySignedRoute(
            $route,
            now()->addMinutes((int) config('bt360.tickets.signed_url_minutes', 10080)),
            ['locale' => app()->getLocale()] + $params,
        );
    }
}
