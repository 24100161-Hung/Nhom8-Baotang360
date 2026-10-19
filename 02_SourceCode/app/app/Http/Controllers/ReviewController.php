<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewStoreRequest;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function store(ReviewStoreRequest $request, ReviewService $reviews): RedirectResponse
    {
        $reviews->store($request->user(), $request->validated());

        return back()->with('success', __('messages.reviews.submitted'));
    }
}
