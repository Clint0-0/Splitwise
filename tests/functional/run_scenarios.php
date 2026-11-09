<?php
/**
 * Phase 5 money scenarios — whole stories driven through the real app over HTTP,
 * with the expected rupee amounts worked out by hand (see docs/TEST_SCENARIOS.md).
 * Uses brand-new users, so it does not depend on the sample data.
 *
 * Preconditions: as for run_functional_tests.php (fresh import + app user + app running).
 * Usage: APP_URL=http://127.0.0.1:8765 php tests/functional/run_scenarios.php
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$BASE = rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8000', '/');
$db = root_db();
$val = function (string $sql, array $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); };
$today = date('Y-m-d');

/** Register a new user through the form and return their logged-in browser + id. */
$register = function (string $username, string $name) use ($BASE, $val): array {
    $b = new Browser($BASE);
    $b->get('/register');
    $b->post('/register', ['full_name' => $name, 'username' => $username, 'email' => "$username@example.com",
        'password' => 'Scenario123', 'password_confirm' => 'Scenario123']);
    return [$b, (int) $val('SELECT user_id FROM users WHERE username = ?', [$username])];
};
$net = fn(int $g, int $u) => (string) $val('SELECT fn_member_net_balance(?, ?)', [$g, $u]);
$nets = fn(int $g, array $ids) => array_map(fn($u) => $net($g, $u), $ids);
$addExpense = function (Browser $b, int $gid, array $fields) use ($today): void {
    $b->get("/expenses/create?group=$gid");
    $b->post('/expenses', $fields + ['group_id' => $gid, 'category_id' => '2', 'expense_date' => $today]);
};
$settle = function (Browser $b, int $gid, int $payer, int $payee, string $amount): void {
    $b->get("/groups/$gid/settle");
    $b->post('/settlements', ['group_id' => $gid, 'payer_id' => $payer, 'payee_id' => $payee, 'amount' => $amount,
        'settlement_date' => date('Y-m-d'), 'method' => 'upi', 'request_token' => $b->field('request_token')]);
};

