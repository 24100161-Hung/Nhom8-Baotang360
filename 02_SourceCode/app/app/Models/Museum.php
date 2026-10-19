<?php

namespace App\Models;

use App\Models\Concerns\HasInterpretations;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasReviews;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Museum extends Model
{
    use HasFactory, HasInterpretations, HasMedia, HasReviews;

    public const LICENSE_LABELS = ['cc_by_sa_3' => 'CC BY-SA 3.0', 'cc_by_4' => 'CC BY 4.0', 'cc0' => 'CC0', 'other' => 'Other'];

    protected $fillable = [
        'slug', 'name', 'city', 'country', 'website', 'license', 'attribution_text', 'logo_path',
        'address', 'phone', 'email', 'floorplan', 'lat', 'lng', 'status',
    ];

    protected $casts = ['floorplan' => 'array', 'lat' => 'float', 'lng' => 'float'];

    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    /** Bảo tàng duy nhất của hệ thống (Naturalis). */
    public static function main(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function getLicenseLabelAttribute(): string
    {
        return self::LICENSE_LABELS[$this->license] ?? (string) $this->license;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return storage_url($this->logo_path);
    }
}
