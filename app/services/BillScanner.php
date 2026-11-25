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
 *   groq       sends the photo to Groq's OpenAI-compatible chat completions API with a
 *              vision model; needs SCAN_API_KEY (a gsk_... key), optional SCAN_MODEL
 *              (default qwen/qwen3.8-27b: check console.groq.com/docs/vision for current IDs).
 *              Groq limits base64 images to about 4 MB, so larger photos are shrunk first (GD).
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
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'qwen/qwen3.8-27b';
    /** Raw photo size that stays under Groq's ~4 MB base64 request limit (base64 adds 1/3). */
    private const GROQ_MAX_RAW = 2_900_000;
    private const PROVIDERS = ['anthropic', 'groq', 'mock'];

    public static function provider(): string
    {
        $p = strtolower(trim((string) Env::get('SCAN_PROVIDER', 'off')));
        if ($p === 'mock' && is_production()) {
            return 'off';
        }
        if (in_array($p, ['anthropic', 'groq'], true) && trim((string) Env::get('SCAN_API_KEY', '')) === '') {
            return 'off';
        }
        return in_array($p, self::PROVIDERS, true) ? $p : 'off';
    }

    public static function enabled(): bool
    {
        return self::provider() !== 'off';
    }

    /** Who receives the photo, for the privacy note next to the button. */
    public static function processorName(): string
    {
        return match (self::provider()) {
            'anthropic' => "Anthropic's AI service",
            'groq'      => "Groq's AI service",
            default     => 'a demo reader on this server (nothing leaves it)',
        };
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
        $raw = match (self::provider()) {
            'mock'  => $this->mockReply(),
            'groq'  => $this->askGroq($bytes, $mime),
            default => $this->askAnthropic($bytes, $mime),
        };
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

        $data = $this->post(self::API_URL, $body, [
            'anthropic-version: 2023-06-01',
            'x-api-key: ' . trim((string) Env::get('SCAN_API_KEY', '')),
        ]);
        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) $block['text'];
            }
        }
        return $text;
    }

    /**
     * One JSON POST to the provider; returns the decoded reply. The key travels only in a
     * header and is never logged; on failure only the HTTP status is logged (the body
     * could echo request details).
     */
    private function post(string $realUrl, string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {   // e.g. XAMPP with extension=curl commented out in php.ini
            error_log('bill scan: the PHP curl extension is not enabled');
            throw ValidationException::single('bill', "Couldn't read the bill right now. Enter the items by hand, or try again.");
        }
        // SCAN_API_URL lets the test suite point at a local fake API; ignored in production
        $url = !is_production() && ($u = (string) Env::get('SCAN_API_URL', '')) !== '' ? $u : $realUrl;
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
            CURLOPT_HTTPHEADER     => array_merge(['content-type: application/json'], $headers),
        ]);
        $res = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false || $status !== 200) {
            error_log(sprintf('bill scan failed: HTTP %d %s', $status, $err));
            $msg = $status === 429
                ? 'The bill reader is busy (rate limit). Wait a minute and try again, or enter the items by hand.'
                : "Couldn't read the bill right now. Enter the items by hand, or try again.";
            throw ValidationException::single('bill', $msg);
        }
        $data = json_decode((string) $res, true);
        return is_array($data) ? $data : [];
    }

    /** One Groq chat-completions call (OpenAI format) with the photo as a base64 data URL. */
    private function askGroq(string $bytes, string $mime): string
    {
        [$bytes, $mime] = $this->shrinkForGroq($bytes, $mime);
        $body = json_encode([
            'model'           => Env::get('SCAN_MODEL', self::GROQ_MODEL) ?: self::GROQ_MODEL,
            'temperature'     => 0,
            'max_completion_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $this->prompt()],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode($bytes)]],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);
        $data = $this->post(self::GROQ_URL, $body, ['authorization: Bearer ' . trim((string) Env::get('SCAN_API_KEY', ''))]);
        $text = (string) ($data['choices'][0]['message']['content'] ?? '');
        // reasoning models may put their thinking in <think>...</think> before the answer
        return (string) preg_replace('~<think>.*?</think>~s', '', $text);
    }

    /**
     * Groq refuses base64 images over ~4 MB. Re-encode a large photo as a JPEG no wider/taller
     * than 2000 px (plenty for reading a bill). Needs PHP's GD extension; without it a large
     * photo gets a friendly "use a smaller photo" message instead of a failed API call.
     * @return array{0:string,1:string}
     */
    private function shrinkForGroq(string $bytes, string $mime): array
    {
        if (strlen($bytes) <= self::GROQ_MAX_RAW) {
            return [$bytes, $mime];
        }
        // decoding takes ~5 bytes per pixel: refuse absurd sizes before GD allocates them
        $dim = @getimagesizefromstring($bytes);
        if ($dim === false || $dim[0] * $dim[1] > 40_000_000) {
            throw ValidationException::single('bill', 'That photo is too large for the bill reader (maximum about 3 MB). Use a smaller photo.');
        }
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
        if ($img === false) {
            throw ValidationException::single('bill', 'That photo is too large for the bill reader (maximum about 3 MB). Use a smaller photo.');
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $scale = min(1.0, 2000 / max($w, $h));
        $small = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        // white background: a transparent PNG would otherwise turn black as a JPEG
        imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
        imagecopyresampled($small, $img, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);
        imagedestroy($img);
        foreach ([85, 70, 55] as $q) {
            ob_start();
            imagejpeg($small, null, $q);
            $out = (string) ob_get_clean();
            if (strlen($out) <= self::GROQ_MAX_RAW) {
                imagedestroy($small);
                return [$out, 'image/jpeg'];
            }
        }
        imagedestroy($small);
        throw ValidationException::single('bill', 'That photo is too large for the bill reader (maximum about 3 MB). Use a smaller photo.');
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
