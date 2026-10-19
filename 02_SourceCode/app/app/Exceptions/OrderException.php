<?php

namespace App\Exceptions;

use RuntimeException;

/** Lỗi nghiệp vụ giỏ hàng/đơn hàng (hết vé, ngày đóng cửa, chuyển trạng thái sai…). field = ô lỗi trên form. */
class OrderException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'quantity', public readonly ?int $available = null)
    {
        parent::__construct($message);
    }

    public static function notEnoughTickets(int $available): self
    {
        return new self(__('shop.errors.only_left', ['count' => $available]), 'quantity', $available);
    }

    public static function dayUnavailable(): self
    {
        return new self(__('shop.errors.day_unavailable'), 'visit_date');
    }

    public static function packageUnavailable(): self
    {
        return new self(__('shop.errors.package_unavailable'), 'package_id');
    }

    public static function quantityTooLarge(int $max): self
    {
        return new self(__('shop.errors.quantity_max', ['max' => $max]), 'quantity');
    }

    public static function cartFull(): self
    {
        return new self(__('shop.errors.cart_full'), 'package_id');
    }

    public static function cartEmpty(): self
    {
        return new self(__('shop.errors.cart_empty'), 'cart');
    }

    public static function tooManyPending(int $max): self
    {
        return new self(__('shop.errors.too_many_pending', ['max' => $max]), 'cart');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('shop.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function cannotCancel(): self
    {
        return new self(__('shop.errors.cannot_cancel'), 'status');
    }

    public static function cannotPay(): self
    {
        return new self(__('shop.errors.cannot_pay'), 'status');
    }
}
