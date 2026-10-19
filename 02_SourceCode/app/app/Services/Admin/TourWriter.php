<?php

namespace App\Services\Admin;

use App\Models\MediaAsset;
use App\Models\Tour;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\UploadPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Tạo/sửa/xóa tour và sắp xếp lại các bước trong một transaction. */
class TourWriter
{
    public const FIELDS = ['slug', 'theme_tag_id', 'duration_minutes', 'difficulty', 'mode', 'sort_order', 'is_featured', 'status'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function save(?Tour $tour, array $data, User $actor): Tour
    {
        $isNew = $tour === null;
        $tour = DB::transaction(function () use ($tour, $data, $actor) {
            $tour ??= new Tour(['created_by' => $actor->id]);
            $tour->fill(array_intersect_key($data, array_flip(self::FIELDS)))->save();
            $tour->syncTranslations([
                'vi' => ['name' => $data['name_vi'], 'description' => $data['description_vi'] ?? null, 'highlights' => $data['highlights_vi'] ?? null],
                'en' => ['name' => $data['name_en'], 'description' => $data['description_en'] ?? null, 'highlights' => $data['highlights_en'] ?? null],
            ], 'name');
            if (array_key_exists('steps', $data)) {
                $this->replaceSteps($tour, $data['steps']);
            }

            return $tour;
        });
        $this->audit->log($isNew ? 'admin.tour.create' : 'admin.tour.update', $tour, ['slug' => $tour->slug], $actor);

        return $tour;
    }

    public function saveSteps(Tour $tour, array $steps, User $actor): void
    {
        DB::transaction(fn () => $this->replaceSteps($tour, $steps));
        $this->audit->log('admin.tour.steps', $tour, ['count' => count($steps)], $actor);
    }

    /** Ảnh bìa tour: lưu media_assets (subject tour) và trỏ cover_media_id; ảnh cũ bị xóa. Không có tệp → bỏ qua. */
    public function setCover(Tour $tour, ?UploadedFile $file, User $actor): void
    {
        if (! $file) {
            return;
        }
        $ext = UploadPolicy::extensionFor('image', $file) ?? 'jpg';
        $path = $file->storeAs('media/tours/'.$tour->id, UploadPolicy::randomName($ext), 'public');
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];
        $old = $tour->cover;
        $media = MediaAsset::create([
            'subject_type' => 'tour', 'subject_id' => $tour->id, 'media_type' => 'image', 'path' => $path,
            'mime' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'width' => $width, 'height' => $height,
            'is_cover' => true, 'license' => 'other', 'source_name' => 'BaoTang360', 'uploaded_by' => $actor->id,
        ]);
        $tour->forceFill(['cover_media_id' => $media->id])->save();
        if ($old) {
            app(MediaWriter::class)->remove($old, $actor);
        }
        $tour->unsetRelation('cover');
        $this->audit->log('admin.tour.cover', $tour, ['media_id' => $media->id], $actor);
    }

    public function delete(Tour $tour, User $actor): void
    {
        $tour->delete();
        $this->audit->log('admin.tour.delete', $tour, ['slug' => $tour->slug], $actor);
    }

    /** Thay toàn bộ bước theo thứ tự mảng (step_order 1..n). */
    private function replaceSteps(Tour $tour, array $steps): void
    {
        $tour->steps()->delete();
        foreach (array_values($steps) as $i => $s) {
            $step = $tour->steps()->create([
                'step_order' => $i + 1,
                'scene_id' => $s['scene_id'],
                'hotspot_id' => $s['hotspot_id'] ?? null,
                'pitch' => $s['pitch'] ?? 0,
                'yaw' => $s['yaw'] ?? 0,
                'hfov' => $s['hfov'] ?? 100,
                'dwell_seconds' => $s['dwell_seconds'] ?? 60,
                'auto_advance' => (bool) ($s['auto_advance'] ?? false),
            ]);
            $step->syncTranslations([
                'vi' => ['title' => $s['title_vi'], 'narration' => $s['narration_vi'] ?? null],
                'en' => ['title' => $s['title_en'], 'narration' => $s['narration_en'] ?? null],
            ], 'title');
        }
        $tour->unsetRelation('steps');
    }
}
