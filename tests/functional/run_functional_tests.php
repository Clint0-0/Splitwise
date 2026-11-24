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

// "today" must be the app's date, not the test machine's (APP_TIMEZONE, default India)
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

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
    public string $headers = '';

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

    /** Value of an input on the current page, by name ('' if absent). */
    public function field(string $name): string
    {
        return preg_match('/name="' . preg_quote($name, '/') . '"[^>]*\svalue="([^"]*)"/', $this->body, $m)
            ? html_entity_decode($m[1], ENT_QUOTES) : '';
    }

    /** Value of a response header of the last request ('' if absent). */
    public function header(string $name): string
    {
        return preg_match('/^' . preg_quote($name, '/') . ':\s*(.*?)\r?$/mi', $this->headers, $m) ? $m[1] : '';
    }

    /** Current value of a cookie in this browser's jar. */
    public function cookie(string $name): string
    {
        foreach (file($this->jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $f = explode("\t", $line);
            if (count($f) === 7 && $f[5] === $name) {
                return $f[6];
            }
        }
        return '';
    }

    /** Plant a cookie (e.g. an attacker-chosen session id). */
    public function setCookie(string $name, string $value): void
    {
        $host = parse_url($this->base, PHP_URL_HOST);
        file_put_contents($this->jar, "$host\tFALSE\t/\tFALSE\t0\t$name\t$value\n");
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
        $this->headers = $headers;
        $this->body = substr($raw, $hs);
        $this->location = preg_match('/^Location:\s*(\S+)/mi', $headers, $m) ? $m[1] : '';
        unset($ch);   // frees the handle -> cookie jar written now
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

$sock = '/var/run/mysqld/mysqld.sock';
$db = new PDO((PHP_OS_FAMILY !== 'Windows' && file_exists($sock) && !getenv('DB_ROOT_TCP'))
        ? "mysql:unix_socket=$sock;dbname=splitwise_plus;charset=utf8mb4"
        : 'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=splitwise_plus;charset=utf8mb4',
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
check('H6', 'Dashboard shows Aarav\'s real overall balance (+₹10,091.67)', $aarav->has('you get back') && $aarav->has('₹10,091.67'));

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

// ======================================================================= itemized bills (Phase 6)
$bill = ['group_id' => $gid, 'description' => 'Itemized dinner', 'total_amount' => '704', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'itemized', 'participants' => [2],
    'items' => [
        ['name' => 'Paneer tikka', 'qty' => '1', 'amount' => '300', 'who' => ['2', '3']],
        ['name' => 'Biryani', 'qty' => '', 'amount' => '250', 'who' => ['3']],
        ['name' => 'Lassi', 'qty' => '3', 'amount' => '90', 'who' => ['2', '3', (string) $tid]],
        ['name' => '', 'qty' => '', 'amount' => '', 'who' => []],   // blank row from the form: ignored
    ]];
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', $bill);
$eI = (int) $val("SELECT expense_id FROM expenses WHERE description='Itemized dinner'");
check('IT1', 'Itemized bill: shared + solo items, ₹64 tax spread by subtotal -> 198 / 473 / 33 (participants from items, not checkboxes)',
    $eI > 0 && $val("SELECT GROUP_CONCAT(share_amount ORDER BY FIELD(user_id, 2, 3, $tid)) FROM expense_splits WHERE expense_id=?", [$eI]) === '198.00,473.00,33.00'
    && (int) $val('SELECT COUNT(*) FROM expense_items WHERE expense_id=?', [$eI]) === 3
    && (int) $val('SELECT COUNT(*) FROM expense_item_shares WHERE expense_id=?', [$eI]) === 6
    && $val("SELECT quantity FROM expense_items WHERE expense_id=? AND name='Biryani'", [$eI]) === '1.00');
$aarav->get("/expenses/$eI");
check('IT2', 'Expense page lists the bill items, who had each, and the shared tax',
    str_contains($tx = text($aarav->body), 'Bill items') && str_contains($tx, 'Paneer tikka') && str_contains($tx, 'Tax / service charge') && str_contains($tx, '₹64.00'));
$aarav->get("/expenses/$eI/edit");
check('IT3', 'Edit form is pre-filled with the saved items and "By item" selected',
    $aarav->field('items[2][name]') === 'Lassi' && preg_match('/id="m_itemized"[^>]*checked/', $aarav->body) === 1);
$aarav->get("/expenses/create?group=$gid");
$bad = $bill; $bad['description'] = 'Itemized bad'; $bad['items'][1]['who'] = [];
$aarav->post('/expenses', $bad)->follow();
check('IT4', 'Item nobody ticked: shown on that row, nothing saved, typed rows kept',
    $aarav->has('Tick who had this item.') && $aarav->field('items[1][name]') === 'Biryani'
    && (int) $val("SELECT COUNT(*) FROM expenses WHERE description='Itemized bad'") === 0);
$bad = $bill; $bad['description'] = 'Itemized outsider'; $bad['items'][0]['who'] = ['2', '9'];
$aarav->post('/expenses', $bad)->follow();
check('IT5', 'Item shared with a non-member is rejected',
    $aarav->has('Every participant must be an active group member') && (int) $val("SELECT COUNT(*) FROM expenses WHERE description='Itemized outsider'") === 0);
$bad = $bill; $bad['description'] = 'Itemized huge discount'; $bad['total_amount'] = '0.5'; $bad['items'] = [['name' => 'A', 'qty' => '', 'amount' => '100', 'who' => ['2']]];
$aarav->post('/expenses', $bad);
check('IT6', 'Bill total far below the items (big discount) still splits without negatives',
    (int) $val("SELECT COUNT(*) FROM expense_splits s JOIN expenses e USING (expense_id) WHERE e.description='Itemized huge discount' AND s.share_amount = 0.50") === 1);
$bad = $bill; $bad['description'] = 'Itemized qty'; $bad['items'][0]['qty'] = '100000';
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', $bad)->follow();
check('IT9', 'Quantity above the column limit gets a clear message on that row', $aarav->has('Quantity can be at most 99999.99.'));
// uneven / quantity shares + charge lines
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', ['group_id' => $gid, 'description' => 'Itemized uneven', 'total_amount' => '704', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'itemized',
    'items' => [
        ['name' => 'Butter naan', 'qty' => '4', 'amount' => '200', 'who' => ['2', '3'], 'units' => [2 => '3', 3 => '']],   // Aarav 3, Priya 1
        ['name' => 'Dal', 'qty' => '1', 'amount' => '440', 'who' => ['2'], 'units' => [$tid => '1']],                      // units tick the tester
    ],
    'charges' => [
        ['kind' => 'tax', 'label' => 'CGST 2.5%', 'amount' => '16'], ['kind' => 'tax', 'label' => 'SGST 2.5%', 'amount' => '16'],
        ['kind' => 'service', 'label' => '', 'amount' => '42'], ['kind' => 'discount', 'label' => 'Happy hour', 'amount' => '10'],
        ['kind' => '', 'label' => '', 'amount' => ''],
    ]]);
$eU = (int) $val("SELECT expense_id FROM expenses WHERE description='Itemized uneven'");
check('IT10', 'Uneven units (naan 3:1) and units-only participant: item parts 150/50 and 220/220',
    $eU > 0 && $val("SELECT GROUP_CONCAT(sh.share_amount ORDER BY i.line_no, sh.user_id) FROM expense_item_shares sh JOIN expense_items i USING (item_id) WHERE sh.expense_id=?", [$eU])
        === '150.00,50.00,220.00,220.00'
    && $val("SELECT share_units FROM expense_item_shares sh JOIN expense_items i USING (item_id) WHERE sh.expense_id=? AND i.name='Butter naan' AND sh.user_id=2", [$eU]) === '3.00');
check('IT11', 'Charge lines saved with signs (discount negative, default label) and items + charges = total; shares sum to ₹704',
    $val("SELECT GROUP_CONCAT(CONCAT(kind,':',label,':',amount) ORDER BY line_no SEPARATOR '|') FROM expense_charges WHERE expense_id=?", [$eU])
        === 'tax:CGST 2.5%:16.00|tax:SGST 2.5%:16.00|service:Service charge:42.00|discount:Happy hour:-10.00'
    && $val('SELECT SUM(share_amount) FROM expense_splits WHERE expense_id=?', [$eU]) === '704.00');
$aarav->get("/expenses/$eU");
check('IT12', 'Expense page lists each charge line and the uneven units (×3)', str_contains($tx = text($aarav->body), 'CGST 2.5%') && str_contains($tx, 'Happy hour')
    && str_contains($tx, 'Aarav Sharma ×3'));
$aarav->get("/expenses/$eU/edit");
check('IT13', 'Edit form pre-fills units and charges (discount shown as a positive number)',
    $aarav->field("items[0][units][2]") === '3' && $aarav->field('charges[3][amount]') === '10.00' && $aarav->field('charges[0][label]') === 'CGST 2.5%');
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses', ['group_id' => $gid, 'description' => 'Itemized mismatch', 'total_amount' => '700', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'itemized',
    'items' => [['name' => 'Dal', 'qty' => '', 'amount' => '640', 'who' => ['2']]],
    'charges' => [['kind' => 'tax', 'label' => 'GST', 'amount' => '64'], ['kind' => 'discount', 'label' => '', 'amount' => '-5']]])->follow();
