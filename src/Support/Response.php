<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Simple HTTP response helpers.
 */
final class Response
{
    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function download(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream',
    ): never {
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
        header('Content-Length: ' . (string) strlen($content));
        header('Cache-Control: no-store');
        echo $content;
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        if ($message === '') {
            $message = match ($status) {
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                default => 'Error',
            };
        }
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        exit;
    }
}
