<?php
declare(strict_types=1);

use App\Core\Session;

/** HTML-escape for output (XSS protection). Use for EVERY dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL prefix when the app lives in a sub-folder (e.g. /Splitwise/public). */
function base_path(): string
{
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return ($script === '/' || $script === '.') ? '' : rtrim($script, '/');
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrfToken()) . '">';
}

/** ₹ formatting with Indian digit grouping: 1234567.5 -> ₹12,34,567.50 */
function inr(mixed $amount): string
{
    $amount = (string) ($amount ?? '0');
    $neg = str_starts_with($amount, '-');
    $amount = ltrim($amount, '-');
    [$int, $dec] = array_pad(explode('.', $amount), 2, '00');
    $dec = str_pad(substr($dec, 0, 2), 2, '0');
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest  = substr($int, 0, -3);
        $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int   = $rest . ',' . $last3;
    }
    return ($neg ? '−' : '') . '₹' . $int . '.' . $dec;
}

/** Old form value (after a validation error) or a default. */
function old(array $old, string $key, mixed $default = ''): string
{
    $v = $old[$key] ?? $default;
    return is_scalar($v) ? (string) $v : '';
}

/** Bootstrap invalid-feedback block for a field. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>' : '';
}

function is_invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

/** "+₹100.00 gets back" / "owes ₹50.00" / "settled up" */
function balance_label(string $net): string
{
    $cmp = bccomp_str($net, '0');
    if ($cmp === 0) {
        return 'settled up';
    }
    return $cmp > 0 ? 'gets back ' . inr($net) : 'owes ' . inr(ltrim($net, '-'));
}

/** Compare two decimal strings without floats. */
function bccomp_str(string $a, string $b): int
{
    return \App\Helpers\Money::toPaise($a) <=> \App\Helpers\Money::toPaise($b);
}

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** "8M" -> 8388608 (php.ini size notation). */
function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') {
        return PHP_INT_MAX;
    }
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
    };
}

/** Referer URL if it points back to this site, else null (no open redirect). */
function same_origin_referer(): ?string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($ref === '' || parse_url($ref, PHP_URL_HOST) . (parse_url($ref, PHP_URL_PORT) ? ':' . parse_url($ref, PHP_URL_PORT) : '') !== $host) {
        return null;
    }
    $path = (string) parse_url($ref, PHP_URL_PATH);
    $query = parse_url($ref, PHP_URL_QUERY);
    return $path . ($query ? '?' . $query : '');
}
