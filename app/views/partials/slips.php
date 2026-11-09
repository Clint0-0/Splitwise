<?php
/**
 * IOU slips — one row per person-to-person debt (the design's signature element).
 * @var array $pairs rows from BalanceModel::pairwise
 * @var int   $uid   current user (slips are worded from their side when they are involved)
 * @var bool  $showGroupName (optional)
 */
$showGroupName = $showGroupName ?? true;
?>
<?php if (!$pairs): ?>
  <div class="empty"><i class="bi bi-check2-circle" aria-hidden="true"></i><p>All settled up. Nobody owes anybody here.</p></div>
<?php else: ?>
<ul class="slips">
  <?php foreach ($pairs as $p):
    $iOwe = (int) $p['debtor_id'] === $uid;
    $owedToMe = (int) $p['creditor_id'] === $uid;
    $other = $iOwe ? $p['creditor_name'] : ($owedToMe ? $p['debtor_name'] : $p['debtor_name']);
    $settled = bccomp_str((string) $p['settled'], '0') > 0; ?>
    <li class="slip<?= $iOwe ? ' is-owes' : ($owedToMe ? ' is-gets' : '') ?>">
      <span class="avatar" aria-hidden="true"><?= e(initials($other)) ?></span>
      <div class="slip-who">
        <?php if ($iOwe): ?>You owe <strong><?= e($p['creditor_name']) ?></strong>
        <?php elseif ($owedToMe): ?><strong><?= e($p['debtor_name']) ?></strong> owes you
        <?php else: ?><strong><?= e($p['debtor_name']) ?></strong> owes <strong><?= e($p['creditor_name']) ?></strong><?php endif; ?>
        <div class="slip-meta"><?php if ($showGroupName): ?><?= e($p['group_name']) ?><?php if ($settled): ?>, <?php endif; ?><?php endif; ?><?php if ($settled): ?><?= e(inr($p['settled'])) ?> paid back so far<?php endif; ?></div>
      </div>
      <div class="slip-amount <?= $iOwe ? 'text-owes' : ($owedToMe ? 'text-gets' : '') ?>"><?= e(inr($p['remaining'])) ?>
        <?php if ($settled && bccomp_str((string) $p['owed_back'], '0') === 0): ?><small>of <?= e(inr($p['original_owed'])) ?></small><?php endif; ?></div>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
