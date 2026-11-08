<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public readonly string $method;
    public readonly string $path;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        $this->path = $uri === '/index.php' ? '/' : $uri;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /** Trimmed string from POST ('' if missing or not a string). */
    public function str(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    public function allPost(): array
    {
        return $_POST;
    }

    public function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $f : null;
    }
}
