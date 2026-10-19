<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourStepTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['tour_step_id', 'locale', 'title', 'narration'];
}
