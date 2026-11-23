<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Minimal SMTP client (RFC 5321) with STARTTLS / implicit TLS and AUTH LOGIN.
 * Plain text UTF-8 messages, base64 body, one recipient. Certificates are verified.
 */
final class SmtpClient
{
    /** @var resource|null */
    private $sock = null;

    public function send(string $to, string $toName, string $subject, string $body): bool
    {
        $host = (string) Env::get('SMTP_HOST', '');
        $port = (int) (Env::get('SMTP_PORT', '587') ?: 587);
        $enc  = strtolower((string) Env::get('SMTP_ENCRYPTION', 'starttls'));
        $user = (string) Env::get('SMTP_USER', '');
        $pass = (string) Env::get('SMTP_PASSWORD', '');
        $from = Mailer::clean((string) Env::get('MAIL_FROM', ''));
        $fromName = Mailer::clean((string) Env::get('MAIL_FROM_NAME', 'SplitWise+'));
        if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('SMTP_HOST and MAIL_FROM must be set');
        }
        if ($enc === 'none' && is_production()) {
            throw new \RuntimeException('SMTP_ENCRYPTION=none is not allowed in production');
        }

        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $remote = ($enc === 'tls' ? 'tls://' : 'tcp://') . $host . ':' . $port;
        $this->sock = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx) ?: null;
        if ($this->sock === null) {
            throw new \RuntimeException("SMTP connect failed ($errno)");
        }
        stream_set_timeout($this->sock, 15);
        try {
            $this->expect(220);
            $me = gethostname() ?: 'localhost';
            $this->cmd("EHLO $me", 250);
            if ($enc === 'starttls') {
                $this->cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('STARTTLS failed');
                }
                $this->cmd("EHLO $me", 250);
            }
            if ($user !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($user), 334);
                $this->cmd(base64_encode($pass), 235, 'AUTH (password hidden)');
            }
            $this->cmd("MAIL FROM:<$from>", 250);
            $this->cmd("RCPT TO:<$to>", [250, 251]);
            $this->cmd('DATA', 354);
            $enc8 = static fn(string $s): string => preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $enc8($fromName) . " <$from>",
                'To: ' . ($toName !== '' ? $enc8($toName) . ' ' : '') . "<$to>",
                'Subject: ' . $enc8('[SplitWise+] ' . $subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@') ?: '@localhost', 1) . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            // base64 lines never start with "." so no dot-stuffing is needed
            $data = implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n")) . "\r\n.";
            $this->cmd($data, 250, 'DATA body');
            $this->cmd('QUIT', 221);
            return true;
        } finally {
            fclose($this->sock);
            $this->sock = null;
        }
    }

    /** @param int|int[] $ok */
    private function cmd(string $line, int|array $ok, string $label = ''): void
    {
        fwrite($this->sock, $line . "\r\n");
        $this->expect($ok, $label !== '' ? $label : strtok($line, ' '));
    }

    /** @param int|int[] $ok */
    private function expect(int|array $ok, string $label = 'greeting'): void
    {
        $code = 0;
        while (($l = fgets($this->sock, 1024)) !== false) {
            $code = (int) substr($l, 0, 3);
            if (($l[3] ?? ' ') !== '-') {
                break;
            }
        }
        if (!in_array($code, (array) $ok, true)) {
            throw new \RuntimeException("SMTP $label rejected (code $code)");
        }
    }
}
