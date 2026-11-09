<?php
/**
 * SplitWise+ database test suite (Phase 2).
 *
 * Precondition: database/splitwise_plus.sql freshly imported.
 * Usage:  php tests/database/run_db_tests.php
 * Env:    DB_HOST (default localhost), DB_PORT (3306), DB_USER (root), DB_PASS (''),
 *         DB_SOCKET (optional unix socket)
 *
 * Tests that change data run inside a transaction and roll back. Tests at the
 * end that call committing procedures change data: re-import afterwards
 * (tests/database/run.sh does this automatically).
 */
declare(strict_types=1);

function connect(?string $user = null, ?string $pass = null, ?string $forceHost = null): PDO {
    $host = $forceHost ?? (getenv('DB_HOST') ?: 'localhost');
    $port = getenv('DB_PORT') ?: '3306';
    $sock = $forceHost === null ? (getenv('DB_SOCKET') ?: '') : '';
    $dsn  = $sock !== ''
        ? "mysql:unix_socket=$sock;dbname=splitwise_plus;charset=utf8mb4"
        : "mysql:host=$host;port=$port;dbname=splitwise_plus;charset=utf8mb4";
    return new PDO($dsn, $user ?? (getenv('DB_USER') ?: 'root'), $pass ?? (getenv('DB_PASS') ?: ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

$db = connect();
$results = [];

function record(string $id, string $name, bool $ok, string $detail = ''): void {
    global $results;
    $results[] = [$id, $name, $ok, $detail];
    printf("%-5s %-4s %s%s\n", $id, $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? "  [$detail]" : '');
}
function val(PDO $db, string $sql, array $p = []) {
    $st = $db->prepare($sql); $st->execute($p);
    return $st->fetchColumn();
}
function rows(PDO $db, string $sql, array $p = []): array {
    $st = $db->prepare($sql); $st->execute($p);
    return $st->fetchAll();
}
/** Expect $fn to throw a PDOException whose message contains $needle. Rolls back. */
function expectError(PDO $db, string $id, string $name, callable $fn, string $needle): void {
    $db->beginTransaction();
    try {
        $fn($db);
        record($id, $name, false, 'no error raised');
    } catch (PDOException $e) {
        $ok = stripos($e->getMessage(), $needle) !== false;
        record($id, $name, $ok, $ok ? $needle : $e->getMessage());
    } finally {
        if ($db->inTransaction()) { $db->rollBack(); }
    }
}
function inTx(PDO $db, callable $fn) {
    $db->beginTransaction();
    try { return $fn($db); } finally { if ($db->inTransaction()) { $db->rollBack(); } }
}
function exec_(PDO $db, string $sql, array $p = []): void { $db->prepare($sql)->execute($p); }

$db->exec('SET @app_user_id = 1');

// ---------------------------------------------------------------- structure
$cnt = fn(string $type) => (int) val($db,
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_plus' AND TABLE_TYPE=?", [$type]);
record('S1', '16 base tables exist (14 business + app_sessions, app_kv)', $cnt('BASE TABLE') === 16);
record('S2', '11 views exist', $cnt('VIEW') === 11);
record('S3', '11 triggers covering all 6 timing/event pairs',
    (int) val($db, "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='splitwise_plus'") === 11
 && (int) val($db, "SELECT COUNT(DISTINCT ACTION_TIMING, EVENT_MANIPULATION) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='splitwise_plus'") === 6);
record('S4', '5 procedures + 3 functions exist',
    (int) val($db, "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='splitwise_plus' AND ROUTINE_TYPE='PROCEDURE'") === 5
 && (int) val($db, "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='splitwise_plus' AND ROUTINE_TYPE='FUNCTION'") === 3);
record('S5', 'No FLOAT/DOUBLE columns anywhere',
    (int) val($db, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_plus' AND DATA_TYPE IN ('float','double')") === 0);

record('S6', 'Text stored as real UTF-8 (no double-encoded "Ã"/"â€" sequences)',
    (int) val($db, "SELECT COUNT(*) FROM expenses WHERE description COLLATE utf8mb4_bin LIKE '%Ã%' OR description COLLATE utf8mb4_bin LIKE '%â€%'") === 0
 && val($db, "SELECT HEX('₹')") === 'E282B9');

// ---------------------------------------------------------------- financial
$bad = rows($db, 'SELECT group_id, SUM(net_balance) s FROM v_group_member_balances GROUP BY group_id HAVING s <> 0');
record('F1', 'Every group: sum of net balances = 0', $bad === []);

$expected = [
    [1, 2, '9991.67'], [1, 3, '3429.16'], [1, 4, '-6287.50'], [1, 5, '-7133.33'],
    [2, 3, '26396.50'], [2, 6, '-10133.51'], [2, 7, '-16262.99'], [2, 9, '0.00'],
    [3, 4, '450.00'], [3, 2, '100.00'], [3, 6, '600.00'], [3, 8, '-1150.00'],
    [4, 5, '0.00'], [4, 7, '0.00'], [5, 4, '0.00'], [5, 5, '0.00'], [5, 6, '0.00'],
];
$mismatch = [];
foreach ($expected as [$g, $u, $net]) {
    $got = val($db, 'SELECT net_balance FROM v_group_member_balances WHERE group_id=? AND user_id=?', [$g, $u]);
    if ($got !== $net) { $mismatch[] = "g$g/u$u got $got want $net"; }
}
record('F2', 'All 17 member balances equal hand-computed values', $mismatch === [], implode('; ', $mismatch));

record('F3', 'Every expense: shares sum to total (gap = 0)',
    (int) val($db, 'SELECT COUNT(*) FROM expenses WHERE fn_expense_split_gap(expense_id) <> 0') === 0);
record('F4', 'fn_member_net_balance agrees with view for every member',
    (int) val($db, 'SELECT COUNT(*) FROM v_group_member_balances WHERE fn_member_net_balance(group_id,user_id) <> net_balance') === 0);
record('F5', 'fn_budget_spent agrees with v_budget_utilization',
    (int) val($db, 'SELECT COUNT(*) FROM v_budget_utilization WHERE fn_budget_spent(budget_id) <> spent') === 0);

$e6 = rows($db, "SELECT s.user_id, s.share_amount FROM expense_splits s JOIN expenses e USING(expense_id)
                 WHERE e.description='Breakfast at café' ORDER BY s.user_id");
record('F6', 'Equal 1000/3: payer gets 333.34, others 333.33',
    array_column($e6, 'share_amount', 'user_id') === [2 => '333.33', 3 => '333.34', 5 => '333.33']);
$e14 = rows($db, "SELECT s.user_id, s.share_amount FROM expense_splits s JOIN expenses e USING(expense_id)
                  WHERE e.description='DMart groceries' ORDER BY s.user_id");
record('F7', 'Equal 2780/3: leftover paise to payer then lowest id',
    array_column($e14, 'share_amount', 'user_id') === [3 => '926.67', 6 => '926.67', 7 => '926.66']);

$pairMismatch = (int) val($db, "
  SELECT COUNT(*) FROM v_group_member_balances b
   WHERE b.net_balance <> COALESCE((SELECT SUM(outstanding) FROM v_pairwise_debts p
                                     WHERE p.group_id=b.group_id AND p.user_b=b.user_id),0)
                        - COALESCE((SELECT SUM(outstanding) FROM v_pairwise_debts p
                                     WHERE p.group_id=b.group_id AND p.user_a=b.user_id),0)");
record('F8', 'Pairwise debts reconcile to net balances for every member', $pairMismatch === 0);
record('F9', 'Overall balances across all users sum to 0',
    val($db, 'SELECT SUM(net_balance) FROM v_user_overall_balance') === '0.00');
record('F10', 'Soft-deleted expense excluded from balances (Goa total = 54150)',
    val($db, 'SELECT total_spent FROM v_group_expense_summary WHERE group_id=1') === '54150.00');

// Spec scenario: dinner 1500, A paid, A/B/C equal; B pays A 300 -> B -200, A +700
$spec = inTx($db, function (PDO $db) {
    exec_($db, "INSERT INTO expense_groups (group_id,name,created_by) VALUES (900,'Spec test',2)");
    exec_($db, "INSERT INTO group_members (group_id,user_id,role) VALUES (900,2,'admin'),(900,3,'member'),(900,4,'member')");
    exec_($db, "INSERT INTO expenses (expense_id,group_id,paid_by,created_by,category_id,description,total_amount,expense_date)
                VALUES (900,900,2,2,2,'Dinner',1500,'2026-11-01')");
    exec_($db, 'INSERT INTO expense_splits (expense_id,user_id,share_amount) VALUES (900,2,500),(900,3,500),(900,4,500)');
    $before = [val($db, 'SELECT fn_member_net_balance(900,3)'), val($db, 'SELECT fn_member_net_balance(900,2)')];
    exec_($db, "INSERT INTO settlements (group_id,payer_id,payee_id,amount,settlement_date,status,created_by,request_token)
                VALUES (900,3,2,300,'2026-11-02','confirmed',2,'spec-test-1')");
    $pair = val($db, 'SELECT outstanding FROM v_pairwise_debts WHERE group_id=900 AND user_a=2 AND user_b=3');
    return [$before, val($db, 'SELECT fn_member_net_balance(900,3)'), val($db, 'SELECT fn_member_net_balance(900,2)'), $pair];
});
record('F11', 'Spec example: B owes 500 -> pays 300 -> owes 200; A +1000 -> +700; pair outstanding 200',
    $spec[0] === ['-500.00', '1000.00'] && $spec[1] === '-200.00' && $spec[2] === '700.00' && $spec[3] === '-200.00');

// ---------------------------------------------------------------- constraints
expectError($db, 'C1', 'Duplicate email rejected', fn($d) => exec_($d,
    "INSERT INTO users (username,email,password_hash,full_name) VALUES ('aarav2','aarav@splitwiseplus.test','x','Dup')"), 'Duplicate entry');
expectError($db, 'C2', 'Zero/negative expense amount rejected', fn($d) => exec_($d,
    "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date) VALUES (1,2,2,2,'Bad',-10,'2026-10-01')"), 'chk_expenses_amount');
expectError($db, 'C3', 'Settlement to self rejected', fn($d) => exec_($d,
    "INSERT INTO settlements (group_id,payer_id,payee_id,amount,settlement_date,created_by,request_token) VALUES (1,2,2,10,'2026-10-01',2,'t-self')"), 'chk_settlements_parties');
expectError($db, 'C4', 'Budget with both user and group owner rejected', fn($d) => exec_($d,
    "INSERT INTO budgets (user_id,group_id,name,limit_amount,period_type,start_date,end_date,created_by) VALUES (2,1,'x',100,'monthly','2026-11-01','2026-11-30',2)"), 'chk_budgets_owner');
expectError($db, 'C5', 'Budget end before start rejected', fn($d) => exec_($d,
    "INSERT INTO budgets (user_id,name,limit_amount,period_type,start_date,end_date,created_by) VALUES (2,'x',100,'monthly','2026-11-30','2026-11-01',2)"), 'chk_budgets_period');
expectError($db, 'C6', 'Second pending invitation for same user/group rejected', fn($d) => exec_($d,
    "INSERT INTO group_invitations (group_id,invited_user_id,invited_by) VALUES (3,9,4)"), 'Duplicate entry');
record('C7', 'New pending invite allowed after previous one was answered', inTx($db, function (PDO $d) {
    exec_($d, "INSERT INTO group_invitations (group_id,invited_user_id,invited_by) VALUES (4,8,5)"); return true; }));
expectError($db, 'C8', 'FK: unknown category rejected', fn($d) => exec_($d,
    "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date) VALUES (1,2,2,999,'x',10,'2026-10-01')"), 'foreign key constraint fails');
expectError($db, 'C9', 'FK RESTRICT: user with history cannot be deleted', fn($d) => exec_($d,
    'DELETE FROM users WHERE user_id = 2'), 'foreign key constraint fails');
expectError($db, 'C10', 'Percentage over 100 rejected', fn($d) => exec_($d,
    'UPDATE expense_splits SET share_percentage = 120 WHERE share_percentage IS NOT NULL LIMIT 1'), 'chk_expense_splits_pct');
expectError($db, 'C11', 'Receipt with disallowed MIME type rejected', fn($d) => exec_($d,
    "INSERT INTO receipts (expense_id,file_path,original_name,mime_type,file_size,uploaded_by) VALUES (1,'a.php','a.php','application/x-php',10,2)"), 'chk_receipts_mime');
expectError($db, 'C12', 'Upper-case tag rejected', fn($d) => exec_($d,
    "INSERT INTO tags (name) VALUES ('Goa')"), 'chk_tags_lower');
expectError($db, 'C13', 'Active member with left_at set rejected', fn($d) => exec_($d,
    "UPDATE group_members SET left_at = NOW() WHERE group_id=1 AND user_id=3"), 'chk_group_members_left');
// MySQL ROUNDS extra decimals into DECIMAL(12,2) with only a note (even in strict mode),
// so the application must reject inputs with > 2 decimals before they reach SQL.
record('C14', 'DECIMAL(12,2) silently rounds 10.005 -> 10.01 (so PHP must validate decimals)', inTx($db, function (PDO $d) {
    exec_($d, "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date)
               VALUES (1,2,2,2,'x','10.005','2026-10-01')");
    return val($d, 'SELECT total_amount FROM expenses WHERE expense_id = LAST_INSERT_ID()') === '10.01'; }));
expectError($db, 'C15', 'Amount above DECIMAL(12,2) range rejected in strict mode', fn($d) => exec_($d,
    "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date) VALUES (1,2,2,2,'x','99999999999.00','2026-10-01')"), 'Out of range');

// ---------------------------------------------------------------- triggers
expectError($db, 'T1', 'BEFORE INSERT expenses: future date rejected', fn($d) => exec_($d,
    "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date) VALUES (1,2,2,2,'x',10,CURDATE() + INTERVAL 1 DAY)"), 'future');
expectError($db, 'T2', 'BEFORE INSERT expenses: non-member payer rejected', fn($d) => exec_($d,
    "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date) VALUES (1,6,6,2,'x',10,'2026-10-01')"), 'active member');
expectError($db, 'T3', 'BEFORE INSERT expenses: archived group rejected', function ($d) {
    exec_($d, 'UPDATE expense_groups SET is_archived = 1 WHERE group_id = 4');
    exec_($d, "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date) VALUES (4,5,5,2,'x',10,'2026-10-25')");
}, 'archived');
expectError($db, 'T4', 'BEFORE INSERT splits: non-member participant rejected', fn($d) => exec_($d,
    'INSERT INTO expense_splits (expense_id,user_id,share_amount) VALUES (1,6,1)'), 'active member');
expectError($db, 'T5', 'BEFORE INSERT splits: personal expense split to another user rejected', fn($d) => exec_($d,
    "INSERT INTO expense_splits (expense_id,user_id,share_amount)
     SELECT expense_id, 3, 1 FROM expenses WHERE description='Running shoes'"), 'personal expense');
expectError($db, 'T6', 'BEFORE INSERT splits: share above total rejected', fn($d) => exec_($d,
    'INSERT INTO expense_splits (expense_id,user_id,share_amount) SELECT expense_id, 8, 99999 FROM expenses WHERE description=\'Movie night at PVR\''), 'exceed');
expectError($db, 'T7', 'BEFORE UPDATE settlements: confirmed is final', fn($d) => exec_($d,
    "UPDATE settlements SET status='cancelled' WHERE settlement_id = 1"), 'final');
expectError($db, 'T8', 'BEFORE UPDATE settlements: amount of pending cannot change', fn($d) => exec_($d,
    'UPDATE settlements SET amount = 1 WHERE settlement_id = 3'), 'cannot be edited');
expectError($db, 'T9', 'BEFORE DELETE settlements: confirmed cannot be deleted', fn($d) => exec_($d,
    'DELETE FROM settlements WHERE settlement_id = 1'), 'cannot be deleted');
record('T10', 'BEFORE DELETE settlements: pending one may be deleted', inTx($db, function (PDO $d) {
    exec_($d, 'DELETE FROM settlements WHERE settlement_id = 3');
    return (int) val($d, 'SELECT COUNT(*) FROM settlements WHERE settlement_id = 3') === 0; }));
record('T11', 'AFTER INSERT settlements: payee notified of pending request',
    (int) val($db, "SELECT COUNT(*) FROM notifications WHERE user_id=2 AND type='settlement_request' AND entity_id=3") === 1);
record('T12', 'AFTER UPDATE settlements: payer told of rejection; responded_at set',
    (int) val($db, "SELECT COUNT(*) FROM notifications WHERE user_id=4 AND type='settlement_rejected' AND entity_id=4") === 1
 && val($db, 'SELECT responded_at IS NOT NULL FROM settlements WHERE settlement_id=4') === 1);
record('T13', 'AFTER UPDATE expenses: soft delete logged as SOFT_DELETE',
    (int) val($db, "SELECT COUNT(*) FROM audit_logs a JOIN expenses e ON e.expense_id=a.record_id
                    WHERE a.table_name='expenses' AND a.action='SOFT_DELETE' AND e.description LIKE 'Souvenirs%'") === 1);
record('T14', 'AFTER DELETE splits: 3 old splits of the edited expense archived',
    (int) val($db, "SELECT COUNT(*) FROM audit_logs a WHERE a.table_name='expense_splits' AND a.action='DELETE'
                    AND JSON_EXTRACT(a.old_data,'$.share_amount') = 1333.34 OR
                    (a.table_name='expense_splits' AND a.action='DELETE' AND JSON_EXTRACT(a.old_data,'$.share_amount') = 1333.33)") === 3);
record('T15', 'AFTER INSERT splits: participants (not payer/creator) notified of new expense',
    (int) val($db, "SELECT COUNT(*) FROM notifications n JOIN expenses e ON e.expense_id = n.entity_id
                    WHERE n.type='expense_added' AND e.description='Flights Mumbai–Goa (return)'") === 3);
record('T16', 'AFTER DELETE expenses: hard delete audited (admin cleanup)', inTx($db, function (PDO $d) {
    $id = (int) val($d, "SELECT expense_id FROM expenses WHERE description='Souvenirs (duplicate entry)'");
    exec_($d, 'DELETE FROM expenses WHERE expense_id = ?', [$id]);
    return (int) val($d, "SELECT COUNT(*) FROM audit_logs WHERE table_name='expenses' AND action='DELETE' AND record_id=?", [$id]) === 1; }));
record('T17', 'audit_logs records the acting user from @app_user_id',
    (int) val($db, "SELECT COUNT(*) FROM audit_logs WHERE table_name='expenses' AND action='SOFT_DELETE' AND changed_by=4") === 1);

// ---------------------------------------------------------------- views
expectError($db, 'V1', 'WITH CHECK OPTION blocks soft delete through v_active_expenses', fn($d) => exec_($d,
    'UPDATE v_active_expenses SET is_deleted = 1 WHERE expense_id = 1'), 'CHECK OPTION failed');
expectError($db, 'V2', 'Aggregate view is not updatable', fn($d) => exec_($d,
    'UPDATE v_group_expense_summary SET total_spent = 0 WHERE group_id = 1'), 'not updatable');
record('V3', 'Simple view is updatable and writes through to base table', inTx($db, function (PDO $d) {
    exec_($d, "UPDATE v_active_expenses SET notes='via view' WHERE expense_id = 1");
    return val($d, 'SELECT notes FROM expenses WHERE expense_id = 1') === 'via view'; }));
$updatable = array_column(rows($db, "SELECT TABLE_NAME FROM information_schema.VIEWS
    WHERE TABLE_SCHEMA='splitwise_plus' AND IS_UPDATABLE='YES' ORDER BY TABLE_NAME"), 'TABLE_NAME');
record('V4', 'information_schema: updatable = 2 simple views + inner-join view + merged-derived-table view',
    $updatable === ['v_active_expenses', 'v_active_group_members', 'v_budget_utilization', 'v_settlement_details'],
    implode(',', $updatable));
expectError($db, 'V5', 'Computed column of an "updatable" view cannot be updated', fn($d) => exec_($d,
    'UPDATE v_budget_utilization SET spent = 0 WHERE budget_id = 1'), 'not updatable');

// ---------------------------------------------------------------- subquery pitfall
record('Q1', 'NOT IN with NULL in subquery returns 0 rows; NOT EXISTS returns Office Team',
    (int) val($db, 'SELECT COUNT(*) FROM expense_groups WHERE group_id NOT IN (SELECT group_id FROM expenses)') === 0
 && val($db, 'SELECT g.name FROM expense_groups g WHERE NOT EXISTS (SELECT 1 FROM expenses e WHERE e.group_id=g.group_id)') === 'Office Team');

// ---------------------------------------------------------------- procedures (read/reject first)
$call = function (PDO $d, array $a): ?int {
    $st = $d->prepare('CALL sp_record_settlement(?,?,?,?,?,?,?,?,?,@sid)'); $st->execute($a); $st->closeCursor();
    $v = val($d, 'SELECT @sid'); return $v === null ? null : (int) $v;
};
$tryCall = function (string $id, string $name, array $args, string $needle) use ($db, $call) {
    try { $call($db, $args); record($id, $name, false, 'no error raised'); }
    catch (PDOException $e) { record($id, $name, stripos($e->getMessage(), $needle) !== false, $e->getMessage()); }
};
$tryCall('P1', 'sp_record_settlement: over-settlement rejected', [1, 4, 2, 99999, '2026-11-08', 'cash', null, 2, 'p1'], 'exceeds');
$tryCall('P2', 'sp_record_settlement: third party cannot record', [1, 4, 2, 10, '2026-11-08', 'cash', null, 3, 'p2'], 'Only the payer or the payee');
$tryCall('P3', 'sp_record_settlement: pending amounts count toward limit (Sneha has 2000 pending)',
    [1, 5, 2, 5500, '2026-11-08', 'upi', null, 5, 'p3'], 'payer owes');
$tryCall('P4', 'sp_record_settlement: non-member rejected', [1, 6, 2, 10, '2026-11-08', 'cash', null, 2, 'p4'], 'members');
$before = (int) val($db, 'SELECT COUNT(*) FROM settlements');
$replayId = $call($db, [1, 4, 2, 5000, '2026-10-10', 'upi', null, 2, 'seed-settlement-0001']);
record('P5', 'sp_record_settlement: same request_token returns existing id, no new row',
    $replayId === 1 && (int) val($db, 'SELECT COUNT(*) FROM settlements') === $before);
try { $db->exec("CALL sp_respond_settlement(3, 5, 'confirm')"); record('P6', 'Only payee can confirm', false); }
catch (PDOException $e) { record('P6', 'sp_respond_settlement: only payee can confirm', str_contains($e->getMessage(), 'Only the payee')); }
$expBefore = (int) val($db, 'SELECT COUNT(*) FROM expenses');
try { $db->exec("CALL sp_add_equal_expense(1,2,2,2,'Atomicity check',900,'2026-10-05','2,3,6',@x)");
      record('P7', 'sp_add_equal_expense: non-member participant -> whole expense rolled back', false); }
catch (PDOException $e) {
    record('P7', 'sp_add_equal_expense: non-member participant -> whole expense rolled back',
        (int) val($db, 'SELECT COUNT(*) FROM expenses') === $expBefore, $e->getMessage()); }
try { $db->exec("CALL sp_add_equal_expense(1,2,2,2,'Bad list',900,'2026-10-05','2;3',@x)"); record('P8', 'bad CSV', false); }
catch (PDOException $e) { record('P8', 'sp_add_equal_expense: malformed participant list rejected', str_contains($e->getMessage(), 'comma-separated')); }

// ---------------------------------------------------------------- concurrency
$db2 = connect();
$db->beginTransaction();
rows($db, 'SELECT membership_id FROM group_members WHERE group_id = 1 FOR UPDATE');
$db2->exec('SET SESSION innodb_lock_wait_timeout = 1');
try { $call($db2, [1, 5, 2, 100, '2026-11-08', 'upi', null, 5, 'conc-1']); record('X1', 'Concurrent settlement waits for group lock', false); }
catch (PDOException $e) { record('X1', 'Concurrent settlement in same group blocks on FOR UPDATE lock', str_contains($e->getMessage(), 'Lock wait timeout')); }
$db->rollBack();
record('X2', 'Blocked call left no partial row', (int) val($db, "SELECT COUNT(*) FROM settlements WHERE request_token='conc-1'") === 0);

// ---------------------------------------------------------------- least-privilege app user
// Runs only when APP_DB_PASS is set (run.sh creates the user with database/security/app_user.sql).
$appPass = getenv('APP_DB_PASS');
if ($appPass !== false && $appPass !== '') {
    try {
        $app = connect('splitwise_app', $appPass, '127.0.0.1');
        $app->exec('SET @app_user_id = 2');
        record('A1', 'App user can read tables and views',
            val($app, 'SELECT net_balance FROM v_group_member_balances WHERE group_id=1 AND user_id=2') === '9991.67');
        $st = $app->query('CALL sp_get_group_balances(1)'); $n = count($st->fetchAll()); $st->closeCursor();
        record('A2', 'App user can EXECUTE procedures', $n === 4);
        record('A3', 'App user can add expense + splits; DEFINER triggers still write audit_logs', inTx($app, function (PDO $d) {
            exec_($d, "INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date)
                       VALUES (1,2,2,2,'App user test',200,'2026-10-05')");
            $id = (int) $d->lastInsertId();
            exec_($d, 'INSERT INTO expense_splits (expense_id,user_id,share_amount) VALUES (?,2,100),(?,3,100)', [$id, $id]);
            return (int) val($d, "SELECT COUNT(*) FROM audit_logs WHERE table_name='expenses' AND record_id=? AND changed_by=2", [$id]) === 1; }));
        expectError($app, 'A4', 'App user cannot write settlements directly (must use procedures)', fn($d) => exec_($d,
            "INSERT INTO settlements (group_id,payer_id,payee_id,amount,settlement_date,created_by,request_token)
             VALUES (1,4,2,10,'2026-11-01',2,'priv-test')"), 'command denied');
        expectError($app, 'A5', 'App user cannot delete or edit audit_logs', fn($d) => exec_($d,
            'DELETE FROM audit_logs WHERE log_id = 1'), 'command denied');
        try { $app->exec('CREATE TABLE hack (id INT)'); record('A6', 'App user cannot run DDL', false); }
        catch (PDOException $e) { record('A6', 'App user cannot run DDL (CREATE TABLE)', str_contains($e->getMessage(), 'command denied')); }
        try { $call($app, [1, 4, 2, 99999, '2026-11-08', 'cash', null, 2, 'priv-over']); record('A7', 'proc business rule', false); }
        catch (PDOException $e) { record('A7', 'App user reaches settlement rules via sp_record_settlement (blocked by rule, not by permission)',
            str_contains($e->getMessage(), 'exceeds')); }
        record('A8', 'App user can store/refresh/delete sessions (app_sessions) and counters (app_kv)', inTx($app, function (PDO $d) {
            exec_($d, "INSERT INTO app_sessions (session_id,payload,last_activity) VALUES ('t1','x',1) AS n
                       ON DUPLICATE KEY UPDATE payload=n.payload");
            exec_($d, "UPDATE app_sessions SET last_activity=2 WHERE session_id='t1'");
            exec_($d, "INSERT INTO app_kv (kv_key,kv_value,expires_at) VALUES ('t:k','1',NULL)");
            exec_($d, "DELETE FROM app_sessions WHERE session_id='t1'");
            exec_($d, "DELETE FROM app_kv WHERE kv_key='t:k'");
            return true; }));
        record('A9', 'App user can store receipt bytes (receipts.content LONGBLOB)', inTx($app, function (PDO $d) {
            exec_($d, "INSERT INTO receipts (expense_id,file_path,original_name,mime_type,file_size,content,uploaded_by)
                       VALUES (1,'t.png','t.png','image/png',3,'abc',2)");
            return val($d, 'SELECT content FROM receipts WHERE receipt_id=?', [(int) $d->lastInsertId()]) === 'abc'; }));
    } catch (PDOException $e) {
        record('A0', 'Connect as splitwise_app', false, $e->getMessage());
    }
} else {
    echo "(skipping A-tests: APP_DB_PASS not set)\n";
}

// ---------------------------------------------------------------- committing flows (last)
$db->exec("CALL sp_respond_settlement(3, 2, 'confirm')");
record('W1', 'Aarav confirms Sneha\'s pending 2000: Sneha -5133.33, Aarav +7991.67, group sum 0',
    val($db, 'SELECT fn_member_net_balance(1,5)') === '-5133.33'
 && val($db, 'SELECT fn_member_net_balance(1,2)') === '7991.67'
 && val($db, 'SELECT SUM(net_balance) FROM v_group_member_balances WHERE group_id=1') === '0.00');
$newId = $call($db, [3, 8, 6, 600, '2026-11-08', 'cash', 'bowling + movie', 6, 'w2']);
record('W2', 'Payee-recorded settlement is confirmed immediately; Kabir settled in College Friends',
    val($db, 'SELECT status FROM settlements WHERE settlement_id=?', [$newId]) === 'confirmed'
 && val($db, 'SELECT fn_member_net_balance(3,6)') === '0.00'
 && val($db, 'SELECT fn_member_net_balance(3,8)') === '-550.00');

// ---------------------------------------------------------------- summary
$pass = count(array_filter($results, fn($r) => $r[2]));
$total = count($results);
echo "\n$pass / $total passed\n";
exit($pass === $total ? 0 : 1);
