<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\GroupService;

final class InvitationController extends Controller
{
    public function accept(int $id): void
    {
        $this->respond($id, 'accept');
    }

    public function decline(int $id): void
    {
        $this->respond($id, 'decline');
    }

    public function cancel(int $id): void
    {
        $this->respond($id, 'cancel');
    }

    private function respond(int $id, string $action): void
    {
        try {
            $groupId = (new GroupService())->respondToInvitation(Auth::requireId(), $id, $action);
        } catch (ValidationException $e) {
            Session::flash('warning', implode(' ', $e->errors));
            $this->redirect('/groups');
        }
        $msg = ['accept' => 'You joined the group.', 'decline' => 'Invitation declined.', 'cancel' => 'Invitation cancelled.'];
        $this->success($msg[$action]);
        $this->redirect($action === 'decline' ? '/groups' : "/groups/$groupId");
    }
}
