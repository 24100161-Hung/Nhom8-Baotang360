<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** AudioItem {id, locale, url|null, duration_sec, transcript, mode:'file'|'tts', license, source_name}. */
class AudioItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $caption = $this->caption($this->locale);
        $url = $this->url;

        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'url' => $url,
            'duration_sec' => $this->duration_sec,
            'transcript' => $caption?->transcript,
            'caption' => $caption?->caption,
            'mode' => $url ? 'file' : 'tts',
            'license' => $this->license,
            'source_name' => $this->source_name,
        ];
    }
}
