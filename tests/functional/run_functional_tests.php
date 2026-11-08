<?php
/**
 * End-to-end HTTP tests for the PHP application (Phase 3).
 * Drives the real app over HTTP like a browser (cookies, CSRF tokens, forms,
 * file uploads) and checks results in the database.
 *
 * Preconditions (tests/functional/run.sh does all of this):
 *   - fresh import of database/splitwise_plus.sql + database/security/app_user.sql
 *   - app running with .env DB_USER=splitwise_app at APP_URL (default http://127.0.0.1:8000)
 * Env: APP_URL, DB_ROOT_USER (root), DB_ROOT_PASS ('') — root is used ONLY to check results.
 */
declare(strict_types=1);

$BASE = rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8000', '/');
$pass = 0; $fail = 0;

function check(string $id, string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s %s%s\n", $id, $ok ? 'PASS' : 'FAIL', $name, $ok || $detail === '' ? '' : "  [$detail]");
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

    /** POST with the CSRF token taken from the last page (unless $csrf === false). */
    public function post(string $path, array $data = [], bool|string $csrf = true, array $files = []): self
    {
        if ($csrf === true) {
            $data['_csrf'] = $this->token();
        } elseif (is_string($csrf)) {
            $data['_csrf'] = $csrf;
        }
        return $this->send('POST', $path, $data, $files);
    }

    /** Follow a redirect (303) once. */
    public function follow(): self
    {
        if ($this->location !== '') {
            $this->get(parse_url($this->location, PHP_URL_PATH) . (($q = parse_url($this->location, PHP_URL_QUERY)) ? "?$q" : ''));
        }
        return $this;
    }

    public function token(): string
    {
        if (!preg_match('/name="_csrf" value="([0-9a-f]+)"/', $this->body, $m)) {
            $this->get('/login');   // any page with a form
            preg_match('/name="_csrf" value="([0-9a-f]+)"/', $this->body, $m);
            if (!$m) {
                $this->get('/profile');
                preg_match('/name="_csrf" value="([0-9a-f]+)"/', $this->body, $m);
            }
        }
        return $m[1] ?? '';
    }

    public function has(string $text): bool
    {
        return str_contains($this->body, $text);
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
        $headers = substr($raw, 0, $hs);
        $this->body = substr($raw, $hs);
        $this->location = preg_match('/^Location:\s*(\S+)/mi', $headers, $m) ? $m[1] : '';
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

$db = new PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=splitwise_plus;charset=utf8mb4',
    getenv('DB_ROOT_USER') ?: 'root', getenv('DB_ROOT_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$val = function (string $sql, array $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); };
$today = date('Y-m-d');

function login(string $base, string $user): Browser
{
    $b = new Browser($base);
    $b->get('/login');
    $b->post('/login', ['login' => $user, 'password' => 'Password@123']);
    return $b;
}

// ======================================================================= auth
$guest = new Browser($BASE);
$guest->get('/dashboard');
check('H1', 'Guest is redirected from /dashboard to /login', $guest->status === 303 && str_ends_with($guest->location, '/login'));

$guest->get('/login');
$guest->post('/login', ['login' => 'aarav', 'password' => 'wrong-password'])->follow();
check('H2', 'Wrong password: generic error, no login', $guest->has('Incorrect username/email or password'));

$guest->get('/login');
$guest->post('/login', ['login' => "' OR '1'='1", 'password' => "' OR '1'='1"])->follow();
check('H3', 'SQL injection in login form does not log in', $guest->has('Incorrect username/email or password'));

$guest->get('/login');
$guest->post('/login', ['login' => 'aarav', 'password' => 'Password@123'], 'not-the-token');
check('H4', 'POST with a wrong CSRF token is rejected (419)', $guest->status === 419);

$aarav = login($BASE, 'aarav');
check('H5', 'Login works and redirects to dashboard', $aarav->status === 303 && str_ends_with($aarav->location, '/dashboard'));
$aarav->get('/dashboard');
check('H6', 'Dashboard shows Aarav\'s real overall balance (+₹10,091.67)', $aarav->has('gets back ₹10,091.67'));

$aarav->get('/profile');
$aarav->post('/profile', ['full_name' => 'Aarav Sharma', 'email' => 'aarav@splitwiseplus.test', 'phone' => '9820011111']); // notify_email unticked
$aarav->post('/groups', ['name' => 'No token group'], false);
check('H7', 'Logged-in POST without CSRF token is rejected (419)', $aarav->status === 419);

$t = new Browser($BASE);
$t->get('/register');
$t->post('/register', ['full_name' => 'Test User', 'username' => 'tester', 'email' => 'Tester@Example.com',
    'password' => 'Secret123', 'password_confirm' => 'Secret123']);
check('H8', 'Registration logs the new user in', $t->status === 303 && str_ends_with($t->location, '/dashboard'));
$hash = (string) $val("SELECT password_hash FROM users WHERE username='tester'");
check('H9', 'Password stored as bcrypt hash, email lower-cased',
    str_starts_with($hash, '$2y$') && password_verify('Secret123', $hash)
    && $val("SELECT email FROM users WHERE username='tester'") === 'tester@example.com');
$dup = new Browser($BASE);
$dup->get('/register');
$dup->post('/register', ['full_name' => 'X', 'username' => 'tester', 'email' => 'aarav@splitwiseplus.test',
    'password' => 'short', 'password_confirm' => 'nope'])->follow();
check('H10', 'Duplicate username/email and weak password rejected with field errors',
    $dup->has('That username is taken') && $dup->has('An account with this email already exists') && $dup->has('at least 8 characters'));

// ======================================================================= authorisation
$t->get('/groups/1');
check('Z1', 'Non-member gets 403 on another group', $t->status === 403);
$t->get('/expenses/1');
check('Z2', 'Non-member gets 403 on another group\'s expense', $t->status === 403);
$t->get('/expenses/create');
$t->post('/expenses', ['group_id' => '1', 'description' => 'sneaky', 'total_amount' => '10', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2]]);
check('Z3', 'Non-member cannot add an expense to another group (403)', $t->status === 403);
$t->get('/admin');
check('Z4', 'Ordinary user gets 403 on /admin', $t->status === 403);
$t->get('/api/analytics/groups');
check('Z5', 'API never leaks other users\' groups', $t->status === 200 && json_decode($t->body, true)['data'] === []);

// ======================================================================= groups + invitations
$aarav->get('/groups/create');
$aarav->post('/groups', ['name' => 'Test Trip', 'group_type' => 'trip', 'description' => 'Functional tests']);
$gid = (int) $val("SELECT group_id FROM expense_groups WHERE name='Test Trip'");
check('G1', 'Group created; creator is its admin', $gid > 0
    && $val("SELECT role FROM group_members WHERE group_id=? AND user_id=2", [$gid]) === 'admin');
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/invite", ['invite' => 'tester']);
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/invite", ['invite' => 'priya@splitwiseplus.test']);
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/invite", ['invite' => 'tester'])->follow();
check('G2', 'Duplicate pending invitation rejected', $aarav->has('already has a pending invitation'));
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/invite", ['invite' => 'nobody-here'])->follow();
check('G3', 'Inviting an unknown user rejected', $aarav->has('No registered user'));

$inv = (int) $val("SELECT invitation_id FROM group_invitations WHERE group_id=? AND invited_user_id=(SELECT user_id FROM users WHERE username='tester')", [$gid]);
$priya = login($BASE, 'priya');
$priya->get('/groups');
$priya->post("/invitations/$inv/accept");
check('G4', 'Someone else cannot accept your invitation (403)', $priya->status === 403);
$t->get('/groups');
check('G5', 'Invitation notification + pending invite shown to invitee', $t->has('Test Trip') && $t->has('Accept'));
$t->post("/invitations/$inv/accept");
$invP = (int) $val("SELECT invitation_id FROM group_invitations WHERE group_id=? AND invited_user_id=3", [$gid]);
$priya->get('/groups');
$priya->post("/invitations/$invP/accept");
$tid = (int) $val("SELECT user_id FROM users WHERE username='tester'");
check('G6', 'Accepted invitations create active memberships',
    (int) $val("SELECT COUNT(*) FROM group_members WHERE group_id=? AND status='active'", [$gid]) === 3);

// ======================================================================= expenses + splits
$aarav->get("/expenses/create?group=$gid");
check('E1', 'Expense form lists the group members', $aarav->has('Test User') && $aarav->has('Priya Nair'));
$aarav->post('/expenses', ['group_id' => $gid, 'description' => 'Dinner', 'total_amount' => '1500', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2, 3, $tid], 'tags' => 'Test Trip, food']);
$e1 = (int) $val("SELECT expense_id FROM expenses WHERE group_id=? AND description='Dinner'", [$gid]);
check('E2', 'Spec example: ₹1500 equal among 3 -> 500 each; tester owes ₹500',
    $e1 > 0 && $val('SELECT fn_member_net_balance(?, ?)', [$gid, $tid]) === '-500.00'
    && $val('SELECT fn_member_net_balance(?, 2)', [$gid]) === '1000.00');
check('E3', 'Tags normalised (lower-case, dashes) and saved',
    $val("SELECT GROUP_CONCAT(t.name ORDER BY t.name) FROM expense_tags et JOIN tags t USING(tag_id) WHERE et.expense_id=?", [$e1]) === 'food,test-trip');
check('E4', 'Participants were notified by the trigger (not the creator)',
    (int) $val("SELECT COUNT(*) FROM notifications WHERE type='expense_added' AND entity_id=?", [$e1]) === 2);

$bad = ['group_id' => $gid, 'description' => 'Bad', 'total_amount' => '1000', 'category_id' => '2', 'expense_date' => $today, 'paid_by' => '2'];
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', $bad + ['split_method' => 'percentage', 'participants' => [2, 3], 'share' => [2 => '50', 3 => '40']])->follow();
check('E5', 'Percentage not 100% rejected with the current total', $aarav->has('must add up to exactly 100% (currently 90.00%)'));
$aarav->post('/expenses', $bad + ['split_method' => 'exact', 'participants' => [2, 3], 'share' => [2 => '500', 3 => '400']])->follow();
check('E6', 'Exact amounts not matching the total rejected', $aarav->has('short by ₹100.00'));
$aarav->post('/expenses', array_replace($bad, ['total_amount' => '10.005', 'split_method' => 'equal', 'participants' => [2]]))->follow();
check('E7', 'Amount with 3 decimals rejected by PHP (MySQL would round it)', $aarav->has('at most 2 decimals'));
$aarav->post('/expenses', array_replace($bad, ['expense_date' => date('Y-m-d', strtotime('+2 days')), 'split_method' => 'equal', 'participants' => [2]]))->follow();
check('E8', 'Future date rejected', $aarav->has('cannot be in the future'));
check('E9', 'No partial expense rows were written by failed submissions',
    (int) $val("SELECT COUNT(*) FROM expenses WHERE group_id=? AND description='Bad'", [$gid]) === 0);

$ok = ['group_id' => $gid, 'category_id' => '10', 'expense_date' => $today, 'paid_by' => '3'];
$priya->get("/expenses/create?group=$gid");
$priya->post('/expenses', $ok + ['description' => 'Cab', 'total_amount' => '1000', 'split_method' => 'shares',
    'participants' => [2, 3, $tid], 'share' => [2 => '1.5', 3 => '1', $tid => '0.5']]);
$eS = (int) $val("SELECT expense_id FROM expenses WHERE description='Cab' AND group_id=?", [$gid]);
check('E10', 'Shares 1.5:1:0.5 of ₹1000 = 500 / 333.33 / 166.67',
    $val("SELECT GROUP_CONCAT(share_amount ORDER BY user_id) FROM expense_splits WHERE expense_id=?", [$eS]) === '500.00,333.33,166.67');
$priya->get("/expenses/create?group=$gid");
$priya->post('/expenses', $ok + ['description' => 'Show', 'total_amount' => '900', 'split_method' => 'adjustment',
    'participants' => [2, 3, $tid], 'share' => [2 => '+150', 3 => '', $tid => '-50']]);
$eA = (int) $val("SELECT expense_id FROM expenses WHERE description='Show' AND group_id=?", [$gid]);
check('E11', 'Adjustment split: base (900−100)/3 + adj -> 416.67 / 266.67 / 216.66',
    $val("SELECT GROUP_CONCAT(share_amount ORDER BY user_id) FROM expense_splits WHERE expense_id=?", [$eA]) === '416.67,266.67,216.66');
check('E12', 'Every expense written through the app has split gap 0',
    (int) $val('SELECT COUNT(*) FROM expenses WHERE fn_expense_split_gap(expense_id) <> 0') === 0);

// XSS
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', ['group_id' => $gid, 'description' => '<script>alert(1)</script>', 'total_amount' => '30',
    'category_id' => '14', 'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2, 3, $tid],
    'notes' => '<img src=x onerror=alert(2)>'])->follow();
