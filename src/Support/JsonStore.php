<?php

declare(strict_types=1);

namespace App\Support;

/**
 * File-backed JSON document store with flock and auto-increment ids.
 */
final class JsonStore
{
    public function __construct(
        private readonly string $dataPath,
    ) {
        if (!is_dir($this->dataPath) && !mkdir($this->dataPath, 0775, true) && !is_dir($this->dataPath)) {
            throw new \RuntimeException('Impossible de créer le dossier data JSON : ' . $this->dataPath);
        }
    }

    public function path(): string
    {
        return $this->dataPath;
    }

    public function collection(string $name): JsonCollection
    {
        return new JsonCollection($this->dataPath, $name);
    }
}
