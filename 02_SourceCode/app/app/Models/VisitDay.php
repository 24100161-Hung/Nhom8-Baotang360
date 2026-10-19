<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Lịch một ngày tham quan: sức chứa, đã bán, trạng thái open|closed|sold_out. */
class VisitDay extends Model
{
    use HasFactory;

    public const OPEN = 'open';
    public const CLOSED = 'closed';
    public const SOLD_OUT = 'sold_out';
    public const STATUSES = [self::OPEN, self::CLOSED, self::SOLD_OUT];

    protected $fillable = ['visit_date', 'capacity', 'sold', 'status'];

    protected $casts = ['visit_date' => 'date:Y-m-d', 'capacity' => 'integer', 'sold' => 'integer'];

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->sold);
    }

    /** Trạng thái hiển thị: ngày mở nhưng hết chỗ → sold_out. */
    public function effectiveStatus(): string
    {
        if ($this->status === self::OPEN && $this->remaining() <= 0) {
            return self::SOLD_OUT;
        }

        return $this->status;
    }
}
