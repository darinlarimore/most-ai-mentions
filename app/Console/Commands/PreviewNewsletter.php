<?php

namespace App\Console\Commands;

use App\Mail\WeeklyNewsletter;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class PreviewNewsletter extends Command
{
    protected $signature = 'app:preview-newsletter {email? : Email address to send the test to}';

    protected $description = 'Compile and send a test newsletter to a specific email address';

    public function handle(NewsletterService $newsletterService): int
    {
        $email = $this->argument('email');

        if (! $email) {
            $email = $this->ask('Which email address should receive the test newsletter?');
        }

        $this->info('Compiling newsletter edition...');

        $edition = $newsletterService->compileWeeklyEdition();

        $subscriber = NewsletterSubscriber::where('email', $email)->first()
            ?? NewsletterSubscriber::factory()->make(['email' => $email]);

        $this->info("Sending test newsletter to {$email}...");

        Mail::to($email)->send(new WeeklyNewsletter($edition, $subscriber));

        $this->info('Test newsletter sent! Check your inbox.');

        return self::SUCCESS;
    }
}
