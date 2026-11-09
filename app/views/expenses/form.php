<?php
$editing = $e !== null;
$gid = $group ? (int) $group['group_id'] : null;
$method = old($old, 'split_method', $e['split_method'] ?? 'equal');
// participants & per-person inputs: old input (after error) > existing splits > everyone
$oldParts = isset($old['participants']) && is_array($old['participants']) ? array_map('intval', $old['participants']) : null;
$oldShare = isset($old['share']) && is_array($old['share']) ? $old['share'] : [];
$selected = $oldParts ?? ($editing ? array_map('intval', array_keys($splits)) : array_map(fn($m) => (int) $m['user_id'], $members));
$inputFor = function (int $uid) use ($oldShare, $splits, $method): string {
    if (isset($oldShare[$uid]) && is_string($oldShare[$uid])) { return $oldShare[$uid]; }
    $s = $splits[$uid] ?? null;
    if (!$s) { return ''; }
    return (string) match ($method) {
        'percentage' => $s['share_percentage'], 'exact' => $s['share_amount'],
        'shares' => $s['share_units'], 'adjustment' => $s['share_adjustment'], default => '',
    };
};
$labels = ['equal' => 'Equally', 'percentage' => 'By percentage', 'exact' => 'Exact amounts', 'shares' => 'By shares', 'adjustment' => 'Equal + adjustments'];
?>
<div class="page-head"><div><h1 class="page-title"><?= $editing ? 'Edit expense' : 'Add an expense' ?></h1>
  <?php if ($editing): ?><p class="page-sub"><?= $group ? 'in ' . e($group['name']) : 'Personal expense' ?></p><?php endif; ?></div></div>

<?php if (!$editing): ?>
  <form method="get" action="<?= e(url('/expenses/create')) ?>" class="mb-3 form-narrow">
    <label class="form-label" for="groupPick">Who is this expense with?</label>
    <div class="d-flex gap-2">
      <select class="form-select" id="groupPick" name="group" data-autosubmit>
        <option value="">Only me (personal expense)</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['group_id'] ?>" <?= $gid === (int) $g['group_id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-outline-secondary">Choose</button></noscript>
    </div>
  </form>
<?php endif; ?>

