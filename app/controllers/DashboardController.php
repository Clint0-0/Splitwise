<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\BudgetModel;
use App\Models\ExpenseModel;
use App\Models\GroupModel;
use App\Models\InvitationModel;
use App\Models\SettlementModel;
use App\Services\BalanceService;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $uid = Auth::requireId();
        $budgets = array_filter((new BudgetModel())->visibleFor($uid),
            fn($b) => $b['budget_status'] !== 'ok' && $b['end_date'] >= date('Y-m-d'));
        $this->view('dashboard/index', [
            'title'       => 'Dashboard',
            'overall'     => (new BalanceService())->overall($uid),
            'groups'      => (new GroupModel())->forUser($uid),
            'recent'      => (new ExpenseModel())->recentForUser($uid, 6),
            'toConfirm'   => (new SettlementModel())->awaitingConfirmation($uid),
            'invitations' => (new InvitationModel())->pendingForUser($uid),
            'budgetAlerts'=> $budgets,
            'pairs'       => (new BalanceService())->pairwise(null, $uid),
            'uid'         => $uid,
            'monthSpend'  => (string) ((new \App\Models\AnalyticsModel())->monthShare($uid, date('Y-m')) ?? '0.00'),
        ]);
    }
}
