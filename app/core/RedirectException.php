<?php
declare(strict_types=1);

namespace App\Core;

/** Short-circuits a request with a redirect (used by middleware). */
final class RedirectException extends \RuntimeException
{
    public function __construct(public readonly string $location)
    {
        parent::__construct('redirect');
    }
}
