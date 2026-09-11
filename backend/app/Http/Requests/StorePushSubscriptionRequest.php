<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePushSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A subscription is the browser's well-formed web-push payload: an HTTPS
     * endpoint (unique per registration) plus the two VAPID encryption keys
     * encoded in url-safe base64. The keys' byte lengths are checked in
     * `after()` because the shape (p256dh = 65-byte point, auth = 16-byte
     * secret) cannot be expressed as a single rule.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', Rule::exists('people', 'id')],
            'endpoint' => [
                'required',
                'string',
                'url',
                'max:512',
                Rule::unique('push_subscriptions', 'endpoint'),
            ],
            'p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]+={0,2}$/'],
            'auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]+={0,2}$/'],
        ];
    }

    /**
     * Validate the cryptographic shape of the VAPID keys after the base rules
     * pass. Rejects well-formed-looking base64 that decodes to the wrong size.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['p256dh', 'auth'])) {
                    return;
                }

                if (! $this->decodesToLength($this->string('p256dh')->toString(), 65)) {
                    $validator->errors()->add('p256dh', 'The p256dh key must decode to a 65-byte ECDH public key.');
                }

                if (! $this->decodesToLength($this->string('auth')->toString(), 16)) {
                    $validator->errors()->add('auth', 'The auth secret must decode to a 16-byte key.');
                }
            },
        ];
    }

    /**
     * Whether a url-safe base64 string decodes (strictly) to a fixed length.
     */
    private function decodesToLength(string $value, int $bytes): bool
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded !== false && strlen($decoded) === $bytes;
    }
}