check('E13', 'XSS: description/notes are escaped in the page', $aarav->status === 200
    && $aarav->has('&lt;script&gt;alert(1)&lt;/script&gt;') && !$aarav->has('<script>alert(1)</script>')
    && !$aarav->has('<img src=x'));
$eX = (int) $val("SELECT expense_id FROM expenses WHERE description='<script>alert(1)</script>'");

// search / SQLi / pagination
$aarav->get('/expenses?q=' . rawurlencode("' OR 1=1 -- ") . '&group=' . $gid);
check('E14', 'SQL injection in search box returns no rows (treated as text)', $aarav->status === 200 && $aarav->has('0 expenses'));
$aarav->get('/expenses?q=Dinner&group=' . $gid);
check('E15', 'Search finds the right expense', $aarav->has('1 expense') && $aarav->has('Dinner'));
$visible = (int) $val("SELECT COUNT(*) FROM expenses e WHERE e.is_deleted = 0 AND
    (e.paid_by = 2 AND e.group_id IS NULL OR e.group_id IN (SELECT group_id FROM group_members WHERE user_id = 2))");
$aarav->get('/expenses');
$first = $aarav->has("$visible expenses");
$aarav->get('/expenses?page=99');
check('E16', "Expense count matches the database ($visible) and an out-of-range page is empty, not an error",
    $first && $aarav->status === 200 && $aarav->has('No expenses yet'));
$aarav->get('/expenses?group=' . rawurlencode('1 OR 1=1'));
check('E17', 'Malformed group filter ignored safely', $aarav->status === 200);

// personal expense
$t->get('/expenses/create');
$t->post('/expenses', ['group_id' => '', 'description' => 'Books', 'total_amount' => '799', 'category_id' => '13', 'expense_date' => $today]);
$eP = (int) $val("SELECT expense_id FROM expenses WHERE description='Books' AND paid_by=?", [$tid]);
check('E18', 'Personal expense: no group, single split to the owner', $eP > 0
    && $val('SELECT group_id FROM expenses WHERE expense_id=?', [$eP]) === null
    && $val('SELECT GROUP_CONCAT(user_id) FROM expense_splits WHERE expense_id=?', [$eP]) === (string) $tid);
$aarav->get("/expenses/$eP");
check('E19', 'Someone else\'s personal expense is private (403)', $aarav->status === 403);

// edit with optimistic locking
$aarav->get("/expenses/$e1/edit");
preg_match('/name="version" value="(\d+)"/', $aarav->body, $vm);
$ver = $vm[1] ?? '0';
$edit = ['description' => 'Dinner (corrected)', 'total_amount' => '1800', 'category_id' => '2', 'expense_date' => $today,
    'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2, 3, $tid], 'version' => $ver];
$aarav->post("/expenses/$e1", $edit);
check('E20', 'Edit re-splits (1800/3 = 600) and bumps the version',
    $val('SELECT fn_member_net_balance(?, ?)', [$gid, $tid]) !== null
    && $val("SELECT GROUP_CONCAT(share_amount) FROM expense_splits WHERE expense_id=?", [$e1]) === '600.00,600.00,600.00'
    && (int) $val('SELECT version FROM expenses WHERE expense_id=?', [$e1]) === (int) $ver + 1);
$aarav->get("/expenses/$e1/edit");
$aarav->post("/expenses/$e1", $edit + ['version' => $ver])->follow();   // stale version
check('E21', 'Stale edit (old version) rejected — no lost update', $aarav->has('Someone else changed this expense'));
check('E22', 'Edit history: old splits archived, UPDATE audited with actor',
    (int) $val("SELECT COUNT(*) FROM audit_logs WHERE table_name='expense_splits' AND action='DELETE' AND old_data->>'$.expense_id'=?", [$e1]) === 3
    && (int) $val("SELECT changed_by FROM audit_logs WHERE table_name='expenses' AND action='UPDATE' AND record_id=?", [$e1]) === 2);

// delete permissions + soft delete + restore
$t->get("/expenses/$eX");
$t->post("/expenses/$eX/delete");
check('E23', 'Participant who is not creator/payer/admin cannot delete (403)', $t->status === 403);
$aarav->get("/expenses/$eX");
$aarav->post("/expenses/$eX/delete");
check('E24', 'Creator soft-deletes: row kept, excluded from balances',
    (int) $val('SELECT is_deleted FROM expenses WHERE expense_id=?', [$eX]) === 1);
$aarav->get("/expenses/$eX");
$aarav->post("/expenses/$eX/restore");
check('E25', 'Group admin restores a deleted expense', (int) $val('SELECT is_deleted FROM expenses WHERE expense_id=?', [$eX]) === 0);
$aarav->get("/expenses/$eX");
$aarav->post("/expenses/$eX/delete");

// ======================================================================= receipts
$png = tempnam(sys_get_temp_dir(), 'png');
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', ['group_id' => $gid, 'description' => 'Snacks with bill', 'total_amount' => '90', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2, $tid]], true,
    ['receipt' => [$png, 'image/png', 'bill.png']]);
$eR = (int) $val("SELECT expense_id FROM expenses WHERE description='Snacks with bill'");
$rid = (int) $val('SELECT receipt_id FROM receipts WHERE expense_id=?', [$eR]);
$stored = (string) $val('SELECT file_path FROM receipts WHERE receipt_id=?', [$rid]);
check('R1', 'PNG receipt stored outside public/ with a random name',
    $rid > 0 && preg_match('/^[0-9a-f]{32}\.png$/', $stored) === 1 && is_file(__DIR__ . '/../../storage/receipts/' . $stored));
$t->get("/receipts/$rid");
check('R2', 'Participant can open the receipt', $t->status === 200 && str_starts_with($t->body, "\x89PNG"));
$sneha = login($BASE, 'sneha');
$sneha->get("/receipts/$rid");
check('R3', 'Non-member cannot open the receipt (403)', $sneha->status === 403);
$php = tempnam(sys_get_temp_dir(), 'php');
file_put_contents($php, "<?php system(\$_GET['c']); ?>");
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', ['group_id' => $gid, 'description' => 'Evil upload', 'total_amount' => '10', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2]], true,
    ['receipt' => [$php, 'image/png', 'shell.php.png']])->follow();