check('IT14', 'A discount typed with a minus sign gets a clear message (discounts are typed positive), nothing saved',
    $aarav->has('Type the discount without a minus sign')
    && (int) $val("SELECT COUNT(*) FROM expenses WHERE description='Itemized mismatch'") === 0);
$aarav->post('/expenses', ['group_id' => $gid, 'description' => 'Itemized mismatch', 'total_amount' => '700', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'itemized',
    'items' => [['name' => 'Dal', 'qty' => '', 'amount' => '640', 'who' => ['2']]],
    'charges' => [['kind' => 'tax', 'label' => 'GST', 'amount' => '64']]])->follow();
check('IT15', 'Items + charges ≠ total -> clear message with both sums, nothing saved',
    $aarav->has('add up to ₹704.00, but the bill total is ₹700.00') && (int) $val("SELECT COUNT(*) FROM expenses WHERE description='Itemized mismatch'") === 0);
$aarav->get("/expenses/$eI/edit");
$aarav->post("/expenses/$eI", ['description' => 'Itemized dinner', 'total_amount' => '704', 'category_id' => '2', 'expense_date' => $today,
    'paid_by' => '2', 'split_method' => 'equal', 'participants' => [2, 3], 'version' => $aarav->field('version')]);
check('IT7', 'Switching an itemized bill to "Equally" removes its items and item shares',
    $val('SELECT split_method FROM expenses WHERE expense_id=?', [$eI]) === 'equal'
    && (int) $val('SELECT COUNT(*) FROM expense_items WHERE expense_id=?', [$eI]) === 0
    && (int) $val('SELECT COUNT(*) FROM expense_item_shares WHERE expense_id=?', [$eI]) === 0
    && $val('SELECT GROUP_CONCAT(share_amount) FROM expense_splits WHERE expense_id=?', [$eI]) === '352.00,352.00');
$aarav->get('/expenses/create');
$aarav->post('/expenses', ['group_id' => '', 'description' => 'Personal itemized', 'total_amount' => '50', 'category_id' => '2',
    'expense_date' => $today, 'split_method' => 'itemized', 'items' => [['name' => 'X', 'qty' => '', 'amount' => '50', 'who' => ['3']]]]);
check('IT8', 'Personal expense ignores "By item" (stays equal, only the owner, no items saved)',
    $val("SELECT split_method FROM expenses WHERE description='Personal itemized'") === 'equal'
    && (int) $val("SELECT COUNT(*) FROM expense_items i JOIN expenses e USING (expense_id) WHERE e.description='Personal itemized'") === 0);

