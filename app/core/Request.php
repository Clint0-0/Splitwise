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

    /** Query-string value as a string; $default when missing or sent as an array (?q[]=x). */
    public function queryStr(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    /** Query-string value as a positive int (digits only, ≤ 9 digits); $default otherwise. */
    public function queryInt(string $key, int $default = 0): int
    {
        $v = $_GET[$key] ?? null;
        return is_string($v) && ctype_digit($v) && strlen($v) <= 9 ? (int) $v : $default;
    }

    /**
     * ?page=N as a safe page number: 1 … 100000. Anything else (missing, array,
     * negative, "abc", 99999999999999999999 which would overflow LIMIT/OFFSET) -> clamped.
     */
    public function page(): int
    {
        $v = $_GET['page'] ?? '1';
        if (!is_string($v) || !ctype_digit($v) || $v === '' || strlen($v) > 6) {
            return is_string($v) && ctype_digit($v) && $v !== '' ? 100000 : 1;
        }
        return max(1, min(100000, (int) $v));
    }

    /** Trimmed string from POST ('' if missing or not a string). */
    public function str(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    /** POST fields that are real lists in the forms (participants[], share[id]). */
    private const ARRAY_FIELDS = ['participants', 'share'];

    /**
     * All POST data for the services. A field sent as an array that the forms never send
     * as one (e.g. description[]=x) becomes '' -> normal "required"/"invalid" message,
     * instead of PHP's "Array" string + warning. List fields must contain only strings.
     */
    public function allPost(): array
    {
        $out = [];
        foreach ($_POST as $k => $v) {
            if ($k === 'items' && is_array($v)) {
                $out[$k] = self::items($v);
            } elseif (is_array($v)) {
                $out[$k] = in_array($k, self::ARRAY_FIELDS, true) ? array_filter($v, 'is_string') : '';
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Itemized bill rows: items[n][name|qty|amount] (strings) and items[n][who][] (user ids).
     * Anything else is dropped; at most 60 rows are kept (the service rejects > 50).
     * @return array<int,array{name:string, qty:string, amount:string, who:list<string>}> (row keys kept, so errors map back)
     */
    private static function items(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 0, 60, true) as $k => $row) {
            if (!is_array($row) || !is_int($k) || $k < 0 || $k > 999) {
                continue;
            }
            $str = static fn(string $f): string => isset($row[$f]) && is_string($row[$f]) ? $row[$f] : '';
            $who = isset($row['who']) && is_array($row['who']) ? array_values(array_filter($row['who'], 'is_string')) : [];
            $out[$k] = ['name' => $str('name'), 'qty' => $str('qty'), 'amount' => $str('amount'), 'who' => $who];
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $f : null;
    }
}
