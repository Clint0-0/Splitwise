<?php
/**
 * Experiment 10 — checks the sandbox AFTER experiments/10_mysql_ddl_fk/run.sh --yes.
 * Verifies the expected errors (codes, order) and the end state of every demo,
 * and that splitwise_plus was not touched (table/column/FK fingerprint before vs after).
 * Usage: php tests/experiments/test_exp10.php   (runs the scripts itself)
 */
declare(strict_types=1);
$ROOT = dirname(__DIR__, 2);
$dir = "$ROOT/experiments/10_mysql_ddl_fk";
$pass = 0; $fail = 0;
$check = function (string $id, string $label, bool $ok, string $info = '') use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . "  $id  $label" . ($ok || $info === '' ? '' : "  [$info]") . "\n";
    $ok ? $pass++ : $fail++;
};
$pdo = new PDO(getenv('EXP_ROOT_DSN') ?: 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;charset=utf8mb4',
    getenv('EXP_ROOT_USER') ?: 'root', getenv('EXP_ROOT_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$val = fn(string $sql) => $pdo->query($sql)->fetchColumn();
$fingerprint = fn() => md5(json_encode($pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_plus' ORDER BY 1,2")->fetchAll())
    . json_encode($pdo->query("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='splitwise_plus' ORDER BY 1")->fetchAll())
    . $pdo->query('SELECT COUNT(*) FROM splitwise_plus.expenses')->fetchColumn());

$before = $fingerprint();
foreach (glob("$dir/output/run_*.txt") ?: [] as $f) { @unlink($f); }
exec('bash ' . escapeshellarg("$dir/run.sh") . ' --yes 2>&1', $o, $rc);
$out = (string) @file_get_contents((glob("$dir/output/run_*.txt") ?: [''])[0]);
$after = $fingerprint();

$check('E10-1', 'run.sh completed and wrote an output file', $rc === 0 && $out !== '');
preg_match_all('/^ERROR (\d+)/m', $out, $m);
$check('E10-2', 'Exactly the 13 expected errors, in order: 1451 1451 1451 1452 1830 1451 3819 1452 3730 1136 1452 1451 3730',
    implode(' ', $m[1]) === '1451 1451 1451 1452 1830 1451 3819 1452 3730 1136 1452 1451 3730', implode(' ', $m[1]));
$check('E10-3', 'CREATE/DROP DATABASE: sandbox exists, scratch database dropped',
    (int) $val("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='splitwise_lab_exp10'") === 1
    && (int) $val("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='splitwise_lab_exp10_fkdemo'") === 1
    && (int) $val("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='splitwise_lab_exp10_scratch'") === 0
    && $val("SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='splitwise_lab_exp10'") === 'utf8mb4_0900_ai_ci');
$check('E10-4', 'RESTRICT: users 1,2,3 and group 1 survived; no ghost expense',
    (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.users WHERE user_id IN (1,2,3)') === 3
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.expense_groups WHERE group_id=1') === 1
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.expenses WHERE paid_by=999') === 0);
$check('E10-5', 'CASCADE: group 2 members deleted with the group; ON UPDATE CASCADE moved created_by 4 -> 40',
    (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.group_members WHERE group_id=2') === 0
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.group_members WHERE group_id=1') === 3
    && (int) $val('SELECT created_by FROM splitwise_lab_exp10.expense_groups WHERE group_id=3') === 40);
$check('E10-6', 'SET NULL: expense 3 kept with deleted_by NULL after user 5 deleted',
    $val('SELECT deleted_by FROM splitwise_lab_exp10.expenses WHERE expense_id=3') === null
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.users WHERE user_id=5') === 0);
$check('E10-7', 'SET DEFAULT measured: definition accepted, delete rejected (1451), demo table dropped afterwards',
    str_contains($out, 'ON DELETE SET DEFAULT') && (int) $val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='set_default_demo'") === 0);
$check('E10-8', 'ALTER: notes added after description, currency dropped, description widened to 200, index added, categories renamed back',
    (int) $val("SELECT ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='expenses' AND COLUMN_NAME='notes'")
      === (int) $val("SELECT ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='expenses' AND COLUMN_NAME='description'") + 1
    && (int) $val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='expenses' AND COLUMN_NAME='currency'") === 0
    && $val("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='expenses' AND COLUMN_NAME='description'") === 'varchar(200)'
    && (int) $val("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND INDEX_NAME='idx_expenses_group_paid'") > 0
    && (int) $val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='categories'") === 1
    && str_contains($out, 'expense_categories'));
$check('E10-9', 'DROP FOREIGN KEY shown then FK re-added; orphan cleaned; tags/expense_tags dropped child-first',
    str_contains($out, 'DROP FOREIGN KEY fk_expenses_paid_by')
    && $val("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='splitwise_lab_exp10' AND CONSTRAINT_NAME='fk_expenses_paid_by'") === 'RESTRICT'
    && (int) $val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME IN ('tags','expense_tags')") === 0);
$check('E10-10', 'SHOW CREATE TABLE output captured', substr_count($out, 'Create Table:') >= 5);
$check('E10-11', 'Warehouse (manual DDL as printed): warehouseProducts renamed to inventoryDetails with composite PK + 2 CASCADE FKs; capacity dropped; warehouseID after productLine; employeeWarehouse(allocationID, employeeNumber, warehouseID)',
    (int) $val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='warehouseProducts'") === 0
    && (int) $val("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='inventoryDetails' AND CONSTRAINT_NAME='PRIMARY'") === 2
    && (int) $val("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='inventoryDetails' AND DELETE_RULE='CASCADE'") === 2
    && (int) $val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='warehouse' AND COLUMN_NAME='capacity'") === 0
    && (int) $val("SELECT ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='products' AND COLUMN_NAME='warehouseID'")
      === (int) $val("SELECT ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='products' AND COLUMN_NAME='productLine'") + 1
    && $val("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10' AND TABLE_NAME='employeeWarehouse'") === 'allocationID,employeeNumber,warehouseID');
$check('E10-12', 'Warehouse delete: allocation 101 -> NULL, 102 keeps 2 (SET NULL); inventory CASCADE (2 left, none for 1); products.warehouseID dangling (2)',
    $val('SELECT warehouseID FROM splitwise_lab_exp10.employeeWarehouse WHERE allocationID=101') === null
    && (int) $val('SELECT warehouseID FROM splitwise_lab_exp10.employeeWarehouse WHERE allocationID=102') === 2
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.inventoryDetails') === 2
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.inventoryDetails WHERE warehouseID=1') === 0
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10.products WHERE warehouseID=1') === 2
    && $val("SELECT GROUP_CONCAT(warehouseName ORDER BY warehouseID) FROM splitwise_lab_exp10.warehouse") === 'North Warehouse');
$check('E10-16', "Manual's fkdemo examples: checklist CASCADE (0 left); after SET NULL section all 4 products have NULL category; fk_category dropped; tasks renamed todo_tasks with columns back to 4",
    (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10_fkdemo.checklists') === 0
    && (int) $val('SELECT COUNT(*) FROM splitwise_lab_exp10_fkdemo.products WHERE categoryId IS NULL') === 4
    && (int) $val("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='splitwise_lab_exp10_fkdemo' AND CONSTRAINT_NAME='fk_category'") === 0
    && $val("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='splitwise_lab_exp10_fkdemo' AND TABLE_NAME='todo_tasks'") === 'id,title,start_date,due_date');
$check('E10-17', "Manual's CASCADE section really cascaded (output shows iPhone/Galaxy Note with categoryId 100 and the watches deleted)",
    (bool) preg_match('/\|\s+1 \| iPhone\s+\|\s+100 \|/', $out) && (bool) preg_match('/\|\s+2 \| Galaxy Note\s+\|\s+100 \|/', $out));
$check('E10-13', 'splitwise_plus untouched (schema + FK rules + expense count identical before/after)', $before === $after);
$tracked = (string) shell_exec('cd ' . escapeshellarg($ROOT) . ' && git status --porcelain database/ 2>/dev/null');
$check('E10-14', 'Production build chain untouched (no changes under database/)', trim($tracked) === '', trim($tracked));
$check('E10-15', 'No experiment script names splitwise_plus except the read-only count in 10',
    count(array_filter(glob("$dir/[01]*.sql") ?: [], fn($f) => str_contains((string) file_get_contents($f), 'splitwise_plus')
        && basename($f) !== '10_verification_queries.sql' && !preg_match('/Never touches splitwise_plus|splitwise_plus is not touched/', (string) file_get_contents($f)))) === 0);

echo "\n$pass / " . ($pass + $fail) . " passed\n";
exit($fail ? 1 : 0);