check('R4', 'PHP file disguised as PNG rejected (MIME from content) and no expense saved',
    $aarav->has('must be a JPG, PNG or PDF') && (int) $val("SELECT COUNT(*) FROM expenses WHERE description='Evil upload'") === 0);
$aarav->get('/storage/receipts/' . $stored);
check('R5', 'Receipts are not reachable by direct URL', $aarav->status === 404);

// ======================================================================= settlements
$t->get("/groups/$gid/settle");
preg_match('/name="request_token" value="([0-9a-f\-]{36})"/', $t->body, $tm);
$token = $tm[1] ?? '';
$net = $val('SELECT fn_member_net_balance(?, ?)', [$gid, $tid]);
$settle = ['group_id' => $gid, 'payer_id' => $tid, 'payee_id' => 2, 'amount' => '300', 'settlement_date' => $today,
    'method' => 'upi', 'note' => 'part payment', 'request_token' => $token];
$t->post('/settlements', $settle);
$t->get("/groups/$gid/settle");
$t->post('/settlements', $settle, (string) (preg_match('/name="_csrf" value="([0-9a-f]+)"/', $t->body, $cm) ? $cm[1] : ''));
check('S1', 'Payer-recorded settlement is pending; double submit (same token) stored once',
    (int) $val('SELECT COUNT(*) FROM settlements WHERE request_token=?', [$token]) === 1
    && $val('SELECT status FROM settlements WHERE request_token=?', [$token]) === 'pending');
