<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\GroupModel;
use App\Models\SettlementModel;
use App\Services\BalanceService;
use App\Services\GroupService;
use App\Services\SettlementService;

final class SettlementController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $uid = Auth::requireId();
        $status = $this->request->query('status');
        $status = in_array($status, ['pending', 'confirmed', 'rejected', 'cancelled'], true) ? $status : null;
        $page = $this->request->page();
        $model = new SettlementModel();
        $total = $model->countForUser($uid, $status);
        $this->view('settlements/index', [
            'title'       => 'Settlements',
            'settlements' => $model->forUser($uid, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'status'      => $status,
            'page'        => $page,
            'pages'       => max(1, (int) ceil($total / self::PER_PAGE)),
            'uid'         => $uid,
        ]);
    }

    /** Form: /groups/{id}/settle?payer=&payee=&amount= (pre-filled from a suggestion). */
    public function create(int $id): void
    {
        $uid = Auth::requireId();
        $group = (new GroupService())->requireMember($id, $uid);
        $this->view('settlements/form', [
            'title'       => 'Record a payment',
            'group'       => $group,
            'uid'         => $uid,
            'members'     => (new GroupModel())->members($id),
            'suggestions' => (new BalanceService())->suggestions($id),
            'prefill'     => [
                'payer_id' => $this->request->queryInt('payer', $uid),
                'payee_id' => $this->request->queryInt('payee', 0),
                'amount'   => preg_match('/^\d+(\.\d{1,2})?$/', $this->request->queryStr('amount'))
                                ? $this->request->queryStr('amount') : '',
            ],
            'token'       => uuid4(),   // idempotency key: a double-submit cannot record twice
        ]);
    }

    public function store(): void
    {
        $uid = Auth::requireId();
        $groupId = ctype_digit($g = (string) $this->request->str('group_id')) ? (int) $g : 0;
        try {
            (new SettlementService())->record($uid, $this->request->allPost());
        } catch (ValidationException $e) {
            $this->back("/groups/$groupId/settle", $e);
        }
        $payee = ctype_digit($p = (string) $this->request->str('payee_id')) ? (int) $p : 0;
        $this->success($payee === $uid
            ? 'Payment recorded and confirmed.'
            : 'Payment recorded. It counts once the receiver confirms it.');
        $this->redirect("/groups/$groupId");
    }

    public function confirm(int $id): void
    {
        $this->respond($id, 'confirm', 'Payment confirmed.');
    }

    public function reject(int $id): void
    {
        $this->respond($id, 'reject', 'Payment rejected.');
    }

    public function cancel(int $id): void
    {
        $this->respond($id, 'cancel', 'Payment cancelled.');
    }

    private function respond(int $id, string $action, string $ok): void
    {
        try {
            (new SettlementService())->respond(Auth::requireId(), $id, $action);
            $this->success($ok);
        } catch (ValidationException $e) {
            Session::flash('warning', implode(' ', $e->errors));
        }
        // local paths only; "//host" would be a protocol-relative redirect to another site
        $back = $this->request->str('back');
        $this->redirect(preg_match('#^/(?!/)[a-z0-9/]*$#', $back) ? $back : '/settlements');
    }
}
