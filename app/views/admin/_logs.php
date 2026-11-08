<div class="table-responsive">
<table class="table table-sm small mb-0">
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Change</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td class="text-nowrap"><?= e(substr($l['changed_at'], 0, 19)) ?></td>
      <td><?= e($l['changed_by_name'] ?? 'system') ?></td>
      <td><span class="badge text-bg-light"><?= e($l['action']) ?></span></td>
      <td><?= e($l['table_name']) ?> #<?= (int) $l['record_id'] ?></td>
      <td class="text-break" style="max-width: 420px"><code><?= e(mb_strimwidth((string) ($l['new_data'] ?? $l['old_data']), 0, 160, '…')) ?></code></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