check('S2', 'Pending settlement does not change the balance yet', $val('SELECT fn_member_net_balance(?, ?)', [$gid, $tid]) === $net);
$sid = (int) $val('SELECT settlement_id FROM settlements WHERE request_token=?', [$token]);
$t->get('/settlements');
$t->post("/settlements/$sid/confirm");
check('S3', 'Payer cannot confirm their own payment', $val('SELECT status FROM settlements WHERE settlement_id=?', [$sid]) === 'pending');
$aarav->get('/dashboard');
check('S4', 'Payee sees it on the dashboard to confirm', $aarav->has('says they paid you'));
$aarav->post("/settlements/$sid/confirm", ['back' => '/dashboard']);
$after = $val('SELECT fn_member_net_balance(?, ?)', [$gid, $tid]);
check('S5', 'After confirmation the debt drops by exactly ₹300',
    (int) round(((float) $after - (float) $net) * 100) === 30000);
$aarav->get("/groups/$gid/settle");
preg_match('/name="request_token" value="([0-9a-f\-]{36})"/', $aarav->body, $tm2);
$aarav->post('/settlements', ['group_id' => $gid, 'payer_id' => $tid, 'payee_id' => 2, 'amount' => '99999',
    'settlement_date' => $today, 'method' => 'cash', 'request_token' => $tm2[1] ?? ''])->follow();
