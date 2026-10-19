<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * OrderItem (hợp đồng trang) {code, status, status_label, subtotal_vnd, discount_vnd, total_vnd, payment_method, contact_*,
 * invoice_address, note, hold_expires_at, seconds_left, paid_at, items: [...tickets], payments, can_pay, can_cancel, invoice_url}.
 * Nạp: ->with(OrderResource::eager()).
 */
class OrderResource extends BaseResource
{
    public static function eager(): array
    {
        return ['items.package.translations', 'items.tickets', 'payments'];
    }

    public function toArray(Request $request): array
    {
        $secondsLeft = $this->isHoldActive() ? max(0, (int) now()->diffInSeconds($this->hold_expires_at, false)) : 0;

        return [
            'code' => $this->code,
            'status' => $this->status,
            'status_label' => __('enums.order_statuses.'.$this->status),
            'subtotal_vnd' => (int) $this->subtotal_vnd,
            'discount_vnd' => (int) $this->discount_vnd,
            'total_vnd' => (int) $this->total_vnd,
            'payment_method' => $this->payment_method,
            'payment_method_label' => __('enums.payment_methods.'.$this->payment_method),
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'invoice_address' => $this->invoice_address,
            'note' => $this->note,
            'hold_expires_at' => self::iso($this->hold_expires_at),
            'seconds_left' => $secondsLeft,
            'paid_at' => self::iso($this->paid_at),
            'cancelled_at' => self::iso($this->cancelled_at),
            'created_at' => self::iso($this->created_at),
            'items' => $this->items->map(fn ($item) => [
                'package_code' => $item->package?->code,
                'package_name' => $item->package?->name,
                'package_type' => $item->package?->package_type,
                'visit_date' => $item->visit_date?->toDateString(),
                'quantity' => (int) $item->quantity,
                'unit_price_vnd' => (int) $item->unit_price_vnd,
                'line_total_vnd' => (int) $item->line_total_vnd,
                'tickets' => TicketItemResource::list($item->relationLoaded('tickets') ? $item->tickets : []),
            ])->values()->all(),
            'payments' => $this->payments->map(fn ($p) => [
                'txn_ref' => $p->txn_ref,
                'method' => $p->method,
                'status' => $p->status,
                'status_label' => __('enums.payment_statuses.'.$p->status),
                'amount_vnd' => (int) $p->amount_vnd,
                'paid_at' => self::iso($p->paid_at),
            ])->values()->all(),
            'can_pay' => $this->canPay(),
            'can_cancel' => $this->canCancel(),
            'invoice_url' => TicketItemResource::signed('orders.invoice', ['order' => $this->code]),
            'url' => localized_route('orders.show', ['order' => $this->code]),
        ];
    }
}
