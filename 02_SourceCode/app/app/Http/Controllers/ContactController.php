<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactStoreRequest;
use App\Services\Catalog\CatalogService;
use App\Services\ContactService;
use App\Support\BusinessLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(CatalogService $catalog): Response
    {
        return Inertia::render('Contact/Create', ['museum' => $catalog->museum()]);
    }

    public function store(ContactStoreRequest $request, ContactService $contacts): RedirectResponse
    {
        $data = $request->validated();
        // Honeypot: bot điền trường ẩn "website" → báo thành công giả, không lưu
        if (filled($data['website'] ?? null)) {
            BusinessLog::warning('contact.honeypot');
        } else {
            $contacts->store($data, $request->ip());
        }

        return back()->with('success', __('messages.contact.sent'));
    }
}
