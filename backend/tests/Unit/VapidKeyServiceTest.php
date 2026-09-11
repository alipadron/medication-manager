<?php

use App\Services\VapidKeyService;

it('generates a VAPID keypair with url-safe base64 keys of the right size', function () {
    $pair = (new VapidKeyService)->generate();

    // Public is the 65-byte uncompressed P-256 point (0x04 || X || Y).
    expect(strlen(base64_decode(strtr($pair['public_key'], '-_', '+/'), true)))->toBe(65)
        ->and($pair['public_key'])->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and(str_contains($pair['public_key'], '='))->toBeFalse();

    // Private is the 32-byte scalar D.
    expect(strlen(base64_decode(strtr($pair['private_key'], '-_', '+/'), true)))->toBe(32)
        ->and(str_contains($pair['private_key'], '='))->toBeFalse();

    // A fresh pair differs from a previous one.
    expect((new VapidKeyService)->generate())->not->toEqual($pair);
});
