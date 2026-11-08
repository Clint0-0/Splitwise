<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\ValidationException;

/**
 * Collects field errors; throw() raises them all at once.
 * Server-side validation is authoritative — client-side checks are only a convenience.
 */
final class Validator
{
    private array $errors = [];

    public function required(string $field, string $value, string $label): self
    {
        if ($value === '' && !isset($this->errors[$field])) {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function maxLen(string $field, string $value, int $max, string $label): self
    {
        if (mb_strlen($value) > $max && !isset($this->errors[$field])) {
            $this->errors[$field] = "$label must be at most $max characters.";
        }
        return $this;
    }

    public function email(string $field, string $value): self
    {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL) && !isset($this->errors[$field])) {
            $this->errors[$field] = 'Enter a valid email address.';
        }
        return $this;
    }

    public function regex(string $field, string $value, string $pattern, string $message): self
    {
        if ($value !== '' && !preg_match($pattern, $value) && !isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function in(string $field, string $value, array $allowed, string $label): self
    {
        if (!in_array($value, $allowed, true) && !isset($this->errors[$field])) {
            $this->errors[$field] = "Choose a valid $label.";
        }
        return $this;
    }

    /** Y-m-d, real calendar date, optionally not in the future. */
    public function date(string $field, string $value, string $label, bool $notFuture = false): self
    {
        if ($value === '' || isset($this->errors[$field])) {
            return $this;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            $this->errors[$field] = "$label must be a valid date.";
        } elseif ($notFuture && $value > date('Y-m-d')) {
            $this->errors[$field] = "$label cannot be in the future.";
        }
        return $this;
    }

    public function add(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;
        return $this;
    }

    /** Run a callable that may throw ValidationException; merge its errors. */
    public function try(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            foreach ($e->errors as $k => $v) {
                $this->errors[$k] ??= $v;
            }
            return null;
        }
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function throw(): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }
}
