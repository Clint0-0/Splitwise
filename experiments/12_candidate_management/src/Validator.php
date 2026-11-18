<?php
/** Server-side validation. Returns [cleanData, errors]. */
declare(strict_types=1);

final class Validator
{
    /** @return array{0: array<string,?string>, 1: array<string,string>} */
    public static function candidate(array $in): array
    {
        $d = [
            'full_name'             => preg_replace('/\s+/u', ' ', trim((string) ($in['full_name'] ?? ''))),
            'email'                 => mb_strtolower(trim((string) ($in['email'] ?? ''))),
            'phone'                 => preg_replace('/[\s-]/', '', trim((string) ($in['phone'] ?? ''))),
            'date_of_birth'         => trim((string) ($in['date_of_birth'] ?? '')),
            'highest_qualification' => preg_replace('/\s+/u', ' ', trim((string) ($in['highest_qualification'] ?? ''))),
        ];
        $e = [];
        if ($d['full_name'] === '') {
            $e['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($d['full_name']) < 2 || mb_strlen($d['full_name']) > 100 || !preg_match("/^[\\p{L}][\\p{L} .'-]*$/u", $d['full_name'])) {
            $e['full_name'] = 'Use 2–100 letters (spaces, dots, apostrophes and hyphens allowed).';
        }
        if ($d['email'] === '') {
            $e['email'] = 'Email is required.';
        } elseif (strlen($d['email']) > 150 || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Enter a valid email address, e.g. name@example.com.';
        }
        if (preg_match('/^(\+91|0)?([6-9]\d{9})$/', (string) $d['phone'], $m)) {
            $d['phone'] = $m[2];
        } elseif ($d['phone'] === '') {
            $e['phone'] = 'Phone number is required.';
        } else {
            $e['phone'] = 'Enter a 10-digit Indian mobile number starting with 6–9 (optional +91).';
        }
        if ($d['date_of_birth'] === '') {
            $d['date_of_birth'] = null;
        } elseif (!self::isDate($d['date_of_birth'])) {
            $e['date_of_birth'] = 'Enter a valid date.';
        } else {
            $age = (int) (new DateTime($d['date_of_birth']))->diff(new DateTime('today'))->format('%r%y');
            if ($age < 16 || $age > 80) {
                $e['date_of_birth'] = 'Age must be between 16 and 80 years.';
            }
        }
        if ($d['highest_qualification'] === '') {
            $e['highest_qualification'] = 'Highest qualification is required.';
        } elseif (mb_strlen($d['highest_qualification']) > 100) {
            $e['highest_qualification'] = 'Keep it under 100 characters.';
        }
        return [$d, $e];
    }

    /** @return array{0: array<string,mixed>, 1: array<string,string>} */
    public static function application(array $in, array $openPositionIds): array
    {
        $d = [
            'position_id' => (string) ($in['position_id'] ?? ''),
            'applied_on'  => trim((string) ($in['applied_on'] ?? '')),
            'status'      => (string) ($in['status'] ?? 'Applied'),
        ];
        $e = [];
        if (!ctype_digit($d['position_id']) || !in_array((int) $d['position_id'], $openPositionIds, true)) {
            $e['position_id'] = 'Choose an open position.';
        }
        if ($d['applied_on'] === '') {
            $e['applied_on'] = 'Application date is required.';
        } elseif (!self::isDate($d['applied_on'])) {
            $e['applied_on'] = 'Enter a valid date.';
        } elseif ($d['applied_on'] > date('Y-m-d')) {
            $e['applied_on'] = 'The application date cannot be in the future.';
        }
        if (!in_array($d['status'], STATUSES, true)) {
            $e['status'] = 'Choose a valid status.';
        }
        $d['position_id'] = (int) $d['position_id'];
        return [$d, $e];
    }

    private static function isDate(string $s): bool
    {
        $dt = DateTime::createFromFormat('!Y-m-d', $s);
        return $dt !== false && $dt->format('Y-m-d') === $s;
    }
}
