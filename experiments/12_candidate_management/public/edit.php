<?php
/** Update candidate information (UPDATE). */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$id = int_param('id', $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);
$current = $id ? repo()->find($id) : null;
if (!$current) {
    render_error('Candidate not found. It may have been deleted.', 404);
}
$cand = $current;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    [$cand, $errors] = Validator::candidate($_POST);
    if (!$errors) {
        try {
            repo()->update($id, $cand);
            flash('success', 'Candidate details updated.');
            redirect('view.php?id=' . $id);
        } catch (PDOException $ex) {
            $dup = CandidateRepository::duplicateField($ex);
            if (!$dup) {
                throw $ex;
            }
            $errors[$dup[0]] = $dup[1];
        }
    }
}

page_header('Edit ' . $current['full_name']);
?>
<p class="crumbs"><a href="index.php">Candidates</a> › <a href="view.php?id=<?= $id ?>"><?= e($current['full_name']) ?></a> › Edit</p>
<h1>Edit candidate</h1>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the <?= count($errors) ?> highlighted field<?= count($errors) === 1 ? '' : 's' ?>.</div><?php endif; ?>
<form method="post" action="edit.php" class="card form" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <fieldset><legend>Candidate details</legend><?php candidate_fields($cand, $errors); ?></fieldset>
  <div class="actions"><button class="btn btn-primary" type="submit">Save changes</button><a class="link" href="view.php?id=<?= $id ?>">Cancel</a></div>
</form>
<?php page_footer();
