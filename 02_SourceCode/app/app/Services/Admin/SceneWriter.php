<?php

namespace App\Services\Admin;

use App\Models\MediaAsset;
use App\Models\Scene;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TileService;
use App\Support\UploadPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Tạo/sửa/xóa không gian 360°, ảnh toàn cảnh (kích hoạt sinh tiles) và audio guide. */
class SceneWriter
{
    use SyncsInterpretations;

    public const FIELDS = [
        'slug', 'code', 'floor', 'sort_order', 'hfov_default', 'pitch_default', 'yaw_default', 'minimap_x', 'minimap_y',
        'north_offset', 'status', 'source_name', 'source_url', 'author', 'license', 'is_simulated',
    ];

    public function __construct(private readonly AuditLogger $audit, private readonly TileService $tiles)
    {
    }

    public function save(?Scene $scene, array $data, User $actor): Scene
    {
        $isNew = $scene === null;
        $scene = DB::transaction(function () use ($scene, $data, $actor) {
            $scene ??= new Scene(['museum_id' => $this->museumId(), 'tiles_state' => 'none']);
            $values = array_filter(array_intersect_key($data, array_flip(self::FIELDS)), fn ($v) => $v !== null);
            $scene->fill($values)->save();
            $this->syncInterpretations($scene, $data['interpretations'] ?? [], $actor->id);
            // Form không gửi tag_ids → giữ nguyên thẻ hiện có
            if (array_key_exists('tag_ids', $data)) {
                $scene->tags()->sync($data['tag_ids'] ?? []);
            }

            return $scene;
        });
        $this->audit->log($isNew ? 'admin.scene.create' : 'admin.scene.update', $scene, ['slug' => $scene->slug], $actor);

        return $scene;
    }

    /** Xóa mềm; scene đang dùng trong tour thì không cho xóa (trả false). */
    public function delete(Scene $scene, User $actor): bool
    {
        if ($scene->tourSteps()->exists()) {
            return false;
        }
        $scene->delete();
        $this->audit->log('admin.scene.delete', $scene, ['slug' => $scene->slug], $actor);

        return true;
    }

    /** Lưu ảnh equirectangular mới, xóa tiles cũ, yêu cầu Python sinh tiles. Trả true nếu Python nhận việc. */
    public function setPanorama(Scene $scene, UploadedFile $file, array $meta, User $actor): bool
    {
        $old = $scene->panorama_path;
        $path = $file->storeAs('panoramas', $scene->code.'-'.Str::lower(Str::random(10)).'.jpg', 'public');
        $scene->forceFill(array_filter([
            'panorama_path' => $path, 'tiles_path' => null, 'tile_config' => null, 'tiles_state' => 'pending',
            'source_name' => $meta['source_name'] ?? null, 'source_url' => $meta['source_url'] ?? null, 'author' => $meta['author'] ?? null,
        ], fn ($v) => $v !== null) + ['tiles_path' => null, 'tile_config' => null])->save();
        if ($old && $old !== $path && str_starts_with($old, 'panoramas/')) {
            Storage::disk('public')->delete($old);
        }
        $this->audit->log('admin.scene.panorama', $scene, ['path' => $path], $actor);

        return $this->tiles->request($scene);
    }

    /** Thêm audio guide; thay thế audio cùng ngôn ngữ đã có. */
    public function addAudio(Scene $scene, UploadedFile $file, array $data, User $actor): MediaAsset
    {
        $path = $file->storeAs('audio/scenes/'.$scene->id, UploadPolicy::randomName('mp3'), 'public');
        $media = DB::transaction(function () use ($scene, $file, $data, $actor, $path) {
            $scene->media()->where('media_type', 'audio')->where('locale', $data['locale'])->get()
                ->each(fn (MediaAsset $m) => $this->deleteMediaFile($m));
            $media = $scene->media()->create([
                'media_type' => 'audio', 'locale' => $data['locale'], 'path' => $path, 'mime' => 'audio/mpeg',
                'size_bytes' => $file->getSize(), 'duration_sec' => $data['duration_sec'] ?? null,
                'license' => $data['license'] ?? 'simulated', 'source_name' => $data['source_name'] ?? null, 'uploaded_by' => $actor->id,
            ]);
            if (filled($data['transcript'] ?? null) || filled($data['caption'] ?? null)) {
                $media->captions()->create(['locale' => $data['locale'], 'caption' => $data['caption'] ?? null, 'transcript' => $data['transcript'] ?? null]);
            }

            return $media;
        });
        $this->audit->log('admin.scene.audio_upload', $scene, ['media_id' => $media->id, 'locale' => $media->locale], $actor);

        return $media;
    }

    public function removeAudio(MediaAsset $media, User $actor): void
    {
        $this->deleteMediaFile($media);
        $this->audit->log('admin.scene.audio_delete', $media, ['locale' => $media->locale], $actor);
    }

    private function deleteMediaFile(MediaAsset $media): void
    {
        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }
        $media->delete();
    }
}
