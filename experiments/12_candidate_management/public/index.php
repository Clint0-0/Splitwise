<?php
/** List + search candidates (SELECT). */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$q = trim(str_param('q', $_GET));
$q = mb_substr($q, 0, 100);
$positionId = int_param('position', $_GET);
$status = in_array(str_param('status', $_GET), STATUSES, true) ? str_param('status', $_GET) : '';
$rows = repo()->search($q, $positionId, $status);
$positions = repo()->positions();
$filtered = $q !== '' || $positionId || $status !== '';

page_header('Candidates');
?>
<div class="page-head">
  <h1>Candidates</h1>
  <a class="btn btn-primary" href="create.php">Register candidate</a>
</div>

<form class="filters" method="get" action="index.php" role="search">
  <div class="field"><label for="q">Search</label>
    <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Name, email or phone"></div>
  <div class="field"><label for="position">Position</label>
    <select id="position" name="position"><option value="">All</option>
      <?php foreach ($positions as $p): ?>
        <option value="<?= (int) $p['position_id'] ?>"<?= $positionId === (int) $p['position_id'] ? ' selected' : '' ?>><?= e($p['title']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="field"><label for="status">Status</label>
    <select id="status" name="status"><option value="">All</option>
      <?php foreach (STATUSES as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select></div>
  <div class="actions"><button class="btn" type="submit">Search</button>
    <?php if ($filtered): ?><a class="link" href="index.php">Clear</a><?php endif; ?></div>
</form>

<?php if (!$rows): ?>
  <div class="empty">
    <?php if ($filtered): ?>
      <p><strong>No candidates match these filters.</strong></p><p><a href="index.php">Show all candidates</a></p>
    <?php else: ?>
      <p><strong>No candidates yet.</strong></p><p><a class="btn btn-primary" href="create.php">Register the first candidate</a></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p class="count"><?= count($rows) ?> candidate<?= count($rows) === 1 ? '' : 's' ?></p>
  <div class="table-wrap"><table class="list">
    <thead><tr><th scope="col">Name</th><th scope="col">Contact</th><th scope="col">Qualification</th><th scope="col">Latest application</th><th scope="col" class="num">Apps</th><th scope="col"><span class="sr">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td data-label="Name"><a href="view.php?id=<?= (int) $r['candidate_id'] ?>"><strong><?= e($r['full_name']) ?></strong></a></td>
        <td data-label="Contact"><?= e($r['email']) ?><br><span class="muted"><?= e($r['phone']) ?></span></td>
        <td data-label="Qualification"><?= e($r['highest_qualification']) ?></td>
        <td data-label="Latest"><?= $r['latest'] !== null ? e($r['latest']) : '<span class="muted">none</span>' ?></td>
        <td data-label="Apps" class="num"><?= (int) $r['applications'] ?></td>
        <td class="row-actions"><a href="edit.php?id=<?= (int) $r['candidate_id'] ?>">Edit<span class="sr"> <?= e($r['full_name']) ?></span></a>
          <a class="danger" href="delete.php?id=<?= (int) $r['candidate_id'] ?>">Delete<span class="sr"> <?= e($r['full_name']) ?></span></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
<?php page_footer();
