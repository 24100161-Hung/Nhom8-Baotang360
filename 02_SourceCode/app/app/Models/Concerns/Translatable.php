<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dùng cho các bảng có bảng *_translations đơn giản (period, tag, hotspot, tour, step, package, post…).
 * Lớp sử dụng phải khai báo $translationModel và $translationForeignKey.
 */
trait Translatable
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel, $this->translationForeignKey);
    }

    /** Lấy bản dịch theo locale, dự phòng sang locale mặc định rồi tới bản dịch bất kỳ. */
    public function translate(?string $locale = null): ?object
    {
        $locale ??= app()->getLocale();
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', config('app.fallback_locale'))
            ?? $translations->first();
    }

    /** Giá trị một trường đã dịch (fallback như translate()). */
    public function tr(string $field, ?string $locale = null): ?string
    {
        $value = $this->translate($locale)?->{$field};

        return $value === null ? null : (string) $value;
    }

    /** Bản dịch đúng locale (không fallback) – dùng cho form quản trị. */
    public function translationFor(string $locale): ?object
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $translations->firstWhere('locale', $locale);
    }

    /** Ghi bản dịch theo mảng {vi: {...}, en: {...}}; bỏ qua locale không có trường bắt buộc. */
    public function syncTranslations(array $byLocale, string $requiredField): void
    {
        foreach ($byLocale as $locale => $fields) {
            if (! in_array($locale, ['vi', 'en'], true) || ! is_array($fields) || blank($fields[$requiredField] ?? null)) {
                continue;
            }
            $this->translations()->updateOrCreate(['locale' => $locale], $fields);
        }
        $this->unsetRelation('translations');
    }

    public function getNameAttribute(): string
    {
        return $this->tr('name') ?? $this->slug ?? $this->code ?? '';
    }

    public function scopeWithTranslation($query)
    {
        return $query->with('translations');
    }
}
