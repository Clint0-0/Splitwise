<?php
/**
 * Shared helpers for HTTP-level tests: a cookie-keeping "browser", CSRF-aware
 * form posts and multipart uploads, result reporting, and a ROOT database
 * connection used only to CHECK results (the app itself runs as splitwise_app).
 */
declare(strict_types=1);

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;

function check(string $id, string $name, bool $ok, string $detail = ''): void
{
    $ok ? $GLOBALS['__pass']++ : $GLOBALS['__fail']++;
    printf("%-6s %s %s%s\n", $id, $ok ? 'PASS' : 'FAIL', $name, $ok || $detail === '' ? '' : "  [$detail]");
}

function finish(): never
{
    $p = $GLOBALS['__pass'];
    $f = $GLOBALS['__fail'];
    echo "\n$p / " . ($p + $f) . " passed\n";
    exit($f === 0 ? 0 : 1);
}

final class Browser
{
    private string $jar;
    public int $status = 0;
    public string $body = '';
    public string $location = '';

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function get(string $path): self
    {
        return $this->send('GET', $path);
    }

    public function post(string $path, array $data = [], bool|string $csrf = true, array $files = []): self
    {
        if ($csrf === true) {
            $data['_csrf'] = $this->token();
        } elseif (is_string($csrf)) {
            $data['_csrf'] = $csrf;
        }
        return $this->send('POST', $path, $data, $files);
    }

    public function follow(): self
    {
        if ($this->location !== '') {
            $q = parse_url($this->location, PHP_URL_QUERY);
            $this->get(parse_url($this->location, PHP_URL_PATH) . ($q ? "?$q" : ''));
        }
        return $this;
    }

    public function token(): string
    {
        foreach ([null, '/login', '/profile'] as $page) {
            if ($page !== null) {
                $this->get($page);
            }
            if (preg_match('/name="_csrf" value="([0-9a-f]+)"/', $this->body, $m)) {
                return $m[1];
            }
        }
        return '';
    }

    /** Value of a hidden/visible input on the current page. */
    public function field(string $name): string
    {
        return preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $this->body, $m) ? html_entity_decode($m[1]) : '';
    }

    public function has(string $text): bool
    {
        return str_contains($this->body, $text);
    }

    /** Visible text of the page (tags stripped, whitespace collapsed) for sentence checks. */
    public function text(): string
    {
        $t = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', ' ', $this->body);
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $t), ENT_QUOTES, 'UTF-8')));
    }

    public function says(string $sentence): bool
    {
        return str_contains($this->text(), $sentence);
    }

    private function send(string $method, string $path, array $data = [], array $files = []): self
    {
        $ch = curl_init($this->base . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($files) {
                $flat = $this->flatten($data);
                foreach ($files as $field => [$file, $mime, $name]) {
                    $flat[$field] = new CURLFile($file, $mime, $name);
                }
                $opts[CURLOPT_POSTFIELDS] = $flat;
            } else {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
            }
        }
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->body = substr($raw, $hs);
        $this->location = preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $hs), $m) ? $m[1] : '';
        curl_close($ch);
        return $this;
    }

    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}[{$k}]";
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = (string) $v;
            }
        }
        return $out;
    }
}

/** Root connection for CHECKING results. Socket on Linux, TCP elsewhere (env DB_ROOT_*). */
function root_db(): PDO
{
    $user = getenv('DB_ROOT_USER') ?: 'root';
    $pass = getenv('DB_ROOT_PASS') ?: '';
    $sock = '/var/run/mysqld/mysqld.sock';
    $dsn = (PHP_OS_FAMILY !== 'Windows' && file_exists($sock) && !getenv('DB_ROOT_TCP'))
        ? "mysql:unix_socket=$sock;dbname=splitwise_plus;charset=utf8mb4"
        : 'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=splitwise_plus;charset=utf8mb4';
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
