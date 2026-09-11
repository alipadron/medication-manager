<?php

namespace App\Services;

use App\Models\PushNotification;
use App\Models\Schedule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * The due-dose delivery loop: for every schedule, push the due slots to the
 * person's enrolled devices. The push_notifications log (unique slot_key)
 * guarantees each slot is delivered once, no matter how often the scheduler
 * ticks while a dose stays untaken.
 */
class DueDoseNotifier
{
    public function __construct(
        private DueDoseFinder $finder,
        private PushNotifier $notifier,
    ) {}

    /**
     * Push every currently-due dose, once per slot.
     *
     * @return array{notified: int, already_notified: int}
     */
    public function notifyDueDoses(?CarbonInterface $now = null): array
    {
        $notified = 0;
        $alreadyNotified = 0;

        foreach ($this->schedules() as $schedule) {
            foreach ($this->finder->dueDoses($schedule, $now) as $due) {
                if ($this->alreadyNotified($due->slotKey)) {
                    $alreadyNotified++;

                    continue;
                }

                $sent = $this->notifier->sendToPerson(
                    $schedule->medication->person,
                    'Medication time',
                    'Time for '.$schedule->medication->name.' — '.$schedule->medication->person->name,
                    ['type' => 'due_dose', 'schedule_id' => $schedule->id],
                );

                if ($sent === 0) {
                    // Nobody enrolled yet: nothing was delivered, so nothing
                    // to mark — an enrollment later in the day still gets
                    // the (still due) slot.
                    continue;
                }

                $this->markNotified($due, $schedule);

                $notified++;
            }
        }

        return ['notified' => $notified, 'already_notified' => $alreadyNotified];
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function schedules(): Collection
    {
        return Schedule::with('medication.person')->get();
    }

    /**
     * Whether this due slot already has a delivery on record.
     */
    private function alreadyNotified(string $slotKey): bool
    {
        return PushNotification::where('slot_key', $slotKey)->exists();
    }

    /**
     * Persist the no-spam marker for a delivered slot.
     */
    private function markNotified(DueDose $due, Schedule $schedule): void
    {
        PushNotification::create([
            'person_id' => $schedule->medication->person_id,
            'schedule_id' => $schedule->id,
            'intake_id' => $due->intakeId,
            'slot_key' => $due->slotKey,
        ]);
    }
}
