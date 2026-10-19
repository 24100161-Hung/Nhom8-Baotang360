<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaStoreRequest;
use App\Models\Artifact;
use App\Models\MediaAsset;
use App\Models\Scene;
use App\Services\Admin\MediaWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Ảnh dùng chung cho form hiện vật / không gian 360 (MediaManager). */
class MediaController extends Controller
{
    private const MODELS = ['artifact' => Artifact::class, 'scene' => Scene::class];

    public function __construct(private readonly MediaWriter $writer)
    {
    }

    public function store(MediaStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $subject = self::MODELS[$data['subject_type']]::query()->findOrFail((int) $data['subject_id']);
        $this->writer->addImage($subject, $request->file('file'), $data, $request->user());

        return back()->with('success', __('admin.image_uploaded'));
    }

    public function destroy(Request $request, MediaAsset $media): RedirectResponse
    {
        $this->authorize('manage-content');
        abort_unless(array_key_exists((string) $media->subject_type, self::MODELS), 404);
        $this->writer->remove($media, $request->user());

        return back()->with('success', __('admin.image_deleted'));
    }
}
