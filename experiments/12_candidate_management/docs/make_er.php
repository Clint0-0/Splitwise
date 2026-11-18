<?php
/**
 * Generates docs/er_diagram.dot from the LIVE schema (information_schema), so the ER
 * diagram always matches the implementation. Then: dot -Tsvg / -Tpng.
 *   php docs/make_er.php && dot -Tsvg docs/er_diagram.dot -o docs/er_diagram.svg && dot -Tpng -Gdpi=150 docs/er_diagram.dot -o docs/er_diagram.png
 * Login: MYSQL_DSN / MYSQL_USER / MYSQL_PASS (default: root over the local socket).
 */
declare(strict_types=1);
$db = 'splitwise_candidate_lab';
$pdo = new PDO(getenv('MYSQL_DSN') ?: 'mysql:unix_socket=/var/run/mysqld/mysqld.sock', getenv('MYSQL_USER') ?: 'root', getenv('MYSQL_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$q = function (string $sql) use ($pdo, $db) { $s = $pdo->prepare($sql); $s->execute([$db]); return $s->fetchAll(); };

$cols = $q('SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_KEY k FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION');
$fks = $q('SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE d
             FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL');
$uk = [];
foreach ($q("SELECT s.TABLE_NAME t, s.COLUMN_NAME c FROM information_schema.STATISTICS s
             WHERE s.TABLE_SCHEMA = ? AND s.NON_UNIQUE = 0 AND s.INDEX_NAME <> 'PRIMARY'") as $r) { $uk[$r['t']][$r['c']] = true; }
$fkc = [];
foreach ($fks as $f) { $fkc[$f['t']][$f['c']] = true; }

$h = fn($s) => htmlspecialchars($s, ENT_QUOTES);
$out = "digraph candidate_management {\n  graph [rankdir=LR, splines=spline, nodesep=0.8, ranksep=1.4, fontname=\"Helvetica\", label=\"Candidate Management System — ER diagram (generated from MySQL information_schema)\\nPK primary key · FK foreign key · UK unique · ? nullable · crow's foot = many, bar = exactly one\", labelloc=t, fontsize=14];\n"
     . "  node [shape=plaintext, fontname=\"Helvetica\", fontsize=10];\n  edge [fontname=\"Helvetica\", fontsize=9, color=\"#555555\", dir=both];\n";
$byTable = [];
foreach ($cols as $c) { $byTable[$c['t']][] = $c; }
$color = ['candidates' => '#1F4E79', 'positions' => '#3B3B3B', 'applications' => '#7A3E00'];
foreach ($byTable as $t => $cs) {
    $out .= "  $t [label=<<table border=\"1\" cellborder=\"0\" cellspacing=\"0\" cellpadding=\"4\"><tr><td colspan=\"3\" bgcolor=\"" . ($color[$t] ?? '#3B3B3B') . "\"><font color=\"white\"><b>$t</b></font></td></tr>";
    foreach ($cs as $c) {
        $tags = array_filter([$c['k'] === 'PRI' ? 'PK' : '', isset($fkc[$t][$c['c']]) ? 'FK' : '', isset($uk[$t][$c['c']]) ? 'UK' : '']);
        $name = $c['k'] === 'PRI' ? "<b>{$c['c']}</b>" : $c['c'] . ($c['n'] === 'YES' ? ' ?' : '');
        $out .= "<tr><td align=\"left\" port=\"{$c['c']}\">$name</td><td align=\"left\"><font color=\"#666666\">" . $h($c['ty']) . "</font></td><td align=\"left\" port=\"{$c['c']}_r\">" . ($tags ? "<font color=\"#1F4E79\">" . implode(',', $tags) . "</font>" : ' ') . "</td></tr>";
    }
    $out .= "</table>>];\n";
}
foreach ($fks as $f) {
    // parent side: exactly one (tee); child side: zero or many (crow + circle)
    $out .= "  {$f['rt']}:{$f['rc']}_r:e -> {$f['t']}:{$f['c']}:w [arrowtail=teetee, arrowhead=crowodot, label=\"1 : N\\non delete " . strtolower($f['d']) . "\"];\n";
}
$out .= "}\n";
file_put_contents(__DIR__ . '/er_diagram.dot', $out);
echo "wrote docs/er_diagram.dot (" . count($byTable) . " tables, " . count($fks) . " relationships)\n";
