<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared helpers for JSON-backed repositories.
 */
trait JsonTimestamps
{
    private function now(): string
    {
        return (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }
}
