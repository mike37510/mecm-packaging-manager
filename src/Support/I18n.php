<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Simple FR/EN translator (PHP array catalogs in /lang).
 */
final class I18n
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $catalog = [];

    private string $locale = 'fr';

    private string $fallback = 'fr';

    private string $langPath;

    public function __construct(string $langPath, string $locale = 'fr', string $fallback = 'fr')
    {
        $this->langPath = rtrim($langPath, '/');
        $this->fallback = $this->normalize($fallback);
        $this->setLocale($locale);
        self::$instance = $this;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('I18n not bootstrapped.');
        }

        return self::$instance;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $this->normalize($locale);
        $this->catalog = $this->load($this->locale);
        if ($this->locale !== $this->fallback) {
            $fallback = $this->load($this->fallback);
            $this->catalog = $this->mergeRecursive($fallback, $this->catalog);
        }
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    public function get(string $key, array $replace = []): string
    {
        $value = $this->resolve($key);
        if ($value === null) {
            $value = $key;
        }

        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * Flat map for front-end (js.* keys).
     *
     * @return array<string, string>
     */
    public function jsDictionary(): array
    {
        $js = $this->resolve('js');
        if (!is_array($js)) {
            return [];
        }

        $out = [];
        $flatten = static function (array $node, string $prefix) use (&$flatten, &$out): void {
            foreach ($node as $k => $v) {
                $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;
                if (is_array($v)) {
                    $flatten($v, $path);
                } elseif (is_string($v)) {
                    $out[$path] = $v;
                }
            }
        };
        $flatten($js, '');

        return $out;
    }

    private function normalize(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return $locale === 'en' ? 'en' : 'fr';
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $locale): array
    {
        $file = $this->langPath . '/' . $locale . '.php';
        if (!is_readable($file)) {
            return [];
        }

        $data = require $file;

        return is_array($data) ? $data : [];
    }

    private function resolve(string $key): mixed
    {
        $parts = explode('.', $key);
        $node = $this->catalog;
        foreach ($parts as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return null;
            }
            $node = $node[$part];
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = $this->mergeRecursive($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }
}
