<?php

namespace App\Console\Commands;

use App\Jobs\SendNewsletterJob;
use App\Services\NewsletterService;
use Illuminate\Console\Command;

class SendWeeklyNewsletter extends Command
{
    protected $signature = 'app:send-newsletter';

    protected $description = 'Compile and send the weekly newsletter to all active subscribers';

    public function handle(NewsletterService $newsletterService): int
    {
        $this->info('Compiling weekly newsletter...');

        $edition = $newsletterService->compileWeeklyEdition();

        if ($edition === null) {
            $this->warn('No newsletter content available for this week. Skipping.');

            return self::SUCCESS;
        }

        SendNewsletterJob::dispatch($edition);

        $this->info("Newsletter edition #{$edition->id} has been queued for delivery.");

        return self::SUCCESS;
    }
}
