<?php

declare(strict_types=1);

namespace App\Domain\Procedure;

/**
 * Validates and normalizes procedure step payloads by type.
 */
final class StepPayloadValidator
{
    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: list<string>, payload: array<string, mixed>}
     */
    public function validate(StepType $type, array $input): array
    {
        $errors = [];
        $payload = [];

        foreach ($type->fields() as $field) {
            $name = $field['name'];
            $raw = $input[$name] ?? null;

            if ($field['type'] === 'checkbox') {
                $payload[$name] = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $raw;
                continue;
            }

            if ($field['type'] === 'number') {
                if ($raw === null || $raw === '') {
                    if ($field['required']) {
                        $errors[] = sprintf('Le champ « %s » est obligatoire.', $field['label']);
                    }
                    $payload[$name] = null;
                    continue;
                }
                if (!is_numeric($raw)) {
                    $errors[] = sprintf('Le champ « %s » doit être numérique.', $field['label']);
                    $payload[$name] = null;
                    continue;
                }
                $payload[$name] = (int) $raw;
                continue;
            }

            $value = is_string($raw) ? trim($raw) : (is_scalar($raw) ? (string) $raw : '');
            if ($name === 'command' && $value !== '') {
                $value = self::unwrapPathQuotes($value);
            }

            if ($field['required'] && $value === '') {
                $errors[] = sprintf('Le champ « %s » est obligatoire.', $field['label']);
            }

            if ($field['type'] === 'select' && $value !== '' && isset($field['options']) && !in_array($value, $field['options'], true)) {
                $errors[] = sprintf('Valeur invalide pour « %s ».', $field['label']);
            }

            $payload[$name] = $value === '' ? null : $value;
        }

        // PowerShell: at least script_body or script_path
        if ($type === StepType::Powershell) {
            $body = $payload['script_body'] ?? null;
            $path = $payload['script_path'] ?? null;
            if (($body === null || $body === '') && ($path === null || $path === '')) {
                $errors[] = 'Fournissez un script inline ou un chemin de script.';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'payload' => $payload,
        ];
    }

    private static function unwrapPathQuotes(string $path): string
    {
        $path = trim($path);
        while (strlen($path) >= 2) {
            $a = $path[0];
            $b = $path[strlen($path) - 1];
            if (($a === '"' && $b === '"') || ($a === "'" && $b === "'")) {
                $path = trim(substr($path, 1, -1));
                continue;
            }
            break;
        }

        return $path;
    }
}
