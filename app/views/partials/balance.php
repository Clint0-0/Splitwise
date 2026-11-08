<?php
/** Coloured balance: $net (decimal string). Positive = gets back. */
$cmp = bccomp_str((string) $net, '0');
?>
<span class="amount <?= $cmp > 0 ? 'text-gets' : ($cmp < 0 ? 'text-owes' : 'text-muted') ?>"><?= e(balance_label((string) $net)) ?></span>
