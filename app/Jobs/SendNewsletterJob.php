<?php

namespace App\Jobs;

use App\Mail\WeeklyNewsletter;
use App\Models\NewsletterEdition;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterEdition $edition,
    ) {}

    public function handle(): void
    {
        Log::info("Sending newsletter edition #{$this->edition->id}");

        $subscribers = NewsletterSubscriber::query()
            ->where('is_active', true)
            ->whereNotNull('confirmed_at')
            ->get();

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)
                ->queue(new WeeklyNewsletter($this->edition));
        }

        $this->edition->update([
            'sent_at' => now(),
            'recipient_count' => $subscribers->count(),
        ]);

        Log::info("Newsletter edition #{$this->edition->id} sent to {$subscribers->count()} subscribers");
    }
}
