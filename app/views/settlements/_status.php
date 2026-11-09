<?php
/** Status pill for a settlement row $s (icon + word, never colour alone). */
$icons = ['confirmed' => 'bi-check-circle', 'pending' => 'bi-hourglass-split', 'rejected' => 'bi-x-circle', 'cancelled' => 'bi-slash-circle'];
?>
<span class="pill pill-<?= e($s['status']) ?>"><i class="bi <?= $icons[$s['status']] ?? 'bi-circle' ?>" aria-hidden="true"></i> <?= e($s['status']) ?></span>
