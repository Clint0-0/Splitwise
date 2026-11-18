<?php
/**
 * Experiment 12 — Candidate Management CRUD tests (database + HTTP end to end).
 * Started by tests/run.sh. Root over the local socket for direct SQL checks
 * (CMS_ROOT_DSN / CMS_ROOT_USER / CMS_ROOT_PASS to override).
 */
declare(strict_types=1);

$BASE = 'http://127.0.0.1:8091';
$pass = 0; $fail = 0;
function check(string $id, string $label, bool $ok, string $info = ''): void
{
    global $pass, $fail;
    echo ($ok ? 'PASS' : 'FAIL') . "  $id  $label" . ($ok || $info === '' ? '' : "  [$info]") . "\n";
    $ok ? $pass++ : $fail++;
}
$root = new PDO(getenv('CMS_ROOT_DSN') ?: 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=splitwise_candidate_lab;charset=utf8mb4',
    getenv('CMS_ROOT_USER') ?: 'root', getenv('CMS_ROOT_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$val = function (string $sql, array $p = []) use ($root) { $s = $root->prepare($sql); $s->execute($p); return $s->fetchColumn(); };
$sqlErr = function (string $sql) use ($root): int {
    try { $root->exec($sql); return 0; } catch (PDOException $e) { return (int) ($e->errorInfo[1] ?? -1); }
};

final class Web
{
    public int $status = 0; public string $body = ''; public string $location = '';
    private string $jar;
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'cmsjar'); }
    public function get(string $p): self { return $this->send('GET', $p); }
    public function post(string $p, array $f, bool $csrf = true): self
    {
        if ($csrf && !isset($f['csrf'])) { $f['csrf'] = $this->token(); }
        return $this->send('POST', $p, $f);
    }
    public function token(): string
    {
        if (!preg_match('/name="csrf" value="([0-9a-f]{64})"/', $this->body, $m)) { $this->get('/create.php'); preg_match('/name="csrf" value="([0-9a-f]{64})"/', $this->body, $m); }
        return $m[1] ?? '';
    }
    public function follow(): self { return $this->location !== '' ? $this->get('/' . ltrim($this->location, '/')) : $this; }
    public function has(string $t): bool { return str_contains($this->body, $t); }
    private function send(string $m, string $p, array $f = []): self
    {
        $ch = curl_init($this->base . $p);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20]
            + ($m === 'POST' ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($f)] : []));
        $raw = (string) curl_exec($ch);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->location = preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $hs), $mm) ? $mm[1] : '';
        $this->body = substr($raw, $hs);
        unset($ch);
        return $this;
    }
}

