<?php $icons = ['group_invite' => 'bi-person-plus', 'expense_added' => 'bi-receipt', 'expense_updated' => 'bi-pencil', 'expense_deleted' => 'bi-trash',
  'settlement_request' => 'bi-hourglass-split', 'settlement_confirmed' => 'bi-check-circle', 'settlement_rejected' => 'bi-x-circle',
  'budget_warning' => 'bi-piggy-bank', 'member_removed' => 'bi-person-dash']; ?>
<div class="page-head">
  <h1 class="page-title">Notifications</h1>
  <div class="page-actions"><form method="post" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn-quiet">Mark all as read</button></form></div>
</div>
<section class="panel" aria-label="Notifications">
  <?php if (!$notifications): ?><div class="empty"><i class="bi bi-bell" aria-hidden="true"></i><p>Nothing new. You will see invitations, new expenses and payments here.</p></div><?php endif; ?>
  <ul class="rows">
  <?php foreach ($notifications as $n): $unreadRow = (int) $n['is_read'] === 0; ?>
    <li class="border-bottom">
      <form method="post" action="<?= e(url('/notifications/' . (int) $n['notification_id'] . '/open')) ?>"><?= csrf_field() ?>
        <button class="notif<?= $unreadRow ? ' is-unread' : '' ?>">
          <span class="avatar sm" aria-hidden="true"><i class="bi <?= $icons[$n['type']] ?? 'bi-bell' ?>"></i></span>
          <span class="notif-body"><strong><?= e($n['title']) ?></strong><?= $unreadRow ? ' <span class="visually-hidden">(unread)</span>' : '' ?>
            <span class="d-block"><?= e($n['message']) ?></span></span>
          <time class="note" datetime="<?= e($n['created_at']) ?>"><?= e(date('j M, H:i', strtotime($n['created_at']))) ?></time>
        </button>
      </form>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<div class="mt-3"><?php require __DIR__ . '/../partials/pagination.php'; ?></div>
