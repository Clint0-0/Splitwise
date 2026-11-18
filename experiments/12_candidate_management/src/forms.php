<?php
/** Form fragments shared by create.php, edit.php and view.php. */
declare(strict_types=1);

function candidate_fields(array $d, array $errors): void
{
    $rows = [
        // name, label, type, required, extra attributes, hint
        ['full_name', 'Full name', 'text', true, 'maxlength="100" autocomplete="name"', ''],
        ['email', 'Email', 'email', true, 'maxlength="150" autocomplete="email"', ''],
        ['phone', 'Mobile number', 'tel', true, 'maxlength="14" inputmode="tel" autocomplete="tel" placeholder="98765 43210"', '10 digits, starting with 6–9. +91 is optional.'],
        ['date_of_birth', 'Date of birth', 'date', false, 'max="' . date('Y-m-d') . '"', ''],
        ['highest_qualification', 'Highest qualification', 'text', true, 'maxlength="100" placeholder="e.g. B.E. Computer Engineering"', ''],
    ];
    foreach ($rows as [$name, $label, $type, $required, $extra, $hint]) {
        $hintId = $hint !== '' ? $name . '-hint' : '';
        echo '<div class="field"><label for="' . $name . '">' . e($label)
           . ($required ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="opt">(optional)</span>') . '</label>'
           . '<input id="' . $name . '" name="' . $name . '" type="' . $type . '"' . ($required ? ' required' : '') . ' ' . $extra
           . ' value="' . e((string) ($d[$name] ?? '')) . '"' . invalid_attrs($errors, $name)
           . ($hintId && !isset($errors[$name]) ? ' aria-describedby="' . $hintId . '"' : '') . '>'
           . ($hint !== '' ? '<p class="hint" id="' . $hintId . '">' . e($hint) . '</p>' : '')
           . field_error($errors, $name) . '</div>';
    }
}

function application_fields(array $d, array $errors, array $positions, array $exclude = []): void
{
    $req = '<span class="req" aria-hidden="true">*</span>';
    echo '<div class="field"><label for="position_id">Position ' . $req . '</label><select id="position_id" name="position_id" required' . invalid_attrs($errors, 'position_id') . '>'
       . '<option value="">Choose…</option>';
    foreach ($positions as $p) {
        if (in_array((int) $p['position_id'], $exclude, true)) {
            continue;
        }
        $sel = (string) ($d['position_id'] ?? '') === (string) $p['position_id'] ? ' selected' : '';
        echo '<option value="' . (int) $p['position_id'] . '"' . $sel . '>' . e($p['title']) . ' — ' . e($p['department']) . '</option>';
    }
    echo '</select>' . field_error($errors, 'position_id') . '</div>';
    echo '<div class="field"><label for="applied_on">Application date ' . $req . '</label><input id="applied_on" name="applied_on" type="date" required max="' . date('Y-m-d') . '" value="'
       . e((string) ($d['applied_on'] ?? date('Y-m-d'))) . '"' . invalid_attrs($errors, 'applied_on') . '>' . field_error($errors, 'applied_on') . '</div>';
    echo '<div class="field"><label for="status">Status ' . $req . '</label><select id="status" name="status" required' . invalid_attrs($errors, 'status') . '>';
    foreach (STATUSES as $s) {
        echo '<option' . (($d['status'] ?? 'Applied') === $s ? ' selected' : '') . '>' . e($s) . '</option>';
    }
    echo '</select>' . field_error($errors, 'status') . '</div>';
}

function status_pill(string $s): string
{
    return '<span class="pill pill-' . e(strtolower($s)) . '">' . e($s) . '</span>';
}
