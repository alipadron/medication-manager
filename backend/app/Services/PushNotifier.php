<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PushSubscription;
use App\Notifications\WebPushNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\PushSubscription as PackagePushSubscription;

/**
 * Sends a web push to every enrolled device of a person through the
 * laravel-notification-channels/webpush channel. Our own person-scoped
 * push_subscriptions rows (8a) are mapped onto the package's subscription
 * model at the send boundary only — the package model is a transient DTO,
 * never persisted, so the two schemas never collide.
 */
class PushNotifier
{
    /**
     * Send a push notification to all of the person's enrolled devices.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToPerson(Person $person, string $title, string $body, array $data = []): int
    {
        $subscriptions = $person->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        Notification::route('WebPush', $this->packageSubscriptions($subscriptions))
            ->notifyNow(new WebPushNotification($title, $body, $data));

        return $subscriptions->count();
    }

    /**
     * Map our stored subscriptions onto the channel's transient model.
     *
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @return Collection<int, PackagePushSubscription>
     */
    private function packageSubscriptions(Collection $subscriptions): Collection
    {
        return $subscriptions->map(
            fn (PushSubscription $subscription): PackagePushSubscription => new PackagePushSubscription([
                'endpoint' => $subscription->endpoint,
                'public_key' => $subscription->p256dh,
                'auth_token' => $subscription->auth,
            ]),
        );
    }
}
