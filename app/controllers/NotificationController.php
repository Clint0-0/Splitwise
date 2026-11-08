<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\NotificationModel;

final class NotificationController extends Controller
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        $uid = Auth::requireId();
        $m = new NotificationModel();
        $page = max(1, (int) $this->request->query('page', 1));
        $this->view('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => $m->forUser($uid, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page'          => $page,
            'pages'         => max(1, (int) ceil($m->countForUser($uid) / self::PER_PAGE)),
        ]);
    }

    /** Mark read and jump to what the notification is about. */
    public function open(int $id): void
    {
        $uid = Auth::requireId();
        $m = new NotificationModel();
        $n = $m->find($id, $uid) ?? throw new HttpException(404);
        $m->markRead($id, $uid);
        $target = match ($n['entity_type']) {
            'expense'    => '/expenses/' . (int) $n['entity_id'],
            'group'      => '/groups/' . (int) $n['entity_id'],
            'settlement' => '/settlements',
            'budget'     => '/budgets',
            'invitation' => '/groups',
            default      => '/notifications',
        };
        $this->redirect($target);
    }

    public function readAll(): void
    {
        (new NotificationModel())->markAllRead(Auth::requireId());
        $this->success('All notifications marked as read.');
        $this->redirect('/notifications');
    }
}
