<?php
/** One candidate: details + applications (SELECT with JOIN). */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$id = int_param('id', $_GET);
$c = $id ? repo()->find($id) : null;
if (!$c) {
    render_error('Candidate not found. It may have been deleted.', 404);
}
$apps = repo()->applications($id);
$applied = array_map(fn($a) => (int) $a['position_id'], $apps);
$open = array_values(array_filter(repo()->positions(true), fn($p) => !in_array((int) $p['position_id'], $applied, true)));
$errors = $_SESSION['app_errors'] ?? [];
$old = $_SESSION['app_old'] ?? ['applied_on' => date('Y-m-d'), 'status' => 'Applied'];
unset($_SESSION['app_errors'], $_SESSION['app_old']);

page_header($c['full_name']);
?>
<p class="crumbs"><a href="index.php">Candidates</a> › <?= e($c['full_name']) ?></p>
<div class="page-head">
  <h1><?= e($c['full_name']) ?></h1>
  <div><a class="btn" href="edit.php?id=<?= $id ?>">Edit details</a> <a class="btn btn-danger" href="delete.php?id=<?= $id ?>">Delete candidate</a></div>
</div>

<section class="card" aria-labelledby="det"><h2 id="det">Details</h2>
  <dl class="details">
    <dt>Candidate ID</dt><dd>#<?= (int) $c['candidate_id'] ?></dd>
    <dt>Email</dt><dd><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></dd>
    <dt>Mobile</dt><dd><?= e($c['phone']) ?></dd>
    <dt>Date of birth</dt><dd><?= e(nice_date($c['date_of_birth'])) ?></dd>
    <dt>Highest qualification</dt><dd><?= e($c['highest_qualification']) ?></dd>
    <dt>Registered</dt><dd><?= e(date('j M Y, H:i', strtotime($c['created_at']))) ?></dd>
    <?php if ($c['updated_at']): ?><dt>Last updated</dt><dd><?= e(date('j M Y, H:i', strtotime($c['updated_at']))) ?></dd><?php endif; ?>
  </dl>
</section>

<section class="card" aria-labelledby="apps"><h2 id="apps">Applications</h2>
<?php if (!$apps): ?>
  <p class="empty-inline">No applications. Add one below.</p>
<?php else: ?>
  <div class="table-wrap"><table class="list">
    <thead><tr><th scope="col">Position</th><th scope="col">Applied on</th><th scope="col">Status</th><th scope="col">Change status</th><th scope="col"><span class="sr">Withdraw</span></th></tr></thead>
    <tbody>
    <?php foreach ($apps as $a): ?>
      <tr>
        <td data-label="Position"><strong><?= e($a['title']) ?></strong><br><span class="muted"><?= e($a['department']) ?></span></td>
        <td data-label="Applied on"><?= e(nice_date($a['applied_on'])) ?></td>
        <td data-label="Status"><?= status_pill($a['status']) ?></td>
        <td data-label="Change status">
          <form method="post" action="application.php" class="inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="status">
            <input type="hidden" name="candidate_id" value="<?= $id ?>"><input type="hidden" name="application_id" value="<?= (int) $a['application_id'] ?>">
            <label class="sr" for="st<?= (int) $a['application_id'] ?>">New status for <?= e($a['title']) ?></label>
            <select id="st<?= (int) $a['application_id'] ?>" name="status">
              <?php foreach (STATUSES as $s): ?><option<?= $a['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-small" type="submit">Save</button>
          </form>
        </td>
        <td class="row-actions"><a class="danger" href="application.php?action=delete&amp;candidate_id=<?= $id ?>&amp;application_id=<?= (int) $a['application_id'] ?>">Withdraw<span class="sr"> <?= e($a['title']) ?></span></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>

<?php if ($open): ?>
  <h3>Add an application</h3>
  <form method="post" action="application.php" class="form form-row" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="candidate_id" value="<?= $id ?>">
    <?php application_fields($old, $errors, $open); ?>
    <div class="actions"><button class="btn btn-primary" type="submit">Add application</button></div>
  </form>
<?php else: ?>
  <p class="muted">This candidate has applied for every open position.</p>
<?php endif; ?>
</section>
<?php page_footer();
