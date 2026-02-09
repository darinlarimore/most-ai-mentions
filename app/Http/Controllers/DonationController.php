<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

class DonationController extends Controller
{
    /**
     * Display the donation page.
     */
    public function index(): Response
    {
        return Inertia::render('Donate/Index');
    }

    /**
     * Create a Stripe Checkout session for a one-time donation.
     */
    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Donation to Most AI Mentions',
                    ],
                    'unit_amount' => $request->integer('amount'),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('donate.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('donate'),
            'metadata' => [
                'user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json(['url' => $session->url]);
    }

    /**
     * Display the donation success page.
     */
    public function success(): Response
    {
        return Inertia::render('Donate/Success');
    }

    /**
     * Handle Stripe webhook for completed payments.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret'),
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            Donation::create([
                'stripe_payment_id' => $session->payment_intent,
                'amount' => $session->amount_total,
                'currency' => $session->currency,
                'status' => 'completed',
                'user_id' => $session->metadata->user_id ?? null,
                'donor_email' => $session->customer_details->email ?? null,
                'donor_name' => $session->customer_details->name ?? null,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
