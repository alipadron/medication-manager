<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Produces well-formed web-push values: the endpoint points at a real
     * push vendor and the keys are the correct lengths (p256dh = 65-byte
     * uncompressed EC point, auth = 16-byte secret), base64url-encoded.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.
                str_replace('=', '', base64_encode(fake()->uuid().fake()->uuid())),
            'p256dh' => $this->base64Url(random_bytes(65)),
            'auth' => $this->base64Url(random_bytes(16)),
        ];
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
