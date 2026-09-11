<?php

namespace App\Models;

use Database\Factories\PushNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A delivered due-dose notification — the no-spam guard for the delivery
 * loop. One row per (person, due slot): the unique slot_key means a slot is
 * pushed once, no matter how often the scheduled command runs while the
 * dose stays untaken.
 */
#[Fillable(['person_id', 'schedule_id', 'intake_id', 'slot_key'])]
class PushNotification extends Model
{
    /** @use HasFactory<PushNotificationFactory> */
    use HasFactory;

    /**
     * The person whose devices received the notification.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The schedule the notified dose belongs to.
     *
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * The fixed-times intake this notification covered (null for interval
     * meds, whose rolling due slots have no intake grid).
     *
     * @return BelongsTo<Intake, $this>
     */
    public function intake(): BelongsTo
    {
        return $this->belongsTo(Intake::class);
    }
}