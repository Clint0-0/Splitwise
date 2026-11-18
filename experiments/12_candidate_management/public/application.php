<?php
/** Application actions for one candidate: add (INSERT), status (UPDATE), delete (DELETE, with confirmation). */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$src = $isPost ? $_POST : $_GET;
$action = (string) ($src['action'] ?? '');
$cid = int_param('candidate_id', $src);
$c = $cid ? repo()->find($cid) : null;
if (!$c) {
    render_error('Candidate not found.', 404);
}
$back = 'view.php?id=' . $cid;

if (!$isPost) {
    // confirmation page for withdrawing an application
    $aid = int_param('application_id', $src);
    $a = $action === 'delete' && $aid ? repo()->application($aid, $cid) : null;
    if (!$a) {
        render_error('Application not found.', 404);
    }
    page_header('Withdraw application');
    echo '<div class="card confirm"><h1>Withdraw this application?</h1><p>' . e($c['full_name']) . ' → <strong>' . e($a['title']) . '</strong> (' . e($a['status']) . ')</p>'
       . '<form method="post" action="application.php">' . csrf_field()
       . '<input type="hidden" name="action" value="delete"><input type="hidden" name="candidate_id" value="' . $cid . '"><input type="hidden" name="application_id" value="' . $aid . '">'
       . '<div class="actions"><button class="btn btn-danger" type="submit">Yes, withdraw</button><a class="link" href="' . e($back) . '">No, keep</a></div></form></div>';
    page_footer();
    exit;
}

require_post_csrf();
switch ($action) {
    case 'add':
        $open = array_map(fn($p) => (int) $p['position_id'], repo()->positions(true));
        [$app, $errors] = Validator::application($_POST, $open);
        if (!$errors) {
            try {
                repo()->addApplication($cid, $app);
                flash('success', 'Application added.');
                redirect($back);
            } catch (PDOException $ex) {
                $dup = CandidateRepository::duplicateField($ex);
                if (!$dup) {
                    throw $ex;
                }
                $errors[$dup[0]] = $dup[1];
            }
        }
        $_SESSION['app_errors'] = $errors;
        $_SESSION['app_old'] = $app;
        flash('error', 'The application was not added — see the form below.');
        redirect($back . '#apps');
    case 'status':
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, STATUSES, true)) {
            flash('error', 'Choose a valid status.');
        } elseif (repo()->setStatus(int_param('application_id', $_POST), $cid, $status)) {
            flash('success', "Status changed to {$status}.");
        } else {
            flash('info', 'Status unchanged.');
        }
        redirect($back);
    case 'delete':
        $ok = repo()->deleteApplication(int_param('application_id', $_POST), $cid);
        flash($ok ? 'success' : 'error', $ok ? 'Application withdrawn.' : 'Application not found.');
        redirect($back);
    default:
        render_error('Unknown action.', 400);
}
