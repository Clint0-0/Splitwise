<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown to stop a request with an HTTP status (403, 404, 419 …). */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            403     => 'You do not have permission to do that.',
            404     => 'Page not found.',
            419     => 'Your session expired. Please reload the page and try again.',
            default => 'Something went wrong.',
        };
    }
}
