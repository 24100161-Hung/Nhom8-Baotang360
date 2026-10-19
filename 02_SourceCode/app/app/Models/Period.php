<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use Translatable;

    protected string $translationModel = PeriodTranslation::class;
    protected string $translationForeignKey = 'period_id';

    protected $fillable = ['code', 'start_mya', 'end_mya', 'sort_order'];

    protected $casts = ['start_mya' => 'float', 'end_mya' => 'float'];

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }
}
