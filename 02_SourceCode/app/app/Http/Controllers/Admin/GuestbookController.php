<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuestbookModerationRequest;
use App\Models\GuestbookEntry;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Duyệt/ẩn/xóa lời trong sổ lưu bút. */
class GuestbookController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-content');
        $filters = $this->filters($request, ['status', 'q']);
        $page = GuestbookEntry::query()->with('user:id,name,email')
            ->when(in_array($filters['status'], GuestbookEntry::STATUSES, true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('message', 'like', like_contains($v))->orWhere('display_name', 'like', like_contains($v))))
            ->latest('id')->paginate(30)->withQueryString()
            ->through(fn (GuestbookEntry $e) => [
                'id' => $e->id, 'display_name' => $e->display_name, 'message' => $e->message, 'locale' => $e->locale,
                'status' => $e->status, 'status_label' => __('enums.guestbook_statuses.'.$e->status),
                'user' => $e->user ? ['name' => $e->user->name, 'email' => $e->user->email] : null,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Guestbook/Index', ['entries' => $page->toArray(), 'filters' => $filters]);
    }

    public function update(GuestbookModerationRequest $request, GuestbookEntry $entry, AuditLogger $audit): RedirectResponse
    {
        $status = $request->validated('status');
        $entry->forceFill(['status' => $status, 'approved_by' => $status === 'approved' ? $request->user()->id : $entry->approved_by])->save();
        $audit->log('admin.guestbook.'.$status, $entry);

        return back()->with('success', __('admin.guestbook_updated'));
    }

    public function destroy(GuestbookEntry $entry, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage-content');
        $entry->delete();
        $audit->log('admin.guestbook.delete', $entry);

        return back()->with('success', __('admin.deleted'));
    }
}
