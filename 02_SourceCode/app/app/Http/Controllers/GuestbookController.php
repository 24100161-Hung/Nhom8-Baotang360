<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestbookStoreRequest;
use App\Services\GuestbookService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GuestbookController extends Controller
{
    public function index(GuestbookService $guestbook): Response
    {
        return Inertia::render('Guestbook/Index', ['entries' => $guestbook->approved()->toArray()]);
    }

    public function store(GuestbookStoreRequest $request, GuestbookService $guestbook): RedirectResponse
    {
        $data = $request->validated();
        if (blank($data['website'] ?? null)) {
            $guestbook->store($data, $request->user());
        }

        return back()->with('success', __('messages.guestbook.submitted'));
    }
}
