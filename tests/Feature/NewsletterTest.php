<?php

use App\Jobs\SendNewsletterJob;
use App\Mail\WeeklyNewsletter;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use App\Services\NewsletterService;
use Illuminate\Support\Facades\Mail;

it('can subscribe to the newsletter', function () {
    $this->post('/newsletter/subscribe', [
        'email' => 'test@example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'test@example.com',
        'is_active' => true,
    ]);
});

it('can resubscribe after unsubscribing', function () {
    $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create([
        'email' => 'returning@example.com',
    ]);

    $this->post('/newsletter/subscribe', [
        'email' => 'returning@example.com',
    ])->assertRedirect();

    $subscriber->refresh();
    expect($subscriber->is_active)->toBeTrue();
    expect($subscriber->unsubscribed_at)->toBeNull();
});

it('can unsubscribe via token', function () {
    $subscriber = NewsletterSubscriber::factory()->create();

    $this->get("/newsletter/unsubscribe/{$subscriber->token}")
        ->assertSuccessful();

    $subscriber->refresh();
    expect($subscriber->is_active)->toBeFalse();
    expect($subscriber->unsubscribed_at)->not->toBeNull();
});

it('validates email is required for subscription', function () {
    $this->post('/newsletter/subscribe', [
        'email' => '',
    ])->assertSessionHasErrors('email');
});

it('compiles a weekly edition with top sites', function () {
    Site::factory()->count(3)->create([
        'status' => 'crawled',
        'crawl_count' => 1,
        'hype_score' => 500,
        'is_active' => true,
    ]);

    $service = app(NewsletterService::class);
    $edition = $service->compileWeeklyEdition();

    expect($edition->subject)->toContain('AI Hype Leaderboard');
    expect($edition->top_sites)->toHaveCount(3);
    expect($edition->week_start)->not->toBeNull();
    expect($edition->week_end)->not->toBeNull();
});

it('sends newsletter to active confirmed subscribers only', function () {
    Mail::fake();

    $active = NewsletterSubscriber::factory()->create();
    NewsletterSubscriber::factory()->unsubscribed()->create();
    NewsletterSubscriber::factory()->unconfirmed()->create();

    Site::factory()->create([
        'status' => 'crawled',
        'crawl_count' => 1,
        'hype_score' => 100,
        'is_active' => true,
    ]);

    $service = app(NewsletterService::class);
    $edition = $service->compileWeeklyEdition();

    $job = new SendNewsletterJob($edition);
    $job->handle();

    Mail::assertQueued(WeeklyNewsletter::class, 1);
    Mail::assertQueued(WeeklyNewsletter::class, function ($mail) use ($active) {
        return $mail->hasTo($active->email);
    });

    $edition->refresh();
    expect($edition->sent_at)->not->toBeNull();
    expect($edition->subscriber_count)->toBe(1);
});
