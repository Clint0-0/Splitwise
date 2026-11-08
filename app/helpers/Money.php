<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\ValidationException;

/**
 * Money as integer paise. No floats anywhere in financial math.
 * "1500.5" -> 150050, 150050 -> "1500.50".
 */
final class Money
{
    public const MAX_PAISE = 999_999_999_999; // DECIMAL(12,2) upper bound

    /** Strict user-input parser: positive, max 2 decimals, within DECIMAL(12,2). */
    public static function parse(string $input, string $field = 'amount', bool $allowZero = false, bool $allowNegative = false): int
    {
        $s = str_replace([',', ' ', '₹'], '', trim($input));
        $pattern = $allowNegative ? '/^-?\d{1,10}(\.\d{1,2})?$/' : '/^\d{1,10}(\.\d{1,2})?$/';
        if (!preg_match($pattern, $s)) {
            throw ValidationException::single($field, 'Enter a valid amount in rupees with at most 2 decimals.');
        }
        $paise = self::toPaise($s);
        if (abs($paise) > self::MAX_PAISE) {
            throw ValidationException::single($field, 'Amount is too large.');
        }
        if (!$allowZero && $paise === 0) {
            throw ValidationException::single($field, 'Amount must be greater than zero.');
        }
        return $paise;
    }

    /** Trusted decimal string (e.g. from MySQL DECIMAL) -> paise. */
    public static function toPaise(string|int $value): int
    {
        $s = trim((string) $value);
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '+-');
        [$int, $dec] = array_pad(explode('.', $s, 2), 2, '');
        $dec = substr(str_pad($dec, 2, '0'), 0, 2);
        $paise = (int) ($int === '' ? '0' : $int) * 100 + (int) $dec;
        return $neg ? -$paise : $paise;
    }

    public static function fromPaise(int $paise): string
    {
        $neg = $paise < 0;
        $abs = abs($paise);
        return ($neg ? '-' : '') . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Parse a fixed-point number with 2 decimals into hundredths (for %, units). */
    public static function parseHundredths(string $input, string $field, int $maxHundredths): int
    {
        $s = trim($input);
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $s)) {
            throw ValidationException::single($field, 'Enter a number with at most 2 decimals.');
        }
        $v = self::toPaise($s);
        if ($v > $maxHundredths) {
            throw ValidationException::single($field, 'Value is too large.');
        }
        return $v;
    }
}
