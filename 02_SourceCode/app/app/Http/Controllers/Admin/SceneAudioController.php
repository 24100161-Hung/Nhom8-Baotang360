<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SceneAudioRequest;
use App\Models\MediaAsset;
use App\Models\Scene;
use App\Services\Admin\SceneWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Audio guide MP3 theo ngôn ngữ cho từng không gian (FR-42). */
class SceneAudioController extends Controller
{
    public function __construct(private readonly SceneWriter $writer)
    {
    }

    public function store(SceneAudioRequest $request, Scene $scene): RedirectResponse
    {
        $this->writer->addAudio($scene, $request->file('file'), $request->validated(), $request->user());

        return back()->with('success', __('admin.audio_uploaded'));
    }

    public function destroy(Request $request, Scene $scene, MediaAsset $media): RedirectResponse
    {
        $this->authorize('manage-content');
        abort_unless($media->subject_type === 'scene' && (int) $media->subject_id === $scene->id && $media->media_type === 'audio', 404);
        $this->writer->removeAudio($media, $request->user());

        return back()->with('success', __('admin.audio_deleted'));
    }
}
