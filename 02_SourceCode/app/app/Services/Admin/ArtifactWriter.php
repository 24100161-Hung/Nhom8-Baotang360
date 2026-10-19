<?php

namespace App\Services\Admin;

use App\Models\Artifact;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Tạo/sửa/xóa hiện vật cùng diễn giải, thẻ và ảnh trong một transaction. */
class ArtifactWriter
{
    use SyncsInterpretations;

    public const FIELDS = [
        'slug', 'catalog_number', 'scientific_name', 'taxon_group', 'period_id', 'scene_id', 'origin_country', 'origin_locality',
        'material', 'length_cm', 'width_cm', 'height_cm', 'weight_kg', 'year_collected', 'collector', 'source_url',
        'image_license', 'image_author', 'is_highlight', 'status',
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function save(?Artifact $artifact, array $data, User $actor): Artifact
    {
        $isNew = $artifact === null;
        $artifact = DB::transaction(function () use ($artifact, $data, $actor) {
            $artifact ??= new Artifact(['museum_id' => $this->museumId()]);
            $values = array_intersect_key($data, array_flip(self::FIELDS));
            $values['origin_country'] = isset($values['origin_country']) ? strtoupper($values['origin_country']) : null;
            $artifact->fill($values)->save();
            $this->syncInterpretations($artifact, $data['interpretations'] ?? [], $actor->id);
            // Form không gửi tag_ids → giữ nguyên thẻ hiện có
            if (array_key_exists('tag_ids', $data)) {
                $artifact->tags()->sync($data['tag_ids'] ?? []);
            }

            return $artifact;
        });
        $this->audit->log($isNew ? 'admin.artifact.create' : 'admin.artifact.update', $artifact, ['slug' => $artifact->slug], $actor);

        return $artifact;
    }

    public function delete(Artifact $artifact, User $actor): void
    {
        $artifact->delete();
        $this->audit->log('admin.artifact.delete', $artifact, ['slug' => $artifact->slug], $actor);
    }

    public function addImage(Artifact $artifact, UploadedFile $file, array $meta, User $actor): MediaAsset
    {
        return app(MediaWriter::class)->addImage($artifact, $file, $meta, $actor);
    }

    public function removeMedia(MediaAsset $media, User $actor): void
    {
        app(MediaWriter::class)->remove($media, $actor);
    }
}
