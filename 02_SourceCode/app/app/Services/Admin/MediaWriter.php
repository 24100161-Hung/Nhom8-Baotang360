<?php

namespace App\Services\Admin;

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\UploadPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Ảnh của thực thể có HasMedia (hiện vật, không gian 360): tệp tải lên hoặc URL ngoài + siêu dữ liệu bản quyền. */
class MediaWriter
{
    public const PROVENANCE = ['source_name', 'source_url', 'author', 'rights_holder', 'captured_at', 'provenance_note'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function addImage(Model $subject, ?UploadedFile $file, array $meta, User $actor): MediaAsset
    {
        $stored = $file ? $this->store($subject, $file) : [];

        $media = DB::transaction(function () use ($subject, $stored, $meta, $actor) {
            $isCover = (bool) ($meta['is_cover'] ?? false) || ! $subject->images()->exists();
            if ($isCover) {
                $subject->media()->where('media_type', 'image')->update(['is_cover' => false]);
            }
            $media = $subject->media()->create($stored + [
                'media_type' => 'image',
                'external_url' => $stored ? null : ($meta['external_url'] ?? null),
                'is_cover' => $isCover,
                'sort_order' => (int) $subject->media()->max('sort_order') + 1,
                'license' => $meta['license'],
                'uploaded_by' => $actor->id,
            ] + array_intersect_key($meta, array_flip(self::PROVENANCE)));
            foreach (['vi', 'en'] as $locale) {
                if (filled($meta['caption_'.$locale] ?? null)) {
                    $media->captions()->create(['locale' => $locale, 'caption' => $meta['caption_'.$locale]]);
                }
            }

            return $media;
        });
        $this->audit->log('admin.media.upload', $subject, ['media_id' => $media->id], $actor);

        return $media;
    }

    public function remove(MediaAsset $media, User $actor): void
    {
        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }
        $media->delete();
        $this->audit->log('admin.media.delete', $media, ['subject' => $media->subject_type.':'.$media->subject_id], $actor);
    }

    /** Lưu tệp với tên ngẫu nhiên theo MIME thật; trả các cột path/mime/kích thước. */
    private function store(Model $subject, UploadedFile $file): array
    {
        $ext = UploadPolicy::extensionFor('image', $file) ?? 'jpg';
        $folder = 'media/'.$subject->getMorphClass().'s/'.$subject->getKey();
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        return [
            'path' => $file->storeAs($folder, UploadPolicy::randomName($ext), 'public'),
            'mime' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'width' => $width, 'height' => $height,
        ];
    }
}
