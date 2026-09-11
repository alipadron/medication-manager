<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePushNudgeRequest;
use App\Models\Person;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;

/**
 * "Nudge partner": ping the other household member's enrolled devices with
 * a short message. User-initiated on purpose — nudges are not deduped like
 * due-dose slots; each call is a fresh, intentional reminder.
 */
class PushNudgeController extends Controller
{
    public function __invoke(
        StorePushNudgeRequest $request,
        PushNotifier $notifier,
    ): JsonResponse {
        $person = Person::findOrFail($request->validated('person_id'));

        $notified = $notifier->sendToPerson(
            $person,
            'Nudge from your partner 👋',
            'Time to check your meds, '.$person->name.'.',
            ['type' => 'nudge', 'person_id' => $person->id],
        );

        return response()->json([
            'data' => [
                'person_id' => $person->id,
                'notified' => $notified,
            ],
        ], 202);
    }
}
