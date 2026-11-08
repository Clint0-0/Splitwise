<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ValidationException;
use App\Models\BudgetModel;
use App\Models\ExpenseModel;
use App\Models\GroupModel;
use App\Models\InvitationModel;
use App\Models\SettlementModel;
use App\Services\BalanceService;
use App\Services\GroupService;

final class GroupController extends Controller
{
    public function index(): void
    {
        $uid = Auth::requireId();
        $this->view('groups/index', [
            'title'       => 'Groups',
            'groups'      => (new GroupModel())->forUser($uid),
            'invitations' => (new InvitationModel())->pendingForUser($uid),
        ]);
    }

    public function create(): void
    {
        $this->view('groups/form', ['title' => 'New group', 'group' => null]);
    }

    public function store(): void
    {
        try {
            $id = (new GroupService())->create(Auth::requireId(), $this->request->allPost());
        } catch (ValidationException $e) {
            $this->back('/groups/create', $e);
        }
        $this->success('Group created. Invite your friends!');
        $this->redirect("/groups/$id");
    }

    public function show(int $id): void
    {
        $uid = Auth::requireId();
        $group = (new GroupService())->requireMember($id, $uid);
        $balances = new BalanceService();
        $this->view('groups/show', [
            'title'       => $group['name'],
            'group'       => $group,
            'uid'         => $uid,
            'members'     => (new GroupModel())->members($id),
            'suggestions' => $balances->suggestions($id),
            'pairs'       => $balances->pairwise($id, null),
            'expenses'    => (new ExpenseModel())->search($uid, ['group' => $id], 10, 0),
            'settlements' => (new SettlementModel())->forGroup($id, 10),
            'pending'     => (new InvitationModel())->pendingForGroup($id),
            'budgets'     => array_filter((new BudgetModel())->visibleFor($uid), fn($b) => (int) $b['group_id'] === $id),
        ]);
    }

    public function edit(int $id): void
    {
        $group = (new GroupService())->requireAdmin($id, Auth::requireId());
        $this->view('groups/form', ['title' => 'Edit group', 'group' => $group]);
    }

    public function update(int $id): void
    {
        try {
            (new GroupService())->update(Auth::requireId(), $id, $this->request->allPost());
        } catch (ValidationException $e) {
            $this->back("/groups/$id/edit", $e);
        }
        $this->success('Group updated.');
        $this->redirect("/groups/$id");
    }

    public function archive(int $id): void
    {
        $archive = $this->request->post('archive') === '1';
        (new GroupService())->setArchived(Auth::requireId(), $id, $archive);
        $this->success($archive ? 'Group archived (read-only).' : 'Group restored.');
        $this->redirect("/groups/$id");
    }

    public function invite(int $id): void
    {
        try {
            (new GroupService())->invite(Auth::requireId(), $id, $this->request->str('invite'));
        } catch (ValidationException $e) {
            $this->back("/groups/$id", $e);
        }
        $this->success('Invitation sent.');
        $this->redirect("/groups/$id");
    }

    public function removeMember(int $id, int $userId): void
    {
        $this->memberAction($id, fn(GroupService $s, int $me) => $s->removeMember($me, $id, $userId), 'Member removed.');
    }

    public function setRole(int $id, int $userId): void
    {
        $role = (string) $this->request->post('role');
        $this->memberAction($id, fn(GroupService $s, int $me) => $s->setRole($me, $id, $userId, $role), 'Role updated.');
    }

    public function leave(int $id): void
    {
        try {
            (new GroupService())->leave(Auth::requireId(), $id);
        } catch (ValidationException $e) {
            $this->back("/groups/$id", $e);
        }
        $this->success('You left the group.');
        $this->redirect('/groups');
    }

    private function memberAction(int $id, callable $fn, string $ok): void
    {
        try {
            $fn(new GroupService(), Auth::requireId());
        } catch (ValidationException $e) {
            $this->back("/groups/$id", $e);
        }
        $this->success($ok);
        $this->redirect("/groups/$id");
    }
}
