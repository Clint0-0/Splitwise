<div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable table">
<table class="table small">
  <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Data</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td class="text-nowrap"><?= e(date('j M, H:i:s', strtotime($l['changed_at']))) ?></td>
      <td><?= e($l['changed_by_name'] ?? 'system') ?></td>
      <td><span class="pill pill-neutral"><?= e($l['action']) ?></span></td>
      <td><?= e($l['table_name']) ?> #<?= (int) $l['record_id'] ?></td>
      <td class="text-break" style="max-width: 420px"><code><?= e(mb_strimwidth((string) ($l['new_data'] ?? $l['old_data']), 0, 160, '…')) ?></code></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
