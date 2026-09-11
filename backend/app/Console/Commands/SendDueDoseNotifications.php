<?php

namespace App\Console\Commands;

use App\Services\DueDoseNotifier;
use Illuminate\Console\Command;

/**
 * The scheduled delivery loop: web-push every person whose doses are due
 * right now. Safe to run every minute — the push_notifications log dedupes
 * per due slot, so a still-untaken dose is not re-pushed on later ticks.
 *
 * Dev: `php artisan schedule:run` (one tick) or `php artisan schedule:work`
 * (foreground worker running all scheduled commands continuously).
 */
class SendDueDoseNotifications extends Command
{
    protected $signature = 'notifications:send-due-doses';

    protected $description = 'Send a web push for every dose that is due now';

    public function handle(DueDoseNotifier $notifier): int
    {
        $result = $notifier->notifyDueDoses();

        $this->info(sprintf(
            '[web-push] %d due slots notified (%d already notified)',
            $result['notified'],
            $result['already_notified'],
        ));

        return self::SUCCESS;
    }
}
