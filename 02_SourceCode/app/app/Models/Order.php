<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** Đơn hàng vé: pending (giữ chỗ 15 phút) → paid | cancelled | expired; paid → refunded. */
class Order extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const REFUNDED = 'refunded';
    public const STATUSES = [self::PENDING, self::PAID, self::CANCELLED, self::EXPIRED, self::REFUNDED];
    public const METHODS = ['card', 'bank', 'ewallet'];

    /** Máy trạng thái hợp lệ. */
    public const TRANSITIONS = [
        self::PENDING => [self::PAID, self::CANCELLED, self::EXPIRED],
        self::PAID => [self::REFUNDED],
        self::CANCELLED => [],
        self::EXPIRED => [],
        self::REFUNDED => [],
    ];

    protected $fillable = [
        'code', 'user_id', 'status', 'subtotal_vnd', 'discount_vnd', 'total_vnd', 'payment_method', 'contact_name',
        'contact_email', 'contact_phone', 'invoice_address', 'note', 'hold_expires_at', 'paid_at', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'hold_expires_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
        'subtotal_vnd' => 'integer', 'discount_vnd' => 'integer', 'total_vnd' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, OrderItem::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isHoldActive(): bool
    {
        return $this->status === self::PENDING && $this->hold_expires_at !== null && $this->hold_expires_at->isFuture();
    }

    /** Đơn chờ thanh toán còn trong thời gian giữ chỗ. */
    public function canPay(): bool
    {
        return $this->isHoldActive();
    }

    /** Hủy được: đơn pending, hoặc đơn paid mà mọi ngày tham quan > hôm nay + N ngày (hoàn tiền). */
    public function canCancel(): bool
    {
        if ($this->status === self::PENDING) {
            return true;
        }

        return $this->status === self::PAID && $this->isRefundable();
    }

    public function isRefundable(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $limit = now()->startOfDay()->addDays((int) config('bt360.orders.refund_min_days_before', 1));
        $dates = $items->pluck('visit_date')->filter();

        // Đơn chỉ có hội viên (không ngày tham quan) không hoàn tiền
        return $dates->isNotEmpty() && $dates->every(fn ($d) => $d->copy()->startOfDay()->greaterThan($limit));
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
