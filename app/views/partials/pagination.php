<?php
/** @var int $page @var int $pages ; keeps current query string */
if (($pages ?? 1) <= 1) { return; }
$q = $_GET;
$link = function (int $p) use ($q): string { $q['page'] = $p; return '?' . http_build_query($q); };
?>
<nav aria-label="Pagination">
  <ul class="pagination pagination-sm">
    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link(max(1, $page - 1))) ?>">Previous</a></li>
    <?php for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++): ?>
      <li class="page-item<?= $p === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e($link($p)) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
    <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link(min($pages, $page + 1))) ?>">Next</a></li>
  </ul>
</nav>
