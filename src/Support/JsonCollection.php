<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One JSON collection file: storage/data/{name}.json
 *
 * Format: { "next_id": int, "items": { "1": {...}, ... } }
 */
final class JsonCollection
{
    private string $file;

    public function __construct(
        string $dataPath,
        private readonly string $name,
    ) {
        $this->file = rtrim($dataPath, '/') . '/' . $name . '.json';
        if (!is_file($this->file)) {
            $this->writeAtomic(['next_id' => 1, 'items' => new \stdClass()]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $data = $this->read();
        $items = array_values($data['items']);

        return array_map(static fn ($row) => is_array($row) ? $row : [], $items);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $data = $this->read();
        $row = $data['items'][(string) $id] ?? null;

        return is_array($row) ? $row : null;
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<array<string, mixed>>
     */
    public function filter(callable $predicate): array
    {
        $out = [];
        foreach ($this->all() as $row) {
            if ($predicate($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return array<string, mixed>|null
     */
    public function first(callable $predicate): ?array
    {
        foreach ($this->all() as $row) {
            if ($predicate($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        return $this->mutate(function (array &$data) use ($row): int {
            $id = (int) $data['next_id'];
            $data['next_id'] = $id + 1;
            $row['id'] = $id;
            $data['items'][(string) $id] = $row;

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    public function put(int $id, array $row): void
    {
        $this->mutate(function (array &$data) use ($id, $row): int {
            $row['id'] = $id;
            $data['items'][(string) $id] = $row;
            if ($id >= (int) $data['next_id']) {
                $data['next_id'] = $id + 1;
            }

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function update(int $id, array $patch): void
    {
        $this->mutate(function (array &$data) use ($id, $patch): int {
            $key = (string) $id;
            if (!isset($data['items'][$key]) || !is_array($data['items'][$key])) {
                throw new \RuntimeException("Record {$this->name}#{$id} introuvable.");
            }
            $data['items'][$key] = array_merge($data['items'][$key], $patch, ['id' => $id]);

            return $id;
        });
    }

    public function delete(int $id): void
    {
        $this->mutate(function (array &$data) use ($id): int {
            unset($data['items'][(string) $id]);

            return $id;
        });
    }

    public function count(): int
    {
        return count($this->read()['items']);
    }

    /**
     * @param callable(array{next_id: int, items: array<string, array<string, mixed>>}): mixed $fn
     */
    private function mutate(callable $fn): mixed
    {
        $fp = fopen($this->file, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Impossible d\'ouvrir ' . $this->file);
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException('Lock impossible sur ' . $this->file);
            }
            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = $this->decode(is_string($raw) ? $raw : '');
            $result = $fn($data);
            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($json === false) {
                throw new \RuntimeException('JSON encode failed for ' . $this->name);
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json . "\n");
            fflush($fp);
            flock($fp, LOCK_UN);

            return $result;
        } finally {
            fclose($fp);
        }
    }

    /**
     * @return array{next_id: int, items: array<string, array<string, mixed>>}
     */
    private function read(): array
    {
        $fp = fopen($this->file, 'rb');
        if ($fp === false) {
            return ['next_id' => 1, 'items' => []];
        }
        try {
            flock($fp, LOCK_SH);
            $raw = stream_get_contents($fp) ?: '';
            flock($fp, LOCK_UN);

            return $this->decode($raw);
        } finally {
            fclose($fp);
        }
    }

    /**
     * @return array{next_id: int, items: array<string, array<string, mixed>>}
     */
    private function decode(string $raw): array
    {
        if (trim($raw) === '') {
            return ['next_id' => 1, 'items' => []];
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['next_id' => 1, 'items' => []];
        }

        $items = [];
        foreach (($data['items'] ?? []) as $k => $row) {
            if (!is_array($row)) {
                continue;
            }
            // Prefer embedded id (repairs list-encoded items: [ {... id:2 } ] → { "2": {...} })
            $id = isset($row['id']) && (is_int($row['id']) || (is_string($row['id']) && ctype_digit($row['id'])))
                ? (string) (int) $row['id']
                : (string) $k;
            if ($id === '' || $id === '0') {
                continue;
            }
            $row['id'] = (int) $id;
            $items[$id] = $row;
        }

        $nextId = max(1, (int) ($data['next_id'] ?? 1));
        foreach (array_keys($items) as $id) {
            $nextId = max($nextId, ((int) $id) + 1);
        }

        return [
            'next_id' => $nextId,
            'items' => $items,
        ];
    }

    /**
     * @param array{next_id: int, items: array<string, mixed>|\stdClass} $data
     */
    private function writeAtomic(array $data): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed');
        }
        $tmp = $this->file . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $json . "\n") === false) {
            throw new \RuntimeException('Write failed: ' . $tmp);
        }
        rename($tmp, $this->file);
        @chmod($this->file, 0664);
    }
}