// ======================================================================= Pay with UPI (Phase 6)
$aarav->get('/profile');
$aarav->post('/profile', ['full_name' => 'Aarav Sharma', 'email' => 'aarav@splitwiseplus.test', 'phone' => '9820011111', 'upi_id' => '  Aarav.K@OKICICI ']);
check('U1', 'Profile saves the UPI ID (trimmed, lower-case)', $val('SELECT upi_id FROM users WHERE user_id=2') === 'aarav.k@okicici');
$aarav->post('/profile', ['full_name' => 'Aarav Sharma', 'email' => 'aarav@splitwiseplus.test', 'phone' => '9820011111', 'upi_id' => 'aarav at icici'])->follow();
check('U2', 'Malformed UPI ID rejected with a message, old one kept',
    $aarav->has('Enter a UPI ID like') && $val('SELECT upi_id FROM users WHERE user_id=2') === 'aarav.k@okicici');
$t->get("/groups/$gid/settle?payer=$tid&payee=2&amount=150.5");
check('U3', 'Payer sees "Pay with UPI" with the exact upi://pay link (payee, amount, INR, group note)',
    $t->has('href="upi://pay?pa=aarav.k@okicici&amp;pn=Aarav%20Sharma&amp;am=150.50&amp;cu=INR&amp;tn=')
    && preg_match('/id="upiPay"[^>]*\shidden/', $t->body) === 0);
$t->get("/groups/$gid/settle?payer=2&payee=$tid&amount=150");
check('U4', 'Panel hidden when you are not the one paying', preg_match('/id="upiPay"[^>]*\shidden/', $t->body) === 1);
$t->get("/groups/$gid");
$sneha2 = login($BASE, 'sneha');
$sneha2->get("/groups/$gid/settle");
check('U5', 'UPI ID not shown on other pages, and non-members cannot open the settle page (403)',
    !$t->has('okicici') && $sneha2->status === 403);
$aarav->post('/profile', ['full_name' => 'Aarav Sharma', 'email' => 'aarav@splitwiseplus.test', 'phone' => '9820011111', 'upi_id' => '']);
check('U6', 'Clearing the field removes the UPI ID (NULL)', $val('SELECT upi_id FROM users WHERE user_id=2') === null);
$aarav->post('/profile', ['full_name' => 'Aarav Sharma', 'email' => 'aarav@splitwiseplus.test', 'phone' => '9820011111',
    'upi_id' => str_repeat('a', 64) . '@' . str_repeat('b', 64)]);
$st7 = $aarav->status;
$aarav->follow();
check('U7', 'UPI ID longer than the 100-character column -> field error, not a 500', $st7 === 303 && $aarav->has('UPI ID must be at most 100 characters'));
$aarav->post('/profile', ['full_name' => 'Aarav Sharma', 'email' => 'aarav@splitwiseplus.test', 'phone' => '9820011111', 'upi_id' => 'aarav.k@okicici']);
$aarav->get("/groups/$gid/settle?payer=2&payee=2&amount=100");
check('U8', 'No "Pay with UPI" to yourself (payer = payee)', preg_match('/id="upiPay"[^>]*\shidden/', $aarav->body) === 1);
$aarav->post('/profile', ['full_name' => 'Aarav Sharma', 'email' => 'aarav@splitwiseplus.test', 'phone' => '9820011111', 'upi_id' => 'aarav.sharma@example']);

// server-computed outstanding amount (same rule as sp_record_settlement), full and partial payments
$snehaP = login($BASE, 'sneha');
$maxSql = "SELECT GREATEST(0, LEAST(-fn_member_net_balance(1,5) - (SELECT COALESCE(SUM(amount),0) FROM settlements WHERE group_id=1 AND payer_id=5 AND status='pending'),
            fn_member_net_balance(1,2) - (SELECT COALESCE(SUM(amount),0) FROM settlements WHERE group_id=1 AND payee_id=2 AND status='pending')))";
$max = (string) $val($maxSql);
$snehaP->get('/groups/1/settle?payer=5&payee=2');
check('U9', 'Pay page pre-fills the outstanding amount computed by the server (after pending payments) and offers a full payment',
    $max !== '0.00' && $snehaP->field('amount') === $max && $snehaP->has('Outstanding:') && $snehaP->has('Pay the full amount'));
$snehaP->get('/groups/1/settle?payer=5&payee=2&amount=999999');
$big = $snehaP->field('amount');
$snehaP->get('/groups/1/settle?payer=5&payee=2&amount=100');
check('U10', 'A larger ?amount= is replaced by the outstanding amount; a smaller one is kept (part payment)', $big === $max && $snehaP->field('amount') === '100.00');
check('U11', 'Payer sees "Copy UPI ID" fallback and the "I\'ve paid: mark as sent" button',
    $snehaP->has('id="copyUpi" data-upi="aarav.sharma@example"') && $snehaP->has("I've paid: mark as sent"));
$tok = $snehaP->field('request_token');
$snehaP->post('/settlements', ['group_id' => 1, 'payer_id' => 5, 'payee_id' => 2, 'amount' => '100', 'settlement_date' => $today,
    'method' => 'upi', 'note' => 'UPI ref 1234', 'request_token' => $tok]);
$sidU = (int) $val("SELECT settlement_id FROM settlements WHERE note='UPI ref 1234'");
check('U12', 'Marked as sent -> settlement stays pending (opening a UPI app proves nothing)', $val('SELECT status FROM settlements WHERE settlement_id=?', [$sidU]) === 'pending');
$aaravU = login($BASE, 'aarav');
$aaravU->get('/dashboard');
$hasHint = $aaravU->has('UPI: check your bank or UPI app first');
$aaravU->post("/settlements/$sidU/confirm", ['back' => '/dashboard']);
$aaravU->get('/dashboard');
$aaravU->post("/settlements/$sidU/confirm", ['back' => '/dashboard'])->follow();
check('U13', 'Receiver confirms once (told to check the UPI app first); a second confirm is refused, status stays confirmed, audit kept',
    $hasHint && $val('SELECT status FROM settlements WHERE settlement_id=?', [$sidU]) === 'confirmed'
    && $aaravU->has('Only pending settlements can be answered')
    && (int) $val("SELECT COUNT(*) FROM audit_logs WHERE table_name='settlements' AND record_id=? AND action='UPDATE'", [$sidU]) === 1);

