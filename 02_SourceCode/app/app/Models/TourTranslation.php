<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['tour_id', 'locale', 'name', 'description', 'highlights'];
}
