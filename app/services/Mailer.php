<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Outgoing e-mail (Phase 6).
 *
 * MAIL_DRIVER=log  (default) append the message to MAIL_LOG (file, or "stderr"); nothing is sent.
 *                  In production a SENSITIVE message (e.g. a password-reset link) is written
 *                  with its body removed, so secrets never reach the hosting logs.
 * MAIL_DRIVER=smtp send through any SMTP provider (Brevo, Amazon SES, Postmark, Mailgun, …):
 *                  SMTP_HOST, SMTP_PORT (587), SMTP_ENCRYPTION (starttls | tls | none),
 *                  SMTP_USER, SMTP_PASSWORD, MAIL_FROM, MAIL_FROM_NAME.
 *                  "none" (plain text) is refused when APP_ENV=production.
 * The SMTP password is read from the environment only and is never logged.
 */
final class Mailer
{
    public static function driver(): string
    {
        return strtolower((string) Env::get('MAIL_DRIVER', 'log')) === 'smtp' ? 'smtp' : 'log';
    }

    /** @return bool true when the message was handed over (or logged) */
    public static function send(string $toEmail, string $toName, string $subject, string $body, bool $sensitive = false): bool
    {
        $toEmail = self::clean($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            return self::driver() === 'smtp'
                ? (new SmtpClient())->send($toEmail, self::clean($toName), self::clean($subject), $body)
                : self::log($toEmail, $toName, $subject, $body, $sensitive);
        } catch (\Throwable $t) {
            error_log('mail failed: ' . $t->getMessage());   // messages never contain the password
            return false;
        }
    }

    private static function log(string $to, string $name, string $subject, string $body, bool $sensitive): bool
    {
        if ($sensitive && is_production()) {
            $body = '(body not logged: it contains a secret link. Set MAIL_DRIVER=smtp to deliver it.)';
        }
        $text = sprintf("----- %s\nTo: %s <%s>\nSubject: [SplitWise+] %s\n\n%s\n\n",
            date('Y-m-d H:i:s'), self::clean($name), $to, self::clean($subject), $body);
        $target = Env::get('MAIL_LOG', is_production() ? 'stderr' : '');
        if ($target === 'stderr') {
            return file_put_contents('php://stderr', preg_replace('/^/m', '[mail] ', $text)) !== false;
        }
        $file = $target !== '' ? $target : BASE_PATH . '/storage/logs/mail.log';
        return file_put_contents($file, $text, FILE_APPEND | LOCK_EX) !== false;
    }

    /** No header injection: strip CR/LF. */
    public static function clean(string $v): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $v));
    }
}
