<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactUpdateRequest;
use App\Models\ContactMessage;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tin nhắn liên hệ: đã đọc/đã trả lời (FR-47, chỉ admin). */
class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-commerce');
        $filters = $this->filters($request, ['status', 'q']);
        $page = ContactMessage::query()->with('replier:id,name')
            ->when(in_array($filters['status'], ContactMessage::STATUSES, true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w->where('subject', 'like', like_contains($v))
                ->orWhere('email', 'like', like_contains($v))->orWhere('name', 'like', like_contains($v))))
            ->latest('id')->paginate(20)->withQueryString()
            ->through(fn (ContactMessage $m) => [
                'id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'phone' => $m->phone, 'subject' => $m->subject,
                'body' => $m->body, 'status' => $m->status, 'status_label' => __('enums.contact_statuses.'.$m->status),
                'reply_note' => $m->reply_note, 'replied_by' => $m->replier?->name,
                'replied_at' => $m->replied_at?->toIso8601String(), 'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Contacts/Index', [
            'messages' => $page->toArray(),
            'filters' => $filters,
            'counts' => ContactMessage::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(ContactUpdateRequest $request, ContactMessage $message, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $values = ['status' => $data['status']];
        if ($data['status'] === 'replied') {
            $values += ['reply_note' => $data['reply_note'], 'replied_by' => $request->user()->id, 'replied_at' => now()];
        }
        $message->forceFill($values)->save();
        $audit->log('admin.contact.'.$data['status'], $message);

        return back()->with('success', __('admin.contact_updated'));
    }
}
