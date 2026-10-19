<?php

namespace App\Models\Concerns;

/** Thực thể có cột status draft|published. */
trait Publishable
{
    public function scopePublished($query)
    {
        return $query->where($this->getTable().'.status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
