<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotspotTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['hotspot_id', 'locale', 'title', 'text'];
}
