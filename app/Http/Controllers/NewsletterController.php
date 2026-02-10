<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscribeRequest;
use App\Services\NewsletterService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    /**
     * Subscribe to the newsletter.
     */
    public function subscribe(SubscribeRequest $request, NewsletterService $service): RedirectResponse
    {
        $validated = $request->validated();

        $service->subscribe(
            $validated['email'],
            $validated['name'] ?? null,
            $request->user(),
        );

        return back()->with('success', 'Successfully subscribed to the newsletter!');
    }

    /**
     * Unsubscribe from the newsletter using a token.
     */
    public function unsubscribe(string $token, NewsletterService $service): Response
    {
        $service->unsubscribe($token);

        return Inertia::render('Newsletter/Unsubscribed');
    }
}
