<?php

namespace App\Services\Admin;

use App\Models\Hotspot;
use App\Models\Scene;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/** Ghi điểm nóng (info/scene/artifact) và bản dịch vi/en. */
class HotspotWriter
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function save(Scene $scene, ?Hotspot $hotspot, array $data, User $actor): Hotspot
    {
        $isNew = $hotspot === null;
        $hotspot = DB::transaction(function () use ($scene, $hotspot, $data) {
            $hotspot ??= new Hotspot(['scene_id' => $scene->id]);
            $type = $data['type'];
            $hotspot->fill([
                'type' => $type,
                'pitch' => $data['pitch'],
                'yaw' => $data['yaw'],
                'target_scene_id' => $type === 'scene' ? $data['target_scene_id'] : null,
                'target_pitch' => $type === 'scene' ? ($data['target_pitch'] ?? null) : null,
                'target_yaw' => $type === 'scene' ? ($data['target_yaw'] ?? null) : null,
                'target_hfov' => $type === 'scene' ? ($data['target_hfov'] ?? null) : null,
                'artifact_id' => $type === 'artifact' ? $data['artifact_id'] : null,
                'icon' => $data['icon'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                // Form không gửi status → giữ trạng thái hiện có
                'status' => $data['status'] ?? $hotspot->status ?? 'published',
                'image_path' => array_key_exists('image_url', $data) ? $data['image_url'] : $hotspot->image_path,
            ])->save();
            $hotspot->syncTranslations([
                'vi' => ['title' => $data['title_vi'], 'text' => $data['text_vi'] ?? null],
                'en' => ['title' => $data['title_en'], 'text' => $data['text_en'] ?? null],
            ], 'title');

            return $hotspot;
        });
        $this->audit->log($isNew ? 'admin.hotspot.create' : 'admin.hotspot.update', $hotspot, ['scene_id' => $scene->id, 'type' => $hotspot->type], $actor);

        return $hotspot;
    }

    public function delete(Hotspot $hotspot, User $actor): void
    {
        $hotspot->delete();
        $this->audit->log('admin.hotspot.delete', $hotspot, ['scene_id' => $hotspot->scene_id], $actor);
    }
}
