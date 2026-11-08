<?php
declare(strict_types=1);

namespace App\Core;

/** Carries field => message errors back to the form. */
final class ValidationException extends \RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    public static function single(string $field, string $message): self
    {
        return new self([$field => $message]);
    }
}
