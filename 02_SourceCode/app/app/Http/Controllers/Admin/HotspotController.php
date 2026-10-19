<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HotspotRequest;
use App\Models\Hotspot;
use App\Models\Scene;
use App\Services\Admin\HotspotWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** CRUD điểm nóng trên ảnh 360 theo pitch/yaw (FR-40). Hotspot không thuộc scene trên URL → 404. */
class HotspotController extends Controller
{
    public function __construct(private readonly HotspotWriter $writer)
    {
    }

    public function store(HotspotRequest $request, Scene $scene): RedirectResponse
    {
        $this->writer->save($scene, null, $request->validated(), $request->user());

        return back()->with('success', __('admin.hotspot_saved'));
    }

    public function update(HotspotRequest $request, Scene $scene, Hotspot $hotspot): RedirectResponse
    {
        $this->ensureBelongs($scene, $hotspot);
        $this->writer->save($scene, $hotspot, $request->validated(), $request->user());

        return back()->with('success', __('admin.hotspot_saved'));
    }

    public function destroy(Request $request, Scene $scene, Hotspot $hotspot): RedirectResponse
    {
        $this->authorize('manage-content');
        $this->ensureBelongs($scene, $hotspot);
        $this->writer->delete($hotspot, $request->user());

        return back()->with('success', __('admin.hotspot_deleted'));
    }

    private function ensureBelongs(Scene $scene, Hotspot $hotspot): void
    {
        abort_unless($hotspot->scene_id === $scene->id, 404);
    }
}