check('S6', 'Over-settlement rejected with the procedure\'s message', $aarav->has('Amount exceeds what the payer owes'));
$aarav->get("/groups/$gid/settle");
preg_match('/name="request_token" value="([0-9a-f\-]{36})"/', $aarav->body, $tm3);
$aarav->post('/settlements', ['group_id' => $gid, 'payer_id' => 3, 'payee_id' => $tid, 'amount' => '10',
    'settlement_date' => $today, 'method' => 'cash', 'request_token' => $tm3[1] ?? ''])->follow();
check('S7', 'Cannot record a payment between two other people', $aarav->has('only record payments you made or received'));
$aarav->get("/groups/$gid");
check('S8', 'Group page shows simplified suggestions and pairwise debts',
    $aarav->has('Suggested settlements') && $aarav->has('Who owes whom') && $aarav->has('→'));
$aarav->get('/groups/1');
check('S9', 'Goa group: 3 suggested transfers from the sample data', substr_count($aarav->body, '>Record</a>') >= 1
    && preg_match_all('/<strong class="amount">₹/', $aarav->body) === 3);

// ======================================================================= leave / remove rules
$t->get("/groups/$gid");
$t->post("/groups/$gid/leave")->follow();
check('L1', 'Cannot leave a group with an unsettled balance', $t->has('must be settled up first'));
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/members/$tid/remove")->follow();
check('L2', 'Admin cannot remove a member who still owes money', $aarav->has('must be settled up first'));
$priya->get("/groups/$gid");
$priya->post("/groups/$gid/members/$tid/remove");
check('L3', 'Non-admin cannot remove members (403)', $priya->status === 403);
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/members/2/role", ['role' => 'member'])->follow();
check('L4', 'Last admin cannot demote themselves', $aarav->has('at least one admin'));

