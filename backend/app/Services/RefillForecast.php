<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * The derived refill forecast for one package: what remains, how fast the
 * medication's schedule consumes it, and when the package empties.
 */
class RefillForecast
{
    public function __construct(
        public readonly int $dosesRemaining,
        public readonly float $dosesPerDay,
        public readonly float $daysOfSupplyLeft,
        public readonly CarbonImmutable $refillBy,
        public readonly string $status,
        public readonly string $message,
    ) {}
}
