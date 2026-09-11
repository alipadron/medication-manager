<?php

namespace App\Services;

use RuntimeException;

/**
 * Generates and encodes the ECDSA P-256 keypair used for VAPID-signed web
 * push. The public key is the 65-byte uncompressed point (0x04 || X || Y)
 * and the private key the 32-byte scalar D, both url-safe base64 — the exact
 * formats a browser's `applicationServerKey` and a push vendor expect.
 */
class VapidKeyService
{
    /**
     * Generate a fresh VAPID keypair.
     *
     * @return array{public_key: string, private_key: string} url-safe base64, no padding
     */
    public function generate(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            throw new RuntimeException('Failed to create a P-256 keypair. Is the openssl extension available?');
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false || ! isset($details['ec'])) {
            throw new RuntimeException('Failed to read the P-256 keypair details.');
        }

        $point = "\x04".$details['ec']['x'].$details['ec']['y'];

        return [
            'public_key' => $this->base64Url($point),
            'private_key' => $this->base64Url($details['ec']['d']),
        ];
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
