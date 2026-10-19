<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    public const LICENSES = [
        'cc_by_sa_3', 'cc_by_sa_4', 'cc_by_3', 'cc_by_4', 'cc_by_sa_2', 'cc_by_2', 'cc0', 'public_domain',
        'cc_by', 'cc_by_sa', 'cc_by_nc', 'all_rights_reserved', 'simulated', 'other',
    ];
    public const TYPES = ['image', 'audio', 'video', 'panorama', 'document'];

    protected $fillable = [
        'subject_type', 'subject_id', 'media_type', 'locale', 'path', 'external_url', 'mime', 'size_bytes',
        'duration_sec', 'width', 'height', 'sort_order', 'is_cover', 'source_name', 'source_url', 'author',
        'license', 'rights_holder', 'captured_at', 'provenance_note', 'uploaded_by',
    ];

    protected $casts = ['is_cover' => 'boolean', 'captured_at' => 'date'];

    protected $appends = ['url'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function captions(): HasMany
    {
        return $this->hasMany(MediaCaption::class, 'media_id');
    }

    public function caption(?string $locale = null): ?MediaCaption
    {
        $locale ??= app()->getLocale();
        $captions = $this->relationLoaded('captions') ? $this->captions : $this->captions()->get();

        return $captions->firstWhere('locale', $locale) ?? $captions->first();
    }

    public function getUrlAttribute(): ?string
    {
        // Ưu tiên tệp đã lưu cục bộ; không có thì dùng liên kết ngoài (ảnh Commons khi tải về lỗi)
        if ($this->path) {
            return Storage::disk('public')->url($this->path);
        }

        return $this->external_url ?: null;
    }
}
