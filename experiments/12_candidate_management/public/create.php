<?php
/** Register a candidate + first application (INSERT, in one transaction). */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$open = repo()->positions(true);
$cand = [];
$app = ['applied_on' => date('Y-m-d'), 'status' => 'Applied'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    [$cand, $e1] = Validator::candidate($_POST);
    [$app, $e2] = Validator::application($_POST, array_map(fn($p) => (int) $p['position_id'], $open));
    $errors = $e1 + $e2;
    if (!$errors) {
        try {
            $id = repo()->create($cand, $app);
            flash('success', "Candidate {$cand['full_name']} registered.");
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

page_header('Register candidate');
?>
<p class="crumbs"><a href="index.php">Candidates</a> › Register</p>
<h1>Register candidate</h1>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the <?= count($errors) ?> highlighted field<?= count($errors) === 1 ? '' : 's' ?>.</div><?php endif; ?>
<form method="post" action="create.php" class="card form" novalidate>
  <?= csrf_field() ?>
  <fieldset><legend>Candidate details</legend><?php candidate_fields($cand, $errors); ?></fieldset>
  <fieldset><legend>Application</legend><?php application_fields($app, $errors, $open); ?></fieldset>
  <p class="hint"><span class="req" aria-hidden="true">*</span> required</p>
  <div class="actions"><button class="btn btn-primary" type="submit">Register candidate</button><a class="link" href="index.php">Cancel</a></div>
</form>
<?php page_footer();
