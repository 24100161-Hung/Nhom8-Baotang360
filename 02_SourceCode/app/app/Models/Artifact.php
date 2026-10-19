<?php

namespace App\Models;

use App\Models\Concerns\HasInterpretations;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasReviews;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\ResolvesByIdOrKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Hiện vật/mẫu vật của Naturalis (số đăng ký, tên khoa học, thời kỳ, kích thước…). */
class Artifact extends Model
{
    use HasFactory, HasInterpretations, HasMedia, HasReviews, HasTags, Publishable, ResolvesByIdOrKey, SoftDeletes;

    public const TAXA = ['dinosauria', 'mammalia', 'aves', 'reptilia', 'amphibia', 'pisces', 'insecta', 'arthropoda', 'mollusca', 'plantae', 'fossil_plant', 'mineral', 'meteorite', 'hominini', 'crustacea', 'fossil_invertebrate', 'fossil_vertebrate', 'fungi', 'human', 'rock', 'invertebrata', 'other'];

    protected $fillable = [
        'museum_id', 'scene_id', 'slug', 'catalog_number', 'scientific_name', 'taxon_group', 'period_id',
        'origin_country', 'origin_locality', 'material', 'length_cm', 'width_cm', 'height_cm', 'weight_kg',
        'year_collected', 'collector', 'source_url', 'image_license', 'image_author', 'is_highlight', 'status', 'view_count',
    ];

    protected $casts = [
        'is_highlight' => 'boolean', 'length_cm' => 'float', 'width_cm' => 'float', 'height_cm' => 'float',
        'weight_kg' => 'float', 'year_collected' => 'integer',
    ];

    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function hotspots(): HasMany
    {
        return $this->hasMany(Hotspot::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
