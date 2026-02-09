<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscribeRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    /**
     * Subscribe to the newsletter.
     */
    public function subscribe(SubscribeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        NewsletterSubscriber::create([
            'email' => $validated['email'],
            'name' => $validated['name'] ?? null,
            'token' => Str::random(64),
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Successfully subscribed to the newsletter!');
    }

    /**
     * Unsubscribe from the newsletter using a token.
     */
    public function unsubscribe(string $token): Response
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->firstOrFail();

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);

        return Inertia::render('Newsletter/Unsubscribed');
    }
}
