<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Category;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Persistence for software categories (JSON file store).
 */
final class CategoryRepository
{
    use JsonTimestamps;

    private JsonCollection $categories;

    public function __construct(JsonStore $store)
    {
        $this->categories = $store->collection('categories');
    }

    public function findById(int $id): ?Category
    {
        $row = $this->categories->find($id);

        return $row ? Category::fromRow($row) : null;
    }

    public function findByName(string $name): ?Category
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }
        $row = $this->categories->first(
            static fn (array $r): bool => mb_strtolower(trim((string) ($r['name'] ?? ''))) === $needle
        );

        return $row ? Category::fromRow($row) : null;
    }

    /**
     * @return list<Category>
     */
    public function all(): array
    {
        $rows = $this->categories->all();
        usort(
            $rows,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return array_map(static fn (array $r) => Category::fromRow($r), $rows);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (Category $c): string => $c->name, $this->all());
    }

    public function exists(string $name): bool
    {
        return $this->findByName($name) !== null;
    }

    public function count(): int
    {
        return count($this->categories->all());
    }

    /**
     * Seed empty store from distinct software categories (one-shot bootstrap).
     *
     * @param list<string> $names
     */
    public function seedIfEmpty(array $names): void
    {
        if ($this->count() > 0) {
            return;
        }
        $seen = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->create($name);
        }
    }

    public function create(string $name): int
    {
        $name = trim($name);
        $now = $this->now();

        return $this->categories->insert([
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, string $name): void
    {
        $row = $this->categories->find($id);
        if ($row === null) {
            return;
        }
        $row['name'] = trim($name);
        $row['updated_at'] = $this->now();
        $this->categories->put($id, $row);
    }

    public function delete(int $id): void
    {
        $this->categories->delete($id);
    }
}