// ===================================================================== setup
[$asha, $A] = $register('asha', 'Asha Kulkarni');
[$bala, $B] = $register('bala', 'Bala Murugan');
[$chet, $C] = $register('chetan', 'Chetan Patil');
$asha->get('/groups/create');
$asha->post('/groups', ['name' => 'Spec checks', 'group_type' => 'friends']);
$G = (int) $val("SELECT group_id FROM expense_groups WHERE name = 'Spec checks'");
foreach (['bala' => $bala, 'chetan' => $chet] as $u => $br) {
    $asha->get("/groups/$G");
    $asha->post("/groups/$G/invite", ['invite' => $u]);
    $inv = (int) $val("SELECT invitation_id FROM group_invitations WHERE group_id = ? AND status = 'pending'
                        AND invited_user_id = (SELECT user_id FROM users WHERE username = ?)", [$G, $u]);
    $br->get('/groups');
    $br->post("/invitations/$inv/accept");
}
check('SC-0', 'Three new users, one group, everyone joined', $G > 0
    && (int) $val("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND status = 'active'", [$G]) === 3);

// ===================================================================== A. spec examples
$addExpense($asha, $G, ['description' => 'Dinner', 'total_amount' => '1500', 'paid_by' => $A,
    'split_method' => 'equal', 'participants' => [$A, $B, $C]]);
check('SC-A1', 'Spec: ₹1,500 dinner paid by Asha, equal -> Asha +1000, Bala −500, Chetan −500',
    $nets($G, [$A, $B, $C]) === ['1000.00', '-500.00', '-500.00']);
$bala->get('/balances');
check('SC-A2', 'Bala\'s balances page says "You owe Asha Kulkarni ₹500.00"', $bala->says('You owe Asha Kulkarni') && $bala->says('₹500.00'));

$settle($bala, $G, $B, $A, '300');
$sid = (int) $val('SELECT MAX(settlement_id) FROM settlements WHERE group_id = ?', [$G]);
check('SC-A3', 'Spec: Bala records paying ₹300 -> pending, balance unchanged until Asha confirms',
    $val('SELECT status FROM settlements WHERE settlement_id = ?', [$sid]) === 'pending' && $net($G, $B) === '-500.00');
$asha->get('/dashboard');
check('SC-A4', 'Asha is asked "Did you receive this money?" on her dashboard', $asha->says('Did you receive this money?') && $asha->says('says they paid you ₹300.00'));
$asha->post("/settlements/$sid/confirm", ['back' => '/dashboard']);
$bala->get('/balances');
check('SC-A5', 'Spec: original ₹500, settled ₹300, remaining ₹200 — shown to Bala exactly like that',
    $net($G, $B) === '-200.00' && $bala->says('₹200.00 of ₹500.00') && $bala->says('₹300.00 paid back so far'));

$addExpense($chet, $G, ['description' => 'Movie tickets', 'total_amount' => '1200', 'paid_by' => $C,
    'split_method' => 'percentage', 'participants' => [$A, $B, $C], 'share' => [$A => '50', $B => '30', $C => '20']]);
$e2 = (int) $val("SELECT expense_id FROM expenses WHERE description = 'Movie tickets'");
check('SC-A6', 'Spec: ₹1,200 at 50/30/20 -> ₹600 / ₹360 / ₹240',
    $val('SELECT GROUP_CONCAT(share_amount ORDER BY user_id) FROM expense_splits WHERE expense_id = ?', [$e2]) === '600.00,360.00,240.00');
check('SC-A7', 'Balances now: Asha +100, Bala −560, Chetan +460 (sum 0)', $nets($G, [$A, $B, $C]) === ['100.00', '-560.00', '460.00']);

// budget: ₹10,000, ₹7,500 spent -> ₹2,500 left, 75 %
$asha->get('/budgets/create');
$asha->post('/budgets', ['scope' => 'personal', 'name' => 'This month', 'limit_amount' => '10000', 'category_id' => '',
    'period_type' => 'monthly', 'start_date' => date('Y-m-01'), 'alert_threshold' => '80']);
$asha->get('/expenses/create');
$asha->post('/expenses', ['group_id' => '', 'description' => 'Laptop repair', 'total_amount' => '6400', 'category_id' => '14', 'expense_date' => $today]);
$asha->get('/budgets');
$bid = (int) $val("SELECT budget_id FROM budgets WHERE name = 'This month' AND user_id = ?", [$A]);
check('SC-A8', 'Spec: budget ₹10,000 with ₹7,500 spent shows ₹2,500.00 left (75% used), no warning yet',
    $asha->says('₹7,500.00 / ₹10,000.00') && $asha->says('₹2,500.00 left (75% used)')
    && (int) $val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'budget_warning' AND entity_id = ?", [$A, $bid]) === 0);
$asha->get('/expenses/create');
$asha->post('/expenses', ['group_id' => '', 'description' => 'Phone bill', 'total_amount' => '600', 'category_id' => '9', 'expense_date' => $today]);
check('SC-A9', 'Crossing 80% (₹8,100) sends one budget warning',
    (int) $val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'budget_warning' AND entity_id = ?", [$A, $bid]) === 1);

// ===================================================================== B. settle everything, then change history
$asha->get("/groups/$G");
check('SC-B1', 'Suggestions: Bala pays Chetan ₹460.00 and Bala pays Asha ₹100.00 (2 payments)',
    substr_count($asha->body, 'data-suggestion') === 2 && $asha->says('Bala Murugan pays Chetan Patil ₹460.00')
    && $asha->says('Bala Murugan pays you ₹100.00'));
$settle($chet, $G, $B, $C, '460');   // receiver records -> confirmed at once
$settle($asha, $G, $B, $A, '100');
$asha->get("/groups/$G");
check('SC-B2', 'After the two payments everyone is at ₹0 and the page says so',
    $nets($G, [$A, $B, $C]) === ['0.00', '0.00', '0.00'] && $asha->says('Everyone is settled up') && $asha->says('In this group you are settled up'));

$e1 = (int) $val("SELECT expense_id FROM expenses WHERE description = 'Dinner' AND group_id = ?", [$G]);
$asha->get("/expenses/$e1/edit");
$asha->post("/expenses/$e1", ['description' => 'Dinner (with dessert)', 'total_amount' => '1800', 'category_id' => '2',
    'expense_date' => $today, 'paid_by' => $A, 'split_method' => 'equal', 'participants' => [$A, $B, $C], 'version' => $asha->field('version')]);
check('SC-B3', 'Editing the dinner to ₹1,800 after settling re-opens exactly the difference: Asha +200, Bala −100, Chetan −100',
    $nets($G, [$A, $B, $C]) === ['200.00', '-100.00', '-100.00']);
check('SC-B4', 'Bala and Chetan are told the expense changed (not Asha, who changed it)',
    (int) $val("SELECT COUNT(*) FROM notifications WHERE type = 'expense_updated' AND entity_id = ? AND user_id IN (?, ?)", [$e1, $B, $C]) === 2
    && (int) $val("SELECT COUNT(*) FROM notifications WHERE type = 'expense_updated' AND entity_id = ? AND user_id = ?", [$e1, $A]) === 0);

$chet->get("/expenses/$e2");
$chet->post("/expenses/$e2/delete");
check('SC-B5', 'Deleting the ₹1,200 movie after settling flips directions: Asha +800, Bala +260, Chetan −1060 (payments are kept)',
    $nets($G, [$A, $B, $C]) === ['800.00', '260.00', '-1060.00']
    && (int) $val("SELECT COUNT(*) FROM settlements WHERE group_id = ? AND status = 'confirmed'", [$G]) === 3);
$chet->get("/groups/$G");
check('SC-B6', 'Chetan now sees he owes ₹1,060.00 and is told who to pay',
    $chet->says('In this group you owe ₹1,060.00') && $chet->says('You pay Asha Kulkarni ₹800.00') && $chet->says('You pay Bala Murugan ₹260.00'));
$asha->get("/expenses/$e2");
$asha->post("/expenses/$e2/restore");
check('SC-B7', 'Group admin restores it: back to Asha +200, Bala −100, Chetan −100',
    $nets($G, [$A, $B, $C]) === ['200.00', '-100.00', '-100.00']);
check('SC-B8', 'History of the dinner shows Added, Edited (₹1,500 -> ₹1,800) with who did it',
    (int) $val("SELECT COUNT(*) FROM audit_logs WHERE table_name = 'expenses' AND record_id = ? AND action IN ('INSERT','UPDATE')", [$e1]) === 2
    && $val("SELECT CONCAT(old_data->>'$.total_amount', '>', new_data->>'$.total_amount') FROM audit_logs
             WHERE table_name = 'expenses' AND record_id = ? AND action = 'UPDATE'", [$e1]) === '1500.00>1800.00');

// ===================================================================== C. rounding
$addExpense($chet, $G, ['description' => 'Auto rickshaw', 'total_amount' => '1000', 'paid_by' => $C,
    'split_method' => 'equal', 'participants' => [$A, $B, $C]]);
$e3 = (int) $val("SELECT expense_id FROM expenses WHERE description = 'Auto rickshaw'");
$chet->get("/expenses/$e3");
check('SC-C1', '₹1,000 among 3: payer Chetan carries the extra paisa (333.34), others 333.33, total exact',
    $val('SELECT GROUP_CONCAT(share_amount ORDER BY user_id) FROM expense_splits WHERE expense_id = ?', [$e3]) === '333.33,333.33,333.34'
    && $val('SELECT fn_expense_split_gap(?)', [$e3]) === '0.00' && $chet->says('₹333.34'));
check('SC-C2', 'Group still sums to exactly ₹0.00',
    $val('SELECT SUM(net_balance) FROM v_group_member_balances WHERE group_id = ?', [$G]) === '0.00');

// ===================================================================== D. debt cycle (sample data, Weekend Trek)
$kabir = new Browser($BASE);
$kabir->get('/login');
$kabir->post('/login', ['login' => 'kabir', 'password' => 'Password@123']);
$kabir->get('/groups/5');
check('SC-D1', 'Debt cycle (Kabir→Sneha→Rohan→Kabir, ₹500 each): 3 debts listed, 0 suggested payments',
    $kabir->says('Everyone is settled up') && substr_count($kabir->body, 'data-suggestion') === 0
    && substr_count($kabir->text(), '₹500.00') >= 3);

// ===================================================================== E. guard rails in the same story
$bala->get("/groups/$G/settle");
$bala->post('/settlements', ['group_id' => $G, 'payer_id' => $B, 'payee_id' => $A, 'amount' => '5000',
    'settlement_date' => $today, 'method' => 'cash', 'request_token' => $bala->field('request_token')])->follow();
check('SC-E1', 'Bala cannot "pay" more than he owes (₹5,000 against ₹100 + ₹333.33)', $bala->says('Amount exceeds what the payer owes'));
$bala->get("/groups/$G");
$bala->post("/groups/$G/leave")->follow();
check('SC-E2', 'Bala cannot leave while he owes money', $bala->says('must be settled up first'));

finish();