<div class="panel form-narrow">
<form method="post" enctype="multipart/form-data" novalidate id="expenseForm"
      action="<?= e(url($editing ? '/expenses/' . (int) $e['expense_id'] : '/expenses')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="group_id" value="<?= $gid ?? '' ?>">
  <?php if ($editing): ?><input type="hidden" name="version" value="<?= (int) $e['version'] ?>"><?php endif; ?>
  <?= field_error($errors, 'version') ?>

  <div class="row g-3">
    <div class="col-md-8">
      <label class="form-label" for="description">Description</label>
      <input class="form-control<?= is_invalid($errors, 'description') ?>" id="description" name="description" maxlength="120" required
             value="<?= e(old($old, 'description', $e['description'] ?? '')) ?>" placeholder="Dinner at Baga">
      <?= field_error($errors, 'description') ?>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="total_amount">Amount (₹)</label>
      <input class="form-control<?= is_invalid($errors, 'total_amount') ?>" id="total_amount" name="total_amount" inputmode="decimal" required
             pattern="\d{1,10}(\.\d{1,2})?" value="<?= e(old($old, 'total_amount', $e['total_amount'] ?? '')) ?>" placeholder="0.00">
      <?= field_error($errors, 'total_amount') ?>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="category_id">Category</label>
      <select class="form-select<?= is_invalid($errors, 'category_id') ?>" id="category_id" name="category_id">
        <option value="">Choose…</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['category_id'] ?>" <?= (int) old($old, 'category_id', $e['category_id'] ?? 0) === (int) $c['category_id'] ? 'selected' : '' ?>>
            <?= $c['parent_category_id'] ? '&nbsp;&nbsp;— ' : '' ?><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'category_id') ?>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="expense_date">Date</label>
      <input class="form-control<?= is_invalid($errors, 'expense_date') ?>" type="date" id="expense_date" name="expense_date" max="<?= date('Y-m-d') ?>"
             value="<?= e(old($old, 'expense_date', $e['expense_date'] ?? date('Y-m-d'))) ?>">
      <?= field_error($errors, 'expense_date') ?>
    </div>
  </div>

  <?php if ($group): ?>
    <div class="form-section">
    <div class="mb-3">
      <label class="form-label" for="paid_by">Paid by</label>
      <select class="form-select<?= is_invalid($errors, 'paid_by') ?>" id="paid_by" name="paid_by">
        <?php foreach ($members as $m): ?>
          <option value="<?= (int) $m['user_id'] ?>" <?= (int) old($old, 'paid_by', $e['paid_by'] ?? $uid) === (int) $m['user_id'] ? 'selected' : '' ?>>
            <?= e($m['full_name']) ?><?= (int) $m['user_id'] === $uid ? ' (you)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'paid_by') ?>
    </div>

    <fieldset class="mb-3">
      <legend class="form-label">How should it be split?</legend>
      <div class="method-choice" role="group" aria-label="Split method">
        <?php foreach ($methods as $mth): ?>
          <input type="radio" class="btn-check" name="split_method" id="m_<?= $mth ?>" value="<?= $mth ?>" <?= $method === $mth ? 'checked' : '' ?>>
          <label class="btn btn-outline-primary btn-sm" for="m_<?= $mth ?>"><?= e($labels[$mth]) ?></label>
        <?php endforeach; ?>
      </div>
      <?= field_error($errors, 'split_method') ?>
    </fieldset>

    <div class="mb-2"><?= field_error($errors, 'participants') ?></div>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable table"><table class="table split-table" id="participants">
      <thead><tr><th scope="col">Who is in it</th><th scope="col" style="width: 38%" class="share-head">Share</th><th scope="col" class="num">Their part</th></tr></thead>
      <tbody>
      <?php foreach ($members as $m): $u = (int) $m['user_id']; ?>
        <tr data-user="<?= $u ?>">
          <td>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="participants[]" value="<?= $u ?>" id="p_<?= $u ?>" <?= in_array($u, $selected, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="p_<?= $u ?>"><?= e($m['full_name']) ?></label>
            </div>
          </td>
          <td>
            <input class="form-control form-control-sm share-input<?= is_invalid($errors, "share_$u") ?>" name="share[<?= $u ?>]"
                   value="<?= e($inputFor($u)) ?>" inputmode="decimal" aria-label="Share for <?= e($m['full_name']) ?>">
            <?= field_error($errors, "share_$u") ?>
          </td>
          <td class="num preview">–</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="3" class="split-status" id="splitStatus" aria-live="polite"></td></tr></tfoot>
    </table></div>
    </div>
  <?php endif; ?>

  <div class="form-section"><div class="row g-3">
    <div class="col-md-6">
      <label class="form-label" for="tags">Tags <span class="text-muted fw-normal">(optional, comma separated)</span></label>
      <input class="form-control" id="tags" name="tags" value="<?= e(old($old, 'tags', $tags)) ?>" placeholder="goa-2026, weekend">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="receipt">Receipt (JPG, PNG or PDF, max <?= e(App\Services\UploadService::human(App\Services\UploadService::maxBytes())) ?>)</label>
      <input class="form-control<?= is_invalid($errors, 'receipt') ?>" type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
      <?= field_error($errors, 'receipt') ?>
    </div>
    <div class="col-12">
      <label class="form-label" for="notes">Notes <span class="text-muted fw-normal">(optional)</span></label>
      <textarea class="form-control<?= is_invalid($errors, 'notes') ?>" id="notes" name="notes" rows="2" maxlength="500"><?= e(old($old, 'notes', $e['notes'] ?? '')) ?></textarea>
      <?= field_error($errors, 'notes') ?>
    </div>
  </div></div>

  <div class="mt-4">
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add expense' ?></button>
    <a class="btn btn-link" href="<?= e(url($editing ? '/expenses/' . (int) $e['expense_id'] : ($gid ? "/groups/$gid" : '/expenses'))) ?>">Cancel</a>
  </div>
</form>
</div>
