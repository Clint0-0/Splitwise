<?php $here = (new App\Core\Request())->path; ?>
<ul class="nav nav-tabs mb-4">
  <?php foreach (['/admin' => 'Overview', '/admin/users' => 'Users', '/admin/groups' => 'Groups', '/admin/activity' => 'Activity log'] as $href => $label): ?>
    <li class="nav-item"><a class="nav-link<?= $here === $href ? ' active' : '' ?>" href="<?= e(url($href)) ?>"><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>
