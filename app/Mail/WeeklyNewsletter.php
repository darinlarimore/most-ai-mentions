<?php

namespace App\Mail;

use App\Models\NewsletterEdition;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyNewsletter extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterEdition $edition,
        public readonly NewsletterSubscriber $subscriber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->edition->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.weekly-newsletter',
            with: [
                'topSites' => $this->edition->top_sites,
                'weekStart' => $this->edition->week_start,
                'weekEnd' => $this->edition->week_end,
                'unsubscribeUrl' => route('newsletter.unsubscribe', $this->subscriber->token),
            ],
        );
    }
}