// ======================================================================= former-member lock (bug fix)
$vik = login($BASE, 'vikram');
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/invite", ['invite' => 'vikram']);
$invV = (int) $val("SELECT invitation_id FROM group_invitations WHERE group_id=? AND invited_user_id=8 AND status='pending'", [$gid]);
$vik->get('/groups');
$vik->post("/invitations/$invV/accept");
// two expenses that involve only Vikram (his balance stays 0), one deleted before he leaves
foreach (['Vikram solo A', 'Vikram solo B'] as $d) {
    $vik->get("/expenses/create?group=$gid");
    $vik->post('/expenses', ['group_id' => $gid, 'description' => $d, 'total_amount' => '100', 'category_id' => '2',
        'expense_date' => $today, 'paid_by' => '8', 'split_method' => 'equal', 'participants' => [8]]);
}
$vA = (int) $val("SELECT expense_id FROM expenses WHERE description='Vikram solo A'");
$vB = (int) $val("SELECT expense_id FROM expenses WHERE description='Vikram solo B'");
$aarav->get("/expenses/$vB");
$aarav->post("/expenses/$vB/delete");
$vik->get("/groups/$gid");
$vik->post("/groups/$gid/leave");
check('K1', 'Member with zero balance can leave', $val("SELECT status FROM group_members WHERE group_id=? AND user_id=8", [$gid]) === 'left');
$aarav->get("/expenses/$vA/edit");
check('K2', 'Editing an expense that involves a former member is refused (redirect, lock message)',
    $aarav->status === 303 && $aarav->follow()->has('Vikram Rao has left the group'));
