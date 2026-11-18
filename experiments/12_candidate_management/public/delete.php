<?php
/** Delete a candidate (DELETE). GET = confirmation page, POST = delete. */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$id = int_param('id', $isPost ? $_POST : $_GET);
$c = $id ? repo()->find($id) : null;

if ($isPost) {
    require_post_csrf();
    if ($c && repo()->delete($id)) {
        flash('success', "Candidate {$c['full_name']} and their applications were deleted.");
    } else {
        flash('error', 'That candidate no longer exists.');
    }
    redirect('index.php');
}
if (!$c) {
    render_error('Candidate not found. It may already have been deleted.', 404);
}
$n = count(repo()->applications($id));

page_header('Delete ' . $c['full_name']);
?>
<p class="crumbs"><a href="index.php">Candidates</a> › <a href="view.php?id=<?= $id ?>"><?= e($c['full_name']) ?></a> › Delete</p>
<div class="card confirm">
  <h1>Delete <?= e($c['full_name']) ?>?</h1>
  <p>This permanently removes the candidate<?= $n ? " and their <strong>{$n} application" . ($n === 1 ? '' : 's') . '</strong>' : '' ?>. It cannot be undone.</p>
  <form method="post" action="delete.php">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="actions"><button class="btn btn-danger" type="submit">Yes, delete candidate</button><a class="link" href="view.php?id=<?= $id ?>">No, keep</a></div>
  </form>
</div>
<?php page_footer();
