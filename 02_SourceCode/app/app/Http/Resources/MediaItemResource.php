<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** MediaItem {id, url, media_type, caption, license, license_label, source_name, source_url, author, width, height, is_cover}. */
class MediaItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $caption = $this->caption();

        return [
            'id' => $this->id,
            'url' => $this->url,
            'media_type' => $this->media_type,
            'caption' => $caption?->caption,
            'license' => $this->license,
            'license_label' => self::licenseLabel($this->license),
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            'author' => $this->author,
            'width' => $this->width,
            'height' => $this->height,
            'is_cover' => (bool) $this->is_cover,
        ];
    }

    public static function licenseLabel(?string $license): ?string
    {
        if ($license === null || $license === '') {
            return null;
        }
        $key = 'enums.licenses.'.$license;
        $label = __($key);

        return $label === $key ? $license : $label;
    }
}