$aarav->get("/expenses/$vA");
check('K3', 'Expense page shows the lock and hides Edit/Delete', $aarav->has('bi-lock') && !$aarav->has("/expenses/$vA/edit\""));
$aarav->post("/expenses/$vA", ['description' => 'Hijack', 'total_amount' => '500', 'category_id' => '2', 'expense_date' => $today,
    'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2], 'version' => '1']);
$aarav->get("/expenses/$vA");
$aarav->post("/expenses/$vA/delete");
$aarav->get("/expenses/$vB");
$aarav->post("/expenses/$vB/restore")->follow();
check('K4', 'Direct POSTs to edit / delete / restore are refused too; data unchanged',
    $aarav->has('has left the group')
    && $val('SELECT CONCAT(description,"|",total_amount,"|",is_deleted) FROM expenses WHERE expense_id=?', [$vA]) === 'Vikram solo A|100.00|0'
    && (int) $val('SELECT is_deleted FROM expenses WHERE expense_id=?', [$vB]) === 1);
check('K5', 'Former member\'s balance is still exactly 0', $val('SELECT fn_member_net_balance(?, 8)', [$gid]) === '0.00');

// leave vs. expense race: leave/remove take FOR UPDATE, expense writes take FOR SHARE on the same rows
$appA = new PDO('mysql:host=127.0.0.1;dbname=splitwise_plus', 'splitwise_app', 'Change_Me_123!', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$appB = new PDO('mysql:host=127.0.0.1;dbname=splitwise_plus', 'splitwise_app', 'Change_Me_123!', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$appA->beginTransaction();
$appA->query("SELECT membership_id FROM group_members WHERE group_id = $gid ORDER BY membership_id FOR UPDATE")->fetchAll();
$appB->exec('SET SESSION innodb_lock_wait_timeout = 1');
$appB->beginTransaction();
try { $appB->query("SELECT membership_id FROM group_members WHERE group_id = $gid ORDER BY membership_id FOR SHARE")->fetchAll(); $blocked = false; }
catch (PDOException $e) { $blocked = str_contains($e->getMessage(), 'Lock wait timeout'); }
$appB->rollBack(); $appA->rollBack();
$src = file_get_contents(__DIR__ . '/../../app/services/ExpenseService.php') . file_get_contents(__DIR__ . '/../../app/services/GroupService.php');
check('K6', 'A leaving member blocks concurrent expense writes (row locks; services take them)', $blocked
    && substr_count($src, 'lockMembers($groupId, false)') >= 2 && substr_count($src, 'lockMembers($groupId, true)') === 2);

// ======================================================================= budgets + notifications
$t->get('/budgets/create');
$t->post('/budgets', ['scope' => 'personal', 'name' => 'Books budget', 'limit_amount' => '1000', 'category_id' => '13',
    'period_type' => 'monthly', 'start_date' => date('Y-m-01'), 'alert_threshold' => '80']);
$bid = (int) $val("SELECT budget_id FROM budgets WHERE name='Books budget'");
check('B1', 'Monthly budget end date computed from start',
    $val('SELECT end_date FROM budgets WHERE budget_id=?', [$bid]) === date('Y-m-t'));
$t->get('/expenses/create');
$t->post('/expenses', ['group_id' => '', 'description' => 'More books', 'total_amount' => '100', 'category_id' => '13', 'expense_date' => $today]);
check('B2', 'Crossing 80% (₹899 of ₹1000) creates exactly one budget warning',
    (int) $val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='budget_warning' AND entity_id=?", [$tid, $bid]) === 1);
$t->get('/expenses/create');
$t->post('/expenses', ['group_id' => '', 'description' => 'Even more books', 'total_amount' => '50', 'category_id' => '13', 'expense_date' => $today]);
check('B3', 'No duplicate warning for the same budget period',
    (int) $val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='budget_warning' AND entity_id=?", [$tid, $bid]) === 1);
$t->get('/budgets');
check('B4', 'Budgets page shows spent / limit', $t->has('₹949.00 / ₹1,000.00'));
$aarav->get("/budgets/$bid/edit");
check('B5', 'Cannot edit someone else\'s budget (403)', $aarav->status === 403);
$t->get('/notifications');
$unread = (int) $val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$tid]);
$t->post('/notifications/read-all');
check('N1', 'Notifications listed and "mark all read" works',
    $unread > 0 && (int) $val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$tid]) === 0);

// ======================================================================= analytics
$aarav->get('/api/analytics/paid-vs-owed');
$rows = array_column(json_decode($aarav->body, true)['data'] ?? [], null, 'label');
check('A1', 'Analytics JSON uses real DB numbers (Goa: paid ₹28,000, share ₹13,008.33)',
    ($rows['Goa Trip 2026']['paid'] ?? '') === '28000.00' && ($rows['Goa Trip 2026']['owed'] ?? '') === '13008.33');
$aarav->get('/api/analytics/monthly');
$d = json_decode($aarav->body, true)['data'] ?? [];
check('A2', 'Monthly series is continuous (12 months incl. zero months)', count($d) === 12);
$aarav->get('/analytics');
check('A3', 'Analytics page renders with Chart.js', $aarav->status === 200 && $aarav->has('chart.umd.min.js'));

// ======================================================================= profile + archive
$t->get('/profile');
$t->post('/profile', ['full_name' => 'Test User Renamed', 'email' => 'aarav@splitwiseplus.test', 'phone' => ''])->follow();
check('P1', 'Profile: another user\'s email rejected', $t->has('Another account uses this email'));
$t->post('/profile', ['full_name' => 'Test User Renamed', 'email' => 'tester@example.com', 'phone' => '9876543210']);
check('P2', 'Profile update saved', $val('SELECT CONCAT(full_name, "|", phone) FROM users WHERE user_id=?', [$tid]) === 'Test User Renamed|9876543210');
$t->get('/profile');
$t->post('/profile/password', ['current_password' => 'wrong', 'password' => 'NewSecret456', 'password_confirm' => 'NewSecret456'])->follow();
check('P3', 'Password change needs the current password', $t->has('Current password is incorrect'));
$t->post('/profile/password', ['current_password' => 'Secret123', 'password' => 'NewSecret456', 'password_confirm' => 'NewSecret456']);
check('P4', 'Password changed (new hash verifies)', password_verify('NewSecret456', (string) $val("SELECT password_hash FROM users WHERE user_id=?", [$tid])));

$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/archive", ['archive' => '1']);
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', ['group_id' => $gid, 'description' => 'After archive', 'total_amount' => '10', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2]]);
check('P5', 'Archived group is read-only (new expense refused with 403)', $aarav->status === 403
    && (int) $val("SELECT COUNT(*) FROM expenses WHERE description='After archive'") === 0);
$aarav->get("/groups/$gid/settle");
preg_match('/name="request_token" value="([0-9a-f\-]{36})"/', $aarav->body, $am);
$aarav->post('/settlements', ['group_id' => $gid, 'payer_id' => $tid, 'payee_id' => 2, 'amount' => '20',
    'settlement_date' => $today, 'method' => 'cash', 'request_token' => $am[1] ?? '']);
$aarav->follow(); preg_match('/invalid-feedback d-block">([^<]+)/', $aarav->body, $pe);
check('P5b', 'Debts in an archived group can still be settled',
    $val('SELECT status FROM settlements WHERE request_token=?', [$am[1] ?? '']) === 'confirmed', ($pe[1] ?? '') . ' status=' . $aarav->status);
$aarav->get("/groups/$gid");
$aarav->post("/groups/$gid/archive", ['archive' => '0']);
check('P6', 'Group admin can unarchive', (int) $val('SELECT is_archived FROM expense_groups WHERE group_id=?', [$gid]) === 0);

// ======================================================================= admin
$admin = login($BASE, 'admin');
$admin->get('/admin');
check('D1', 'System admin sees statistics', $admin->status === 200 && $admin->has('Confirmed settlements'));
$admin->get('/admin/activity?table=expenses');
check('D2', 'Activity log shows trigger-written audit rows', $admin->has('SOFT_DELETE'));
$admin->get('/admin/users');
$admin->post("/admin/users/$tid/toggle");
$t->get('/dashboard');
check('D3', 'Deactivated user is logged out on their next request', $t->status === 303 && str_ends_with($t->location, '/login'));
$t->get('/login');
$t->post('/login', ['login' => 'tester', 'password' => 'NewSecret456'])->follow();
check('D4', 'Deactivated user cannot log in', $t->has('deactivated'));
$admin->get('/admin/users');
$admin->post('/admin/users/1/toggle');
check('D5', 'Admin cannot deactivate themselves', $admin->status === 403);

// ======================================================================= throttle, mail, uploads (bug fixes)
$m1 = new Browser($BASE);
for ($i = 0; $i < 5; $i++) {
    $m1->get('/login');
    $m1->post('/login', ['login' => 'meera', 'password' => 'wrong' . $i]);
}
$m1->get('/login');
$m1->post('/login', ['login' => 'meera', 'password' => 'Password@123'])->follow();
check('T1', 'After 5 failures even the right password is refused', $m1->has('Too many failed attempts'));
$m2 = new Browser($BASE);   // fresh session / no cookies
$m2->get('/login');
$m2->post('/login', ['login' => 'meera', 'password' => 'Password@123'])->follow();
check('T2', 'Lockout survives throwing away the session cookie', $m2->has('Too many failed attempts'));
$k = login($BASE, 'kabir');
check('T3', 'Other accounts from the same IP are not locked', $k->status === 303 && str_ends_with($k->location, '/dashboard'));

$mail = (string) @file_get_contents(__DIR__ . '/../../storage/logs/mail.log');
check('T4', 'Mock e-mail written for opted-in users (invitation + trigger-made expense notice)',
    str_contains($mail, '<tester@example.com>') && str_contains($mail, 'Subject: [SplitWise+] Group invitation')
    && str_contains($mail, 'Subject: [SplitWise+] New expense'));
check('T5', 'No e-mail for a user who unticked "Email me" (Aarav)', !str_contains($mail, '<aarav@splitwiseplus.test>'));
check('T6', 'Each notification e-mailed once (no duplicates)', substr_count($mail, 'Subject: [SplitWise+] Group invitation') ===
    (int) $val("SELECT COUNT(*) FROM notifications n JOIN users u USING(user_id) WHERE n.type='group_invite' AND n.created_at >= CURDATE() AND u.username IN ('tester','priya','vikram')"));

$big = tempnam(sys_get_temp_dir(), 'big');
file_put_contents($big, str_repeat('A', 9 * 1024 * 1024));
$k->get("/expenses/create");
$k->post('/expenses', ['group_id' => '', 'description' => 'Huge', 'total_amount' => '1', 'category_id' => '14', 'expense_date' => $today],
    true, ['receipt' => [$big, 'image/png', 'huge.png']]);
$k->follow();
check('T7', 'Upload over post_max_size: clear "too large" message, not "session expired"',
    $k->has('upload is too large') && !$k->has('session expired'));
$mid = tempnam(sys_get_temp_dir(), 'mid');
file_put_contents($mid, "\x89PNG\r\n\x1a\n" . str_repeat("\0", (int) (5.5 * 1024 * 1024)));
$k->get("/expenses/create");
$k->post('/expenses', ['group_id' => '', 'description' => 'Too big receipt', 'total_amount' => '1', 'category_id' => '14', 'expense_date' => $today],
    true, ['receipt' => [$mid, 'image/png', 'mid.png']])->follow();
check('T8', '5.5 MB receipt rejected with the real limit (5 MB) and no expense saved',
    $k->has('between 1 byte and 5 MB') && (int) $val("SELECT COUNT(*) FROM expenses WHERE description='Too big receipt'") === 0);
@unlink($big); @unlink($mid);

// ======================================================================= misc
$aarav->get('/does-not-exist');
check('M1', 'Unknown page -> 404 page', $aarav->status === 404);
$aarav->get('/groups/abc');
check('M2', 'Non-numeric id -> 404 (route only accepts digits)', $aarav->status === 404);
check('M3', 'Database totals still consistent: every group balance sums to 0',
    (int) $val('SELECT COUNT(*) FROM (SELECT group_id FROM v_group_member_balances GROUP BY group_id HAVING SUM(net_balance) <> 0) x') === 0);
$aarav->post('/logout');
$aarav->get('/dashboard');
check('M4', 'Logout ends the session', $aarav->status === 303);

echo "\n$pass / " . ($pass + $fail) . " passed\n";
exit($fail === 0 ? 0 : 1);
