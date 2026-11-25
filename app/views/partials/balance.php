<?php
/**
 * Coloured balance in words: $net (decimal string). Positive = gets back.
 * Optional $open = number of person-to-person debts still open for this member in the group:
 * a net of 0 with open debts is NOT "settled up" (they owe one person and are owed the same by
 * others), so it is shown as "evens out" with a hint to settle person by person.
 */
$cmp = bccomp_str((string) $net, '0');
$openDebts = (int) ($open ?? 0);
unset($open);
?>
<?php if ($cmp === 0 && $openDebts > 0): ?>
<span class="amount fw-semibold text-muted" title="What you owe and what you are owed in this group are equal, but the debts are with different people. See Person by person.">evens out <small class="fw-normal">· <?= $openDebts ?> open debt<?= $openDebts === 1 ? '' : 's' ?></small></span>
<?php else: ?>
<span class="amount fw-semibold <?= $cmp > 0 ? 'text-gets' : ($cmp < 0 ? 'text-owes' : 'text-muted') ?>"><?= e(balance_label((string) $net)) ?></span>
<?php endif; ?>
