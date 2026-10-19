<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketPackageTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = ['package_id', 'locale', 'name', 'description'];
}