// ======================================================================= bill scan (Phase 6, SCAN_PROVIDER=mock)
$billPng = tempnam(sys_get_temp_dir(), 'bill');
file_put_contents($billPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$aarav->get("/expenses/create?group=$gid");
preg_match_all('/<button[^>]*type="submit"[^>]*>/', explode('id="expenseForm"', $aarav->body, 2)[1] ?? '', $sb);
check('SN1', 'Expense form offers "Scan the bill" with the privacy note; Enter still saves (first submit button is not "Read items")',
    $aarav->has('Scan the bill') && $aarav->has('is not saved') && isset($sb[0][0]) && !str_contains($sb[0][0], 'formaction'));
$before = (int) $val('SELECT COUNT(*) FROM expenses');
$aarav->post('/expenses/scan', ['group_id' => $gid, 'description' => '', 'total_amount' => '', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2', 'split_method' => 'equal'], true, ['bill' => [$billPng, 'image/png', 'bill.png']]);
$loc = $aarav->location;
$aarav->follow();
check('SN2', 'Scan returns to the same form with the bill lines, total and shop name filled in, "By item" chosen, nothing saved',
    str_ends_with($loc, "/expenses/create?group=$gid") && $aarav->field('items[0][name]') === 'Paneer butter masala'
    && $aarav->field('items[3][amount]') === '160.00' && $aarav->field('items[1][qty]') === '4'
    && $aarav->field('total_amount') === '903.00' && $aarav->field('description') === 'Demo Dhaba'
    && preg_match('/id="m_itemized"[^>]*checked/', $aarav->body) === 1 && $aarav->has('Read 4 items from the bill')
    && (int) $val('SELECT COUNT(*) FROM expenses') === $before);
$aarav->post('/expenses/scan', ['group_id' => $gid, 'description' => 'Lunch at work', 'total_amount' => '950', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => '2'], true, ['bill' => [$billPng, 'image/png', 'bill.png']])->follow();
check('SN3', 'What you already typed (description, total) is kept, not overwritten by the scan',
    $aarav->field('description') === 'Lunch at work' && $aarav->field('total_amount') === '950');
$aarav->post('/expenses/scan', ['group_id' => $gid], true, ['bill' => [$billPng, 'image/png', 'bill.png'], 'receipt' => [$billPng, 'image/png', 'r.png']])->follow();
check('SN9', 'If a receipt file was chosen, the scan tells you to choose it again (file fields reset on reload)', $aarav->has('Choose the receipt file again'));
$aarav->post('/expenses/scan', ['group_id' => $gid, 'description' => '', 'total_amount' => '', 'category_id' => '2', 'paid_by' => '2',
    'expense_date' => $today], true, ['bill' => [$billPng, 'image/png', 'bill.png']])->follow();
check('SN10', 'Scan also fills the bill date and the CGST/SGST charge lines, flags the hard-to-read line, warns, and asks for a review tick; nobody is pre-assigned',
    $aarav->field('expense_date') === '2026-11-20' && $aarav->field('charges[0][label]') === 'CGST 2.5%' && $aarav->field('charges[1][amount]') === '21.50'
    && $aarav->has('item-row item-uncertain') && $aarav->has('Hard to read, check these lines: Jeera rice')
    && $aarav->has('id="scan_reviewed"') && !preg_match('/name="items\[\d+\]\[who\]\[\]" value="\d+"[^>]*checked/', $aarav->body));
$scanSave = ['group_id' => $gid, 'description' => 'Scanned dinner', 'total_amount' => '903', 'category_id' => '2', 'expense_date' => '2026-11-20',
    'paid_by' => '2', 'split_method' => 'itemized', 'scanned' => '1',
    'items' => [['name' => 'Paneer butter masala', 'qty' => '1', 'amount' => '320', 'who' => ['2', '3']],
                ['name' => 'Butter naan', 'qty' => '4', 'amount' => '200', 'who' => ['3']],
                ['name' => 'Jeera rice', 'qty' => '1', 'amount' => '180', 'who' => [(string) $tid]],
                ['name' => 'Sweet lassi', 'qty' => '2', 'amount' => '160', 'who' => ['2', '3', (string) $tid]]],
    'charges' => [['kind' => 'tax', 'label' => 'CGST 2.5%', 'amount' => '21.50'], ['kind' => 'tax', 'label' => 'SGST 2.5%', 'amount' => '21.50']]];
$aarav->post('/expenses', $scanSave)->follow();
$noTick = (int) $val("SELECT COUNT(*) FROM expenses WHERE description='Scanned dinner'");
check('SN11', 'A scanned bill is not saved until the review box is ticked', $noTick === 0 && $aarav->has('confirm you checked the scanned lines'));
$aarav->post('/expenses', $scanSave + ['scan_reviewed' => '1']);
check('SN12', 'After the review tick it saves through the normal path: items, both charges, splits = ₹903',
    $val("SELECT SUM(s.share_amount) FROM expense_splits s JOIN expenses e USING (expense_id) WHERE e.description='Scanned dinner'") === '903.00'
    && (int) $val("SELECT COUNT(*) FROM expense_charges c JOIN expenses e USING (expense_id) WHERE e.description='Scanned dinner'") === 2);
$txt = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($txt, "%PDF-1.4 not an image");
$aarav->post('/expenses/scan', ['group_id' => $gid, 'description' => 'Kept'], true, ['bill' => [$txt, 'image/png', 'bill.png']])->follow();
check('SN4', 'Non-image (MIME from content) refused with a message; typed fields kept', $aarav->has('must be a JPG, PNG or WebP') && $aarav->field('description') === 'Kept');
$aarav->post('/expenses/scan', ['group_id' => $gid])->follow();
check('SN5', 'No photo chosen -> clear message', $aarav->has('Choose a photo of the bill first'));
$sneha3 = login($BASE, 'sneha');
$sneha3->get('/expenses/create');
$sneha3->post("/expenses/scan?expense=$e1", [], true, ['bill' => [$billPng, 'image/png', 'bill.png']]);
check('SN6', "Cannot scan into someone else's group expense (403)", $sneha3->status === 403);
$aarav->get("/expenses/create?group=$gid");
$aarav->post('/expenses/scan', ['group_id' => $gid], 'bad-token', ['bill' => [$billPng, 'image/png', 'bill.png']]);
check('SN7', 'Scan needs the CSRF token (419)', $aarav->status === 419);
// daily limit is 4 in tests/functional/run.sh: SN2, SN3, SN9, SN10 used 4 (SN4/SN5 failed before counting)
$aarav->get("/expenses/create?group=$gid");
for ($k = 0; $k < 1; $k++) {
    $aarav->post('/expenses/scan', ['group_id' => $gid], true, ['bill' => [$billPng, 'image/png', 'bill.png']])->follow();
}
$priyaS = login($BASE, 'priya');
$db->exec("UPDATE app_kv SET kv_value = '1000' WHERE kv_key LIKE 'scan:month:%' AND kv_key NOT LIKE '%:failed'");
$priyaS->get("/expenses/create?group=$gid");
$priyaS->post('/expenses/scan', ['group_id' => $gid], true, ['bill' => [$billPng, 'image/png', 'bill.png']])->follow();
$capped = $priyaS->has('reached its monthly limit');
$adm = login($BASE, 'admin');
$adm->get('/admin');
check('SN13', 'Site-wide monthly scan cap stops scanning for everyone; admin page shows scans, failures and estimated cost',
    $capped && $adm->has('Bill scanning (') && $adm->has('Scans this month') && $adm->has('Estimated cost'));
check('SN8', 'Per-user daily scan limit enforced (counted in MySQL app_kv)', $aarav->has('used today&#039;s 4 bill scans')
    && (int) $val("SELECT MAX(CAST(kv_value AS UNSIGNED)) FROM app_kv WHERE kv_key LIKE 'scan:u:2:%'") === 5);

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
check('R1', 'PNG receipt bytes stored in MySQL (receipts.content) under a random key, not on local disk',
    $rid > 0 && preg_match('/^[0-9a-f]{32}\.png$/', $stored) === 1
    && $val('SELECT content FROM receipts WHERE receipt_id=?', [$rid]) === file_get_contents($png)
    && !is_file(__DIR__ . '/../../storage/receipts/' . $stored));
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
    $aarav->has('Suggested settlements') && $aarav->has('Who owes whom') && $aarav->has(' pays '));
$aarav->get('/groups/1');
check('S9', 'Goa group: 3 suggested transfers from the sample data', substr_count($aarav->body, '>Record</a>') >= 1
    && substr_count($aarav->body, 'data-suggestion') === 3);

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
$appA = new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=splitwise_plus', 'splitwise_app', getenv('APP_DB_PASS') ?: 'Change_Me_123!', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$appB = new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=splitwise_plus', 'splitwise_app', getenv('APP_DB_PASS') ?: 'Change_Me_123!', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
$tOther = new Browser($BASE);                                  // the same account on another device
$tOther->get('/login');
$tOther->post('/login', ['login' => 'tester', 'password' => 'Secret123']);
$tOther->get('/dashboard');
$otherWasIn = $tOther->status === 200;
$t->post('/profile/password', ['current_password' => 'Secret123', 'password' => 'NewSecret456', 'password_confirm' => 'NewSecret456']);
check('P4', 'Password changed (new hash verifies)', password_verify('NewSecret456', (string) $val("SELECT password_hash FROM users WHERE user_id=?", [$tid])));
$tOther->get('/dashboard');
$t->get('/dashboard');
check('P7', 'Changing the password logs out the other devices but keeps this one', $otherWasIn && $tOther->status === 303 && $t->status === 200);

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

// ======================================================================= deployment readiness (Phase 5.5)
$p = new Browser($BASE);
$p->get('/healthz');
check('X1', '/healthz -> 200 {"status":"ok"}, no session cookie, no-store',
    $p->status === 200 && trim($p->body) === '{"status":"ok"}' && $p->cookie('splitwise_sess') === '' && $p->header('Cache-Control') === 'no-store');
$p->get('/readyz');
check('X2', '/readyz -> 200 {"status":"ready"} (DB reachable), body leaks nothing else',
    $p->status === 200 && trim($p->body) === '{"status":"ready"}');
$p->post('/readyz', [], false);
check('X3', 'Health endpoints accept only GET/HEAD (405)', $p->status === 405);
$p->get('/login');
$csp = $p->header('Content-Security-Policy');
check('X4', 'CSP header: scripts only from self, no framing, no plugins',
    str_contains($csp, "script-src 'self'") && str_contains($csp, "frame-ancestors 'none'") && str_contains($csp, "object-src 'none'")
    && !str_contains($csp, "script-src 'self' 'unsafe-inline'"));
$inline = 0;
$pv = login($BASE, 'priya');
foreach (['/dashboard', '/groups', '/expenses', '/expenses/create', '/settlements', '/balances', '/budgets', '/budgets/create', '/analytics', '/notifications', '/profile'] as $pg) {
    $pv->get($pg);
    $inline += preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>|\son[a-z]+\s*=\s*"/i', $pv->body);
}
check('X5', 'No inline <script> or on*= handlers on 11 main pages (CSP-safe)', $inline === 0, "found $inline");
$sid = $pv->cookie('splitwise_sess');
check('X6', 'Session stored in MySQL app_sessions (shared by all instances)',
    $sid !== '' && (int) $val('SELECT COUNT(*) FROM app_sessions WHERE session_id=?', [$sid]) === 1
    && str_contains((string) $val('SELECT payload FROM app_sessions WHERE session_id=?', [$sid]), 'user_id'));
$pv->post('/logout');
check('X7', 'Logout deletes the session row', (int) $val('SELECT COUNT(*) FROM app_sessions WHERE session_id=?', [$sid]) === 0);
$fx = new Browser($BASE);
$fx->setCookie('splitwise_sess', 'attackerchosenid1234567890abcdef');
$fx->get('/login');
check('X8', 'Unknown session id from the client is replaced (strict mode, no fixation)',
    $fx->cookie('splitwise_sess') !== 'attackerchosenid1234567890abcdef' && $fx->cookie('splitwise_sess') !== ''
    && (int) $val("SELECT COUNT(*) FROM app_sessions WHERE session_id='attackerchosenid1234567890abcdef'") === 0);
$old = login($BASE, 'kabir');
$oldSid = $old->cookie('splitwise_sess');
$db->prepare('UPDATE app_sessions SET last_activity = UNIX_TIMESTAMP() - 8000 WHERE session_id=?')->execute([$oldSid]);
$old->get('/dashboard');
check('X9', 'Session idle longer than SESSION_LIFETIME (2 h) is expired server-side', $old->status === 303 && str_ends_with($old->location, '/login'));
check('X10', 'Login-throttle counters kept in MySQL (app_kv), hashed keys (no e-mail/IP in clear)',
    (int) $val("SELECT COUNT(*) FROM app_kv WHERE kv_key LIKE 'thr:a:%'") >= 1
    && (int) $val("SELECT COUNT(*) FROM app_kv WHERE kv_key LIKE '%meera%' OR kv_key LIKE '%127.0.0.1%'") === 0);
check('X11', 'Mock-mail cursor kept in MySQL (app_kv), equals newest notification',
    (int) $val("SELECT kv_value FROM app_kv WHERE kv_key='mail:cursor'") === (int) $val('SELECT MAX(notification_id) FROM notifications'));
$x = new Browser($BASE);
$x->get('/login');
check('X12', 'Local http: session cookie not forced Secure; HttpOnly + SameSite=Lax set',
    (bool) preg_match('/^Set-Cookie: splitwise_sess=[^\r\n]*HttpOnly[^\r\n]*SameSite=Lax/mi', $x->headers)
    && !preg_match('/^Set-Cookie: splitwise_sess=[^\r\n]*;\s*secure/mi', $x->headers));

// ======================================================================= input robustness (full audit, Nov 2026)
$logFile = __DIR__ . '/../../storage/logs/php-error.log';
$logBefore = is_file($logFile) ? filesize($logFile) : 0;
$ad = login($BASE, 'admin');
$codes = [];
foreach (['/expenses?page=999999999999999999999', '/notifications?page=999999999999999999999', '/settlements?page=999999999999999999999',
          '/admin/users?page=999999999999999999999', '/admin/groups?page=9223372036854775807', '/admin/activity?page=999999999999999999999'] as $u) {
    $codes[] = ($u[1] === 'a' ? $ad : $aarav)->get($u)->status;
}
check('X13', 'Huge ?page= values are clamped (no LIMIT/OFFSET overflow 500)', $codes === [200, 200, 200, 200, 200, 200], implode(',', $codes));
$aarav->get('/expenses?q[]=x&group[]=1&category[]=1&from[]=1&tag[]=x&page[]=2');
$okArr = $aarav->status === 200;
$aarav->get('/expenses?from=2026-13-45&to=2026-02-30');
$okDate = $aarav->status === 200;
$aarav->get('/expenses/create');
$aarav->post('/expenses', ['description' => ['x'], 'total_amount' => ['5'], 'group_id' => ['1'], 'split_method' => ['equal'], 'participants' => [['nested']]]);
$okPostArr = $aarav->status === 303 && (int) $val("SELECT COUNT(*) FROM expenses WHERE description = 'Array'") === 0;
$logNew = is_file($logFile) ? (string) substr((string) file_get_contents($logFile), $logBefore) : '';
check('X14', 'Array-valued query/POST fields and impossible dates handled quietly (200/303, no "Array" rows, no PHP warnings)',
    $okArr && $okDate && $okPostArr && !str_contains($logNew, 'Array to string conversion'), trim(substr($logNew, 0, 200)));
$raw = new Browser($BASE);
$raw->get('/login');
$raw->post('/login', ['_csrf' => ['x'], 'login' => 'aarav', 'password' => 'Password@123'], false);
check('X15', 'CSRF token sent as an array -> 419, not a 500 TypeError', $raw->status === 419);
$sid = (int) $val("SELECT settlement_id FROM settlements WHERE created_by = (SELECT user_id FROM users WHERE username='aarav') AND status <> 'pending' LIMIT 1");
$aarav->get('/settlements');
$aarav->post("/settlements/$sid/cancel", ['back' => '//evilhost']);
$l1 = $aarav->location;
$aarav->get('/settlements');
$aarav->post("/settlements/$sid/cancel", ['back' => '/groups/1']);
check('X16', 'Settlement "back" redirect: //host refused (no open redirect), local path kept',
    $sid > 0 && str_ends_with($l1, '/settlements') && !str_contains($l1, 'evil') && str_ends_with($aarav->location, '/groups/1'), "$l1 | {$aarav->location}");

/** Page text with tags removed and whitespace collapsed. */
function text(string $html): string
{
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}
/** "33342.33" -> "₹33,342.33" (Indian digit grouping, like the app's inr()). */
function inr_t(string $v): string
{
    [$i, $d] = array_pad(explode('.', number_format((float) $v, 2, '.', '')), 2, '00');
    $last3 = substr($i, -3); $rest = substr($i, 0, -3);
    $rest = $rest === '' ? '' : (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',';
    return '₹' . $rest . $last3 . '.' . $d;
}
$bandOk = true; $bandInfo = '';
foreach (['priya', 'aarav', 'kabir', 'sneha', 'rohan'] as $un) {
    $uidX = (int) $val('SELECT user_id FROM users WHERE username = ?', [$un]);
    $in  = (string) $val('SELECT COALESCE(SUM(ABS(outstanding)),0) FROM v_pairwise_debts WHERE (user_b = ? AND outstanding > 0) OR (user_a = ? AND outstanding < 0)', [$uidX, $uidX]);
    $out = (string) $val('SELECT COALESCE(SUM(ABS(outstanding)),0) FROM v_pairwise_debts WHERE (user_a = ? AND outstanding > 0) OR (user_b = ? AND outstanding < 0)', [$uidX, $uidX]);
    $net = (string) $val('SELECT COALESCE(net_balance,0) FROM v_user_overall_balance WHERE user_id = ?', [$uidX]);
    $bx = login($BASE, $un);
    $bx->get('/dashboard');
    $page = text($bx->body);
    $ok = str_contains($page, 'Owed to you ' . inr_t($in)) && str_contains($page, 'You owe ' . inr_t($out)) && (int) round(((float) $in - (float) $out) * 100) === (int) round((float) $net * 100);
    if (!$ok) { $bandOk = false; $bandInfo .= "$un in=$in out=$out net=$net; "; }
}
check('X17', 'Dashboard "Owed to you" / "You owe" = sum of the "Who owes whom" debts, and their difference = net balance (5 users)', $bandOk, $bandInfo);

// ======================================================================= password reset (Phase 6, MAIL_DRIVER=log -> storage/logs/mail.log)
$mailFile = __DIR__ . '/../../storage/logs/mail.log';
$guestR = new Browser($BASE);
$guestR->get('/login');
$hasLink = $guestR->has('Forgot password?');
$guestR->get('/forgot-password');
$guestR->post('/forgot-password', ['email' => 'nobody@example.com'])->follow();
$noAcct = text($guestR->body);
$guestR->get('/forgot-password');
$guestR->post('/forgot-password', ['email' => 'Ananya@SplitwisePlus.test'])->follow();
check('PR1', 'Forgot-password: link on the login page; identical answer for an unknown e-mail and a real one (no account probing)',
    $hasLink && str_contains($noAcct, 'If an account uses that e-mail') && str_contains(text($guestR->body), 'If an account uses that e-mail')
    && (int) $val('SELECT COUNT(*) FROM password_resets WHERE user_id = 7') === 1 && (int) $val('SELECT COUNT(*) FROM password_resets') === 1);
$mailTxt = (string) @file_get_contents($mailFile);
preg_match_all('#/reset-password\?token=([0-9a-f]{64})#', $mailTxt, $tm);
$rtok = end($tm[1]) ?: '';
check('PR2', 'The e-mail carries a one-hour link; only the SHA-256 of the token is stored',
    $rtok !== '' && str_contains($mailTxt, 'ananya@splitwiseplus.test') && $val('SELECT token_hash FROM password_resets WHERE user_id = 7') === hash('sha256', $rtok)
    && (int) $val('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$rtok]) === 0
    && abs((int) $val('SELECT TIMESTAMPDIFF(MINUTE, created_at, expires_at) FROM password_resets WHERE user_id = 7') - 60) <= 1);
$other = login($BASE, 'ananya');                       // a session on "another device"
$other->get('/dashboard');
$otherIn = $other->status === 200;
$guestR->get("/reset-password?token=$rtok");
$formOk = $guestR->has('Choose a new password') && $guestR->header('Referrer-Policy') === 'no-referrer';
$guestR->post('/reset-password', ['token' => $rtok, 'password' => 'short', 'password_confirm' => 'short'])->follow();
$weak = $guestR->has('at least 8 characters');
$guestR->post('/reset-password', ['token' => $rtok, 'password' => 'NewSecret123', 'password_confirm' => 'NewSecret123'])->follow();
$other->get('/dashboard');
$try = new Browser($BASE); $try->get('/login'); $try->post('/login', ['login' => 'ananya', 'password' => 'Password@123']);
$oldFails = !str_ends_with($try->location, '/dashboard');
$try2 = new Browser($BASE); $try2->get('/login'); $try2->post('/login', ['login' => 'ananya', 'password' => 'NewSecret123']);
check('PR3', 'Reset form (no-referrer) rejects a weak password; a good one works, the old one stops working, and every other session is logged out',
    $formOk && $weak && $guestR->has('Password changed') && $otherIn && $other->status === 303 && $oldFails && str_ends_with($try2->location, '/dashboard'));
$g2 = new Browser($BASE);
$g2->get("/reset-password?token=$rtok");
$reused = $g2->has('expired or was already used');
$g2->get('/login');
$g2->post('/reset-password', ['token' => $rtok, 'password' => 'Another123x', 'password_confirm' => 'Another123x'])->follow();
check('PR4', 'A used link cannot be used again (page and POST both refuse)', $reused && $g2->has('expired or was already used'));
$g3 = new Browser($BASE);
$g3->get('/forgot-password');
$g3->post('/forgot-password', ['email' => 'kabir@splitwiseplus.test']);
preg_match_all('#/reset-password\?token=([0-9a-f]{64})#', (string) @file_get_contents($mailFile), $tm2);
$ktok = end($tm2[1]) ?: '';
$val("UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE user_id = 6");
$g3->get("/reset-password?token=$ktok");
check('PR5', 'An expired link is refused', $ktok !== '' && $g3->has('expired or was already used'));
for ($k = 0; $k < 3; $k++) { $g3->get('/forgot-password'); $g3->post('/forgot-password', ['email' => 'kabir@splitwiseplus.test']); }
check('PR6', 'Reset requests are throttled per e-mail (3 per hour): 4 requests -> only 3 e-mails, same answer each time',
    substr_count((string) @file_get_contents($mailFile), "To: Kabir Singh <kabir@splitwiseplus.test>\nSubject: [SplitWise+] Reset your password") === 3
    && (int) $val('SELECT COUNT(*) FROM password_resets WHERE user_id = 6') === 1);

// ======================================================================= Phase 6 end-to-end workflow
// receipt -> scan (mock OCR) -> review -> assign (incl. uneven units) -> tax -> expense -> balances -> UPI handoff -> receiver confirms -> settled
$net = fn(int $u) => (string) $val('SELECT fn_member_net_balance(?, ?)', [$gid, $u]);
$before = [2 => $net(2), 3 => $net(3), $tid => $net($tid)];
$pr = login($BASE, 'priya');                 // Priya pays the restaurant this time
$pr->get("/expenses/create?group=$gid");
$val("DELETE FROM app_kv WHERE kv_key LIKE 'scan:%'");   // fresh scan quota for this flow
$pr->post('/expenses/scan', ['group_id' => $gid, 'description' => '', 'total_amount' => '', 'category_id' => '2', 'paid_by' => '3',
    'expense_date' => $today], true, ['bill' => [$billPng, 'image/png', 'bill.png']])->follow();
$scanOk = $pr->field('items[0][name]') === 'Paneer butter masala' && $pr->field('total_amount') === '903.00' && $pr->has('id="scan_reviewed"');
// the person reviews: corrects nothing, assigns people (naan 3:1 Priya:tester), keeps the scanned charges, ticks the review box
$e2e = ['group_id' => $gid, 'description' => $pr->field('description'), 'total_amount' => $pr->field('total_amount'), 'category_id' => '2',
    'expense_date' => $pr->field('expense_date'), 'paid_by' => '3', 'split_method' => 'itemized', 'scanned' => '1', 'scan_reviewed' => '1',
    'items' => [
        ['name' => $pr->field('items[0][name]'), 'qty' => $pr->field('items[0][qty]'), 'amount' => $pr->field('items[0][amount]'), 'who' => ['2', '3']],
        ['name' => $pr->field('items[1][name]'), 'qty' => $pr->field('items[1][qty]'), 'amount' => $pr->field('items[1][amount]'), 'who' => ['3', (string) $tid],
         'units' => [3 => '3', $tid => '1']],
        ['name' => $pr->field('items[2][name]'), 'qty' => $pr->field('items[2][qty]'), 'amount' => $pr->field('items[2][amount]'), 'who' => [(string) $tid]],
        ['name' => $pr->field('items[3][name]'), 'qty' => $pr->field('items[3][qty]'), 'amount' => $pr->field('items[3][amount]'), 'who' => ['2', (string) $tid]],
    ],
    'charges' => [
        ['kind' => $pr->field('charges[0][amount]') !== '' ? 'tax' : '', 'label' => $pr->field('charges[0][label]'), 'amount' => $pr->field('charges[0][amount]')],
        ['kind' => $pr->field('charges[1][amount]') !== '' ? 'tax' : '', 'label' => $pr->field('charges[1][label]'), 'amount' => $pr->field('charges[1][amount]')],
    ]];
$pr->post('/expenses', $e2e);
$eE = (int) $val("SELECT MAX(expense_id) FROM expenses WHERE description = 'Demo Dhaba' AND paid_by = 3");
// expected: Paneer 320 -> 160/160 (A,P); Naan 200 -> 150 P / 50 T; Rice 180 -> T; Lassi 160 -> 80/80 (A,T)
// subtotals A 240, P 310, T 310 = 860; tax 43 by subtotal -> A 12, P 15.5, T 15.5 => A 252.00, P 325.50, T 325.50 (sum 903)
$shares = $val("SELECT GROUP_CONCAT(share_amount ORDER BY FIELD(user_id, 2, 3, $tid)) FROM expense_splits WHERE expense_id = ?", [$eE]);
$after = [2 => $net(2), 3 => $net(3), $tid => $net($tid)];
$delta = fn(int $u) => round((float) $after[$u] - (float) $before[$u], 2);
check('E2E1', 'Scanned bill -> reviewed -> assigned (uneven naan) -> CGST/SGST shared by subtotal -> saved: 252.00 / 325.50 / 325.50 = ₹903',
    $scanOk && $shares === '252.00,325.50,325.50' && (int) $val('SELECT COUNT(*) FROM expense_charges WHERE expense_id = ?', [$eE]) === 2, (string) $shares);
check('E2E2', 'Balances move by exactly paid − share: Aarav −252.00, Priya +577.50, tester −325.50; group still sums to 0',
    $delta(2) === -252.0 && $delta(3) === 577.5 && $delta($tid) === -325.5
    && $val('SELECT SUM(net_balance) FROM v_group_member_balances WHERE group_id = ?', [$gid]) === '0.00');
$pr->post('/profile', ['full_name' => 'Priya Nair', 'email' => 'priya@splitwiseplus.test', 'phone' => '9820022222', 'upi_id' => 'priya.nair@example']);
$val('UPDATE users SET is_active = 1 WHERE user_id = ?', [$tid]);   // the admin tests deactivated the tester; bring them back
$t = new Browser($BASE);
$t->get('/login');
$t->post('/login', ['login' => 'tester', 'password' => 'NewSecret456']);
$t->get("/groups/$gid/settle?payer=$tid&payee=3");
$owed = $t->field('amount');
$maxT = (string) $val("SELECT GREATEST(0, LEAST(-fn_member_net_balance(?, ?) - (SELECT COALESCE(SUM(amount),0) FROM settlements WHERE group_id=? AND payer_id=? AND status='pending'),
            fn_member_net_balance(?, 3) - (SELECT COALESCE(SUM(amount),0) FROM settlements WHERE group_id=? AND payee_id=3 AND status='pending')))", [$gid, $tid, $gid, $tid, $gid, $gid]);
check('E2E3', 'Pay page: server-computed amount, UPI link to Priya with exactly that amount',
    $owed === $maxT && $owed !== '0.00' && $t->has('href="upi://pay?pa=priya.nair@example&amp;pn=Priya%20Nair&amp;am=' . $owed . '&amp;cu=INR'));
$t->post('/settlements', ['group_id' => $gid, 'payer_id' => $tid, 'payee_id' => 3, 'amount' => $owed, 'settlement_date' => $today,
    'method' => 'upi', 'note' => 'E2E UPI ref 777', 'request_token' => $t->field('request_token')]);
$sE = (int) $val("SELECT settlement_id FROM settlements WHERE note = 'E2E UPI ref 777'");
$pendingNet = $net($tid);
check('E2E4', 'Marked as sent -> pending; balances do not change yet', $val('SELECT status FROM settlements WHERE settlement_id = ?', [$sE]) === 'pending' && $pendingNet === $after[$tid]);
$pr->get('/dashboard');
$pr->post("/settlements/$sE/confirm", ['back' => '/dashboard']);
$t->get("/groups/$gid/settle?payer=$tid&payee=3");
check('E2E5', 'Priya confirms receipt -> confirmed; the debt is cleared (nothing more to pay her) and the group still sums to 0',
    $val('SELECT status FROM settlements WHERE settlement_id = ?', [$sE]) === 'confirmed'
    && (float) $net($tid) === round((float) $after[$tid] + (float) $owed, 2)
    && !$t->has('Outstanding:') && $val('SELECT SUM(net_balance) FROM v_group_member_balances WHERE group_id = ?', [$gid]) === '0.00');

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
