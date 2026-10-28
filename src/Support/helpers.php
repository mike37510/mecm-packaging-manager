<?php

declare(strict_types=1);

use App\Support\I18n;

/**
 * Translate a UI key (nested dot path). Placeholders: :name
 *
 * @param array<string, string|int|float> $replace
 */
function __(string $key, array $replace = []): string
{
    return I18n::instance()->get($key, $replace);
}
