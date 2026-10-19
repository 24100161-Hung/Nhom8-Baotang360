<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';
    public const CURATOR = 'curator';
    public const VISITOR = 'visitor';

    public const ALL = [self::ADMIN, self::CURATOR, self::VISITOR];

    protected $fillable = ['code', 'name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
