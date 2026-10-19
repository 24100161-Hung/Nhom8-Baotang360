<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use App\Models\Concerns\ResolvesByIdOrKey;
use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Gói vé: vé lượt (single, cần ngày tham quan) hoặc hội viên (membership, 365 ngày). */
class TicketPackage extends Model
{
    use HasFactory, Publishable, ResolvesByIdOrKey, SoftDeletes, Translatable;

    public const SINGLE = 'single';
    public const MEMBERSHIP = 'membership';
    public const REGULAR_CODE = 'regular';

    protected string $translationModel = TicketPackageTranslation::class;
    protected string $translationForeignKey = 'package_id';

    protected $fillable = ['code', 'package_type', 'price_vnd', 'validity_days', 'benefits', 'max_per_order', 'is_featured', 'sort_order', 'status'];

    protected $casts = ['benefits' => 'array', 'is_featured' => 'boolean', 'price_vnd' => 'integer', 'validity_days' => 'integer', 'max_per_order' => 'integer'];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'package_id');
    }

    public function isSingle(): bool
    {
        return $this->package_type === self::SINGLE;
    }

    public function isMembership(): bool
    {
        return $this->package_type === self::MEMBERSHIP;
    }

    /** Danh sách quyền lợi theo locale (fallback locale mặc định). */
    public function benefitsFor(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $benefits = (array) ($this->benefits ?? []);

        return array_values((array) ($benefits[$locale] ?? $benefits[config('app.fallback_locale')] ?? []));
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
