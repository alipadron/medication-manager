<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser push registration enrolled for a person. Holds everything the
 * eventual delivery loop needs to reach a device: the push endpoint plus
 * the VAPID encryption keys (p256dh/auth).
 *
 * @see CONTEXT.md (person)
 */
#[Fillable(['person_id', 'endpoint', 'p256dh', 'auth'])]
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    /**
     * The person this subscription belongs to (one subscriber, many devices).
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
