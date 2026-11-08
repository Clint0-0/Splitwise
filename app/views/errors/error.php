<div class="text-center py-5">
  <h1 class="display-5"><?= (int) $status ?></h1>
  <p class="lead"><?= e($message) ?></p>
  <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
</div>