// ------------------------------------------------------------------ database
$app = null;
try { $app = new PDO('mysql:host=127.0.0.1;dbname=splitwise_candidate_lab;charset=utf8mb4', 'candidate_app', (string) getenv('CANDIDATE_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } catch (PDOException $e) {}
check('D1', 'App user candidate_app connects to splitwise_candidate_lab', $app !== null);
$denied = false;
try { $app?->query('SELECT 1 FROM splitwise_plus.users LIMIT 1'); } catch (PDOException $e) { $denied = in_array((int) $e->errorInfo[1], [1044, 1142], true); }
check('D2', 'App user has no access to the SplitWise+ database', $denied);
$ddl = false;
try { $app?->exec('DROP TABLE positions'); } catch (PDOException $e) { $ddl = (int) $e->errorInfo[1] === 1142; }
check('D3', 'App user cannot run DDL (DROP TABLE refused)', $ddl);
check('D4', '3 tables; FKs: applications→candidates CASCADE, applications→positions RESTRICT',
    (int) $val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_candidate_lab'") === 3
    && $val("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='splitwise_candidate_lab' AND CONSTRAINT_NAME='fk_applications_candidate'") === 'CASCADE'
    && $val("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='splitwise_candidate_lab' AND CONSTRAINT_NAME='fk_applications_position'") === 'RESTRICT');
check('D5', 'Duplicate email rejected by UNIQUE (1062)',
    $sqlErr("INSERT INTO candidates (full_name,email,phone,highest_qualification) VALUES ('Dup','neha.k@example.test','9000000001','X')") === 1062);
check('D6', 'Duplicate phone rejected by UNIQUE (1062)',
    $sqlErr("INSERT INTO candidates (full_name,email,phone,highest_qualification) VALUES ('Dup','dup@example.test','9876543210','X')") === 1062);
check('D7', 'Invalid phone rejected by CHECK (3819)',
    $sqlErr("INSERT INTO candidates (full_name,email,phone,highest_qualification) VALUES ('Bad','bad@example.test','1234567890','X')") === 3819);
check('D8', 'Invalid status rejected by ENUM in strict mode (1265)',
    $sqlErr("INSERT INTO applications (candidate_id,position_id,applied_on,status) VALUES (2,2,'2026-10-01','Hired')") === 1265);
check('D9', 'Same candidate + position twice rejected (1062)',
    $sqlErr("INSERT INTO applications (candidate_id,position_id,applied_on) VALUES (1,1,'2026-10-01')") === 1062);
check('D10', 'Position with applications cannot be deleted (RESTRICT, 1451)', $sqlErr('DELETE FROM positions WHERE position_id = 1') === 1451);
check('D11', 'Missing required column rejected (NOT NULL, 1364)',
    $sqlErr("INSERT INTO candidates (full_name,email,phone) VALUES ('NoQual','nq@example.test','9000000002')") === 1364);

// ------------------------------------------------------------------ HTTP
$w = new Web($BASE);
$w->get('/');
check('H1', 'Listing: 200, 4 sample candidates, links to view/edit/delete',
    $w->status === 200 && $w->has('4 candidates') && $w->has('Neha Kulkarni') && $w->has('view.php?id=1') && $w->has('edit.php?id=1') && $w->has('delete.php?id=1'));
$w->get('/create.php');
check('H2', 'Register form: labels, required fields, CSRF token, only open positions',
    $w->status === 200 && $w->has('<label for="full_name">') && substr_count($w->body, ' required') >= 6
    && preg_match('/name="csrf" value="[0-9a-f]{64}"/', $w->body) === 1 && !$w->has('HR Executive'));
$n0 = (int) $val('SELECT COUNT(*) FROM candidates');
$w->post('/create.php', ['full_name' => '', 'email' => '', 'phone' => '', 'highest_qualification' => '', 'position_id' => '', 'applied_on' => '', 'status' => 'Applied']);
check('H3', 'Missing required fields: 200 with 6 field errors, nothing inserted',
    $w->status === 200 && $w->has('Full name is required.') && $w->has('Email is required.') && $w->has('Phone number is required.')
    && $w->has('Highest qualification is required.') && $w->has('Choose an open position.') && $w->has('Application date is required.')
    && (int) $val('SELECT COUNT(*) FROM candidates') === $n0);
$w->post('/create.php', ['full_name' => 'A1', 'email' => 'not-an-email', 'phone' => '12345', 'date_of_birth' => '2020-01-01',
    'highest_qualification' => 'B.E.', 'position_id' => '5', 'applied_on' => date('Y-m-d', strtotime('+3 days')), 'status' => 'Hired']);
check('H4', 'Invalid input: bad name, email, phone, age, closed position, future date, bad status all reported',
    $w->has('Use 2–100 letters') && $w->has('Enter a valid email') && $w->has('10-digit Indian mobile') && $w->has('Age must be between')
    && $w->has('Choose an open position.') && $w->has('cannot be in the future') && $w->has('Choose a valid status.') && (int) $val('SELECT COUNT(*) FROM candidates') === $n0);
$w->post('/create.php', ['full_name' => "  Kavya   D'Souza ", 'email' => 'Kavya.D@Example.TEST', 'phone' => '+91 99887-76655', 'date_of_birth' => '2003-01-30',
    'highest_qualification' => 'B.Tech. <b>CS</b>', 'position_id' => '2', 'applied_on' => '2026-11-01', 'status' => 'Applied']);
$newId = (int) $val("SELECT candidate_id FROM candidates WHERE email = 'kavya.d@example.test'");
check('H5', "Create: 303 to view.php, row stored normalised (name spaces, lower-case email, phone without +91), application stored",
    $w->status === 303 && $w->location === "view.php?id=$newId" && $newId > 0
    && $val('SELECT full_name FROM candidates WHERE candidate_id=?', [$newId]) === "Kavya D'Souza"
    && $val('SELECT phone FROM candidates WHERE candidate_id=?', [$newId]) === '9988776655'
    && (int) $val('SELECT COUNT(*) FROM applications WHERE candidate_id=? AND position_id=2', [$newId]) === 1);
$w->follow();
check('H6', 'Detail page: success message, details, application, HTML in input shown escaped',
    $w->status === 200 && $w->has("Candidate Kavya D&#039;Souza registered.") && $w->has('9988776655') && $w->has('Database Administrator')
    && $w->has('B.Tech. &lt;b&gt;CS&lt;/b&gt;') && !$w->has('<b>CS</b>'));
$w->post('/create.php', ['full_name' => 'Other Person', 'email' => 'neha.k@example.test', 'phone' => '9000000003', 'highest_qualification' => 'BA',
    'position_id' => '1', 'applied_on' => '2026-11-01', 'status' => 'Applied']);
check('H7', 'Duplicate email via form: friendly field error, no row, no half-created application',
    $w->status === 200 && $w->has('Another candidate already uses this email.') && (int) $val("SELECT COUNT(*) FROM candidates WHERE phone='9000000003'") === 0);
$w->get('/?q=kavya');
$okSearch = $w->has('1 candidate') && $w->has("Kavya D&#039;Souza");
$w->get('/?position=1&status=Interviewed');
$okFilter = $w->has('1 candidate') && $w->has('Neha Kulkarni');
$w->get('/?q=' . urlencode("' OR 1=1 -- "));
$okInj = $w->has('No candidates match these filters.');
$w->get('/?q=zzzz');
check('H8', 'Search by name, filter by position+status, injection text treated as data, empty state',
    $okSearch && $okFilter && $okInj && $w->has('Show all candidates'));
$w->get("/edit.php?id=$newId");
$w->post('/edit.php', ['id' => (string) $newId, 'full_name' => 'Kavya Dsouza', 'email' => 'kavya.d@example.test', 'phone' => '9988776650',
    'date_of_birth' => '', 'highest_qualification' => 'M.Tech. CS']);
check('H9', 'Update: 303, new values saved, optional DOB cleared to NULL, updated_at set',
    $w->status === 303 && $val('SELECT full_name FROM candidates WHERE candidate_id=?', [$newId]) === 'Kavya Dsouza'
    && $val('SELECT highest_qualification FROM candidates WHERE candidate_id=?', [$newId]) === 'M.Tech. CS'
    && $val('SELECT date_of_birth FROM candidates WHERE candidate_id=?', [$newId]) === null
    && $val('SELECT updated_at FROM candidates WHERE candidate_id=?', [$newId]) !== null);
$w->get("/edit.php?id=$newId");
$w->post('/edit.php', ['id' => (string) $newId, 'full_name' => 'Kavya Dsouza', 'email' => 'kavya.d@example.test', 'phone' => '9876543210', 'highest_qualification' => 'M.Tech. CS']);
check('H10', 'Update with another candidate\'s phone: field error, value unchanged',
    $w->status === 200 && $w->has('Another candidate already uses this phone number.') && $val('SELECT phone FROM candidates WHERE candidate_id=?', [$newId]) === '9988776650');
$w->get("/view.php?id=$newId");
$w->post('/application.php', ['action' => 'add', 'candidate_id' => (string) $newId, 'position_id' => '1', 'applied_on' => '2026-11-02', 'status' => 'Applied']);
$okAdd = $w->status === 303 && (int) $val('SELECT COUNT(*) FROM applications WHERE candidate_id=?', [$newId]) === 2;
$appId = (int) $val('SELECT application_id FROM applications WHERE candidate_id=? AND position_id=1', [$newId]);
$w->post('/application.php', ['action' => 'status', 'candidate_id' => (string) $newId, 'application_id' => (string) $appId, 'status' => 'Shortlisted']);
$okStatus = $val('SELECT status FROM applications WHERE application_id=?', [$appId]) === 'Shortlisted';
$w->post('/application.php', ['action' => 'status', 'candidate_id' => '1', 'application_id' => (string) $appId, 'status' => 'Rejected']);
$okScope = $val('SELECT status FROM applications WHERE application_id=?', [$appId]) === 'Shortlisted';
check('H11', 'Add second application; change status; status change via another candidate\'s URL ignored', $okAdd && $okStatus && $okScope);
$w->get("/application.php?action=delete&candidate_id=$newId&application_id=$appId");
$okConfirm = $w->status === 200 && $w->has('Withdraw this application?') && (int) $val('SELECT COUNT(*) FROM applications WHERE application_id=?', [$appId]) === 1;
$w->post('/application.php', ['action' => 'delete', 'candidate_id' => (string) $newId, 'application_id' => (string) $appId]);
check('H12', 'Withdraw: GET shows confirmation only; POST deletes that one application',
    $okConfirm && (int) $val('SELECT COUNT(*) FROM applications WHERE application_id=?', [$appId]) === 0
    && (int) $val('SELECT COUNT(*) FROM applications WHERE candidate_id=?', [$newId]) === 1);
$w->get("/delete.php?id=$newId");
$okDelConfirm = $w->status === 200 && $w->has('Delete Kavya Dsouza?') && $w->has('1 application') && (int) $val('SELECT COUNT(*) FROM candidates WHERE candidate_id=?', [$newId]) === 1;
$bad = new Web($BASE);
$bad->post('/delete.php', ['id' => (string) $newId, 'csrf' => str_repeat('0', 64)], false);
$okCsrf = $bad->status === 400 && (int) $val('SELECT COUNT(*) FROM candidates WHERE candidate_id=?', [$newId]) === 1;
check('H13', 'Delete: GET = confirmation page (nothing deleted); POST with a wrong CSRF token refused (400)', $okDelConfirm && $okCsrf);
$w->post('/delete.php', ['id' => (string) $newId]);
$w->follow();
check('H14', 'Delete: POST removes candidate and (CASCADE) their applications; listing shows message and 4 candidates',
    (int) $val('SELECT COUNT(*) FROM candidates WHERE candidate_id=?', [$newId]) === 0
    && (int) $val('SELECT COUNT(*) FROM applications WHERE candidate_id=?', [$newId]) === 0
    && $w->has('and their applications were deleted.') && $w->has('4 candidates'));
$w->get('/view.php?id=99999');
$s1 = $w->status;
$w->get('/view.php?id=abc');
check('H15', 'Unknown or malformed id: 404 page, no SQL error text', $s1 === 404 && $w->status === 404 && !$w->has('SQLSTATE'));
$h = shell_exec('curl -sI ' . escapeshellarg($BASE . '/')) ?? '';
check('H16', 'Security headers: CSP, nosniff, frame DENY; session cookie HttpOnly',
    stripos($h, "Content-Security-Policy: default-src 'self'") !== false && stripos($h, 'X-Content-Type-Options: nosniff') !== false
    && stripos($h, 'X-Frame-Options: DENY') !== false && preg_match('/Set-Cookie: cms_lab_sess=[^\r\n]*HttpOnly/i', $h) === 1);

$bad = new Web('http://127.0.0.1:8092');
$bad->get('/');
check('H17', 'Wrong DB password: friendly 500 page, no credentials or SQLSTATE shown',
    $bad->status === 500 && $bad->has('Cannot connect to the database') && !$bad->has('SQLSTATE') && !$bad->has('candidate_app'));

echo "\n$pass / " . ($pass + $fail) . " passed\n";
exit($fail ? 1 : 0);
