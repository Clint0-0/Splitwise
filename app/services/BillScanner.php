<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\ValidationException;
use App\Helpers\Money;

/**
 * Bill scanning (Phase 6, behind a feature flag).
 *
 * Reads a photo of a bill and returns its lines, so the itemized-split form can be
 * filled in. It NEVER saves anything: the person checks and corrects the lines,
 * ticks who had what, and saves the expense through the normal, validated path.
 *
 * SCAN_PROVIDER (environment):
 *   off        (default) feature hidden
 *   anthropic  sends the photo to the Anthropic Messages API (vision); needs SCAN_API_KEY,
 *              optional SCAN_MODEL. The photo is not stored by SplitWise+.
 *   mock       returns a fixed sample bill, for local demos and the test suite only
 *              (refused when APP_ENV=production)
 * SCAN_DAILY_LIMIT    scans per user per day (default 20), counted in app_kv.
 * SCAN_MONTHLY_LIMIT  scans for the whole site per calendar month (default 1000): a hard
 *                     spending cap. SCAN_COST_PAISE = estimated cost of one scan, for the
 *                     admin page (default 70 paise ≈ one Sonnet-class vision call).
 */
final class BillScanner
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const DEFAULT_MODEL = 'claude-sonnet-5-5';

    public static function provider(): string
    {
        $p = strtolower(trim((string) Env::get('SCAN_PROVIDER', 'off')));
        if ($p === 'mock' && is_production()) {
            return 'off';
        }
        if ($p === 'anthropic' && trim((string) Env::get('SCAN_API_KEY', '')) === '') {
            return 'off';
        }
        return in_array($p, ['anthropic', 'mock'], true) ? $p : 'off';
    }

    public static function enabled(): bool
    {
        return self::provider() !== 'off';
    }

    /** Who receives the photo, for the privacy note next to the button. */
    public static function processorName(): string
    {
        return self::provider() === 'anthropic' ? "Anthropic's AI service" : 'a demo reader on this server (nothing leaves it)';
    }

    /**
     * @param array $file one entry of $_FILES
     * @return array{merchant: string, date: ?string, items: list<array{name:string, qty:string, amount:string, uncertain:bool}>,
     *               charges: list<array{kind:string, label:string, amount:string}>, total: ?string, warnings: list<string>}
     */
    public function scan(int $userId, array $file): array
    {
        if (!self::enabled()) {
            throw ValidationException::single('bill', 'Bill scanning is not switched on.');
        }
        [$bytes, $mime] = $this->readImage($file);
        $this->countScan($userId);
        try {
            return $this->read($bytes, $mime);
        } catch (ValidationException $e) {
            self::bump('scan:month:' . date('Y-m') . ':failed');
            throw $e;
        }
    }

    /** Site-wide usage for the admin page: this month's scans, failures, estimated cost, cap. */
    public static function usage(): array
    {
        $st = Database::pdo()->prepare('SELECT kv_key, CAST(kv_value AS UNSIGNED) AS n FROM app_kv WHERE kv_key IN (?, ?)');
        $m = date('Y-m');
        $st->execute(["scan:month:$m", "scan:month:$m:failed"]);
        $rows = array_column($st->fetchAll(\PDO::FETCH_ASSOC), 'n', 'kv_key');
        $n = (int) ($rows["scan:month:$m"] ?? 0);
        $cost = max(0, (int) (Env::get('SCAN_COST_PAISE', '70') ?? '70'));
        return ['month' => $m, 'scans' => $n, 'failed' => (int) ($rows["scan:month:$m:failed"] ?? 0),
                'cost_paise' => $n * $cost, 'limit' => self::monthlyLimit(), 'provider' => self::provider()];
    }

    private static function monthlyLimit(): int
    {
        return max(1, (int) (Env::get('SCAN_MONTHLY_LIMIT', '1000') ?? '1000'));
    }

    /** Atomic +1 on an app_kv counter; returns the new value. */
    private static function bump(string $key, int $ttl = 400 * 86400): int
    {
        $pdo = Database::pdo();
        $pdo->prepare("INSERT INTO app_kv (kv_key, kv_value, expires_at) VALUES (?, '1', ?)
                       ON DUPLICATE KEY UPDATE kv_value = CAST(kv_value AS UNSIGNED) + 1")
            ->execute([$key, time() + $ttl]);
        $st = $pdo->prepare('SELECT CAST(kv_value AS UNSIGNED) FROM app_kv WHERE kv_key = ?');
        $st->execute([$key]);
        return (int) $st->fetchColumn();
    }

    /** Ask the configured provider about already-checked image bytes, then clean the answer. */
    public function read(string $bytes, string $mime): array
    {
        $raw = self::provider() === 'mock' ? $this->mockReply() : $this->askAnthropic($bytes, $mime);
        return $this->normalise($raw);
    }

    /** @return array{0:string,1:string} bytes, MIME type (checked from the content, not the name) */
    private function readImage(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            $big = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            throw ValidationException::single('bill', $big ? 'That photo is too large (maximum 5 MB).' : 'Choose a photo of the bill first.');
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw ValidationException::single('bill', 'That photo is too large (maximum 5 MB).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!in_array($mime, self::MIME, true)) {
            throw ValidationException::single('bill', 'The bill must be a JPG, PNG or WebP photo.');
        }
        $bytes = (string) file_get_contents($file['tmp_name']);
        return [$bytes, $mime];
    }

    /** Per-user daily limit: each scan costs money with a real provider. Atomic counter in app_kv. */
    private function countScan(int $userId): void
    {
        $limit = max(1, (int) (Env::get('SCAN_DAILY_LIMIT', '20') ?? '20'));
        if (self::bump('scan:u:' . $userId . ':' . date('Y-m-d'), 2 * 86400) > $limit) {
            throw ValidationException::single('bill', "You've used today's $limit bill scans. Enter the items by hand, or try again tomorrow.");
        }
        // site-wide monthly cap = spending limit; counted only for scans that get this far
        $month = 'scan:month:' . date('Y-m');
        if (self::bump($month) > self::monthlyLimit()) {
            throw ValidationException::single('bill', 'Bill scanning has reached its monthly limit for this site. Enter the items by hand.');
        }
    }

    private function prompt(): string
    {
        return <<<TXT
        This is a photo of a restaurant or shop bill, usually from India (amounts in rupees).
        Read it. Reply with ONLY one JSON object, no other text:
        {"merchant": "<shop name or empty>", "date": "<YYYY-MM-DD or null>",
         "items": [{"name": "<item>", "qty": <number>, "amount": <line total in rupees>, "uncertain": <true if any part of this line was hard to read>}],
         "charges": [{"kind": "tax|service|discount|other", "label": "<as printed, e.g. CGST 2.5%>", "amount": <rupees, always positive>}],
         "total": <grand total payable in rupees or null>}
        Rules: "amount" is the line total (qty × price) as printed. Tax, GST, CGST/SGST, service charge,
        discounts and round-off go in "charges" (round-off = "other", negative only if printed as a minus), never in "items".
        Do not guess who ate what. Use null for anything you cannot read.
        If the image is not a bill, reply {"merchant": "", "date": null, "items": [], "charges": [], "total": null}.
        TXT;
    }

    /** One Messages API call. The key is sent only as a header and never logged. */
    private function askAnthropic(string $bytes, string $mime): string
    {
        $body = json_encode([
            'model'      => Env::get('SCAN_MODEL', self::DEFAULT_MODEL) ?: self::DEFAULT_MODEL,
            'max_tokens' => 2000,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]],
                    ['type' => 'text', 'text' => $this->prompt()],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        // SCAN_API_URL lets the test suite point at a local fake API; ignored in production
        $url = !is_production() && ($u = (string) Env::get('SCAN_API_URL', '')) !== '' ? $u : self::API_URL;
        // Windows/XAMPP counts max_execution_time (default 30 s) in wall-clock time, so a
        // slow API reply would kill the request with a fatal error: allow the curl timeout
        @set_time_limit(75);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'anthropic-version: 2023-06-01',
                'x-api-key: ' . trim((string) Env::get('SCAN_API_KEY', '')),
            ],
        ]);
        $res = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false || $status !== 200) {
            // log the status only: the response body could echo request details
            error_log(sprintf('bill scan failed: HTTP %d %s', $status, $err));
            throw ValidationException::single('bill', "Couldn't read the bill right now. Enter the items by hand, or try again.");
        }
        $data = json_decode((string) $res, true);
        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) $block['text'];
            }
        }
        return $text;
    }

    private function mockReply(): string
    {
        return '{"merchant": "Demo Dhaba", "date": "2026-11-20", "items": [
            {"name": "Paneer butter masala", "qty": 1, "amount": 320, "uncertain": false},
            {"name": "Butter naan", "qty": 4, "amount": 200, "uncertain": false},
            {"name": "Jeera rice", "qty": 1, "amount": 180, "uncertain": true},
            {"name": "Sweet lassi", "qty": 2, "amount": 160, "uncertain": false}
        ], "charges": [
            {"kind": "tax", "label": "CGST 2.5%", "amount": 21.50},
            {"kind": "tax", "label": "SGST 2.5%", "amount": 21.50}
        ], "total": 903.00}';
    }

    /**
     * Trust nothing in the reply: pull out the JSON object, keep only well-formed lines,
     * clamp lengths and counts, convert amounts to exact 2-decimal strings.
     */
    public function normalise(string $raw): array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        $data = ($start !== false && $end !== false && $end > $start) ? json_decode(substr($raw, $start, $end - $start + 1), true) : null;
        if (!is_array($data)) {
            throw ValidationException::single('bill', "Couldn't read any items on that photo. Try a sharper, straight-on picture, or enter them by hand.");
        }
        $money = static function (mixed $v): ?string {
            if (!is_int($v) && !is_float($v) && !(is_string($v) && is_numeric($v))) {
                return null;
            }
            $p = (int) round(((float) $v) * 100);
            return $p > 0 && $p <= 99_999_999_999 ? Money::fromPaise($p) : null;
        };
        $items = [];
        foreach (array_slice((array) ($data['items'] ?? []), 0, SplitCalculator::MAX_ITEMS) as $it) {
            if (!is_array($it)) {
                continue;
            }
            $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($it['name'] ?? '')));
            $amount = $money($it['amount'] ?? null);
            if ($name === '' || $amount === null) {
                continue;
            }
            $qty = $money($it['qty'] ?? 1) ?? '1.00';
            $items[] = ['name' => mb_substr($name, 0, 80), 'qty' => rtrim(rtrim($qty, '0'), '.'), 'amount' => $amount,
                        'uncertain' => ($it['uncertain'] ?? false) === true];
        }
        if ($items === []) {
            throw ValidationException::single('bill', "Couldn't read any items on that photo. Try a sharper, straight-on picture, or enter them by hand.");
        }
        $charges = [];
        foreach (array_slice((array) ($data['charges'] ?? []), 0, 10) as $c) {
            if (!is_array($c) || !in_array($c['kind'] ?? '', ['tax', 'service', 'discount', 'other'], true)) {
                continue;
            }
            $raw = $c['amount'] ?? null;
            $neg = $c['kind'] === 'other' && is_numeric($raw) && (float) $raw < 0;
            $amt = $money(is_numeric($raw) ? abs((float) $raw) : null);
            if ($amt === null) {
                continue;
            }
            $label = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($c['label'] ?? ''))), 0, 60);
            $charges[] = ['kind' => $c['kind'], 'label' => $label, 'amount' => ($neg ? '-' : '') . $amt];
        }
        $date = is_string($data['date'] ?? null) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data['date'], $d)
            && checkdate((int) $d[2], (int) $d[3], (int) $d[1]) && $data['date'] <= date('Y-m-d') ? $data['date'] : null;
        $total = $money($data['total'] ?? null);

        // confidence warnings: say what to double-check; never silently "fix" the numbers
        $warnings = [];
        $unsure = array_column(array_filter($items, fn($i) => $i['uncertain']), 'name');
        if ($unsure !== []) {
            $warnings[] = 'Hard to read, check these lines: ' . implode(', ', $unsure) . '.';
        }
        $sum = array_sum(array_map(fn($i) => Money::toPaise($i['amount']), $items))
             + array_sum(array_map(fn($c) => ($c['kind'] === 'discount' ? -1 : 1) * Money::toPaise($c['amount']), $charges));
        if ($total === null) {
            $warnings[] = "Couldn't read the bill total: type it in.";
        } elseif ($sum !== Money::toPaise($total)) {
            $warnings[] = sprintf('The lines add up to %s but the bill total reads %s: a line or charge may be missing or misread.',
                inr(Money::fromPaise($sum)), inr($total));
        }
        if (!empty($data['date']) && $date === null) {
            $warnings[] = "Couldn't read a valid bill date: check the date.";
        }
        return [
            'merchant' => mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($data['merchant'] ?? ''))), 0, 120),
            'date'     => $date,
            'items'    => $items,
            'charges'  => $charges,
            'total'    => $total,
            'warnings' => $warnings,
        ];
    }
}
