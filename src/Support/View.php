<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PHP template renderer with layout support and flash messages.
 */
final class View
{
    private string $basePath;

    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->renderPartial($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): string
    {
        $file = $this->basePath . '/' . ltrim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('View not found: %s', $template));
        }

        extract(array_merge($this->shared, $data), EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
