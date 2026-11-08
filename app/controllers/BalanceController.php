<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\GroupModel;
use App\Services\BalanceService;

final class BalanceController extends Controller
{
    public function index(): void
    {
        $uid = Auth::requireId();
        $balances = new BalanceService();
        $this->view('balances/index', [
            'title'   => 'Balances',
            'overall' => $balances->overall($uid),
            'groups'  => (new GroupModel())->forUser($uid),
            'pairs'   => $balances->pairwise(null, $uid),
            'uid'     => $uid,
        ]);
    }
}
