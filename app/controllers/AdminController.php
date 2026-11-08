<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\AnalyticsModel;
use App\Models\GroupModel;
use App\Models\UserModel;

/** System administration (route middleware 'admin' guards every action). */
final class AdminController extends Controller
{
    private const PER_PAGE = 25;
    private const AUDIT_TABLES = ['expenses', 'expense_splits', 'settlements'];

    public function index(): void
    {
        $this->view('admin/index', [
            'title' => 'Admin',
            'stats' => (new AnalyticsModel())->system(),
            'recent'=> (new AnalyticsModel())->audit(null, 10, 0),
        ]);
    }

    public function users(): void
    {
        $q = mb_substr(trim((string) $this->request->query('q', '')), 0, 100);
        $page = max(1, (int) $this->request->query('page', 1));
        $m = new UserModel();
        $this->view('admin/users', [
            'title' => 'Users',
            'q'     => $q,
            'users' => $m->search($q, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page'  => $page,
            'pages' => max(1, (int) ceil($m->countSearch($q) / self::PER_PAGE)),
            'me'    => Auth::requireId(),
        ]);
    }

    public function toggleUser(int $id): void
    {
        if ($id === Auth::requireId()) {
            throw new HttpException(403, 'You cannot deactivate your own account.');
        }
        $m = new UserModel();
        $u = $m->find($id) ?? throw new HttpException(404);
        $m->setActive($id, (int) $u['is_active'] !== 1);
        $this->success(((int) $u['is_active'] === 1 ? 'Deactivated ' : 'Reactivated ') . $u['full_name'] . '.');
        $this->redirect('/admin/users');
    }

    public function groups(): void
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $m = new GroupModel();
        $this->view('admin/groups', [
            'title'  => 'All groups',
            'groups' => $m->allWithStats(self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page'   => $page,
            'pages'  => max(1, (int) ceil($m->count() / self::PER_PAGE)),
        ]);
    }

    public function activity(): void
    {
        $table = $this->request->query('table');
        $table = in_array($table, self::AUDIT_TABLES, true) ? $table : null;
        $page = max(1, (int) $this->request->query('page', 1));
        $m = new AnalyticsModel();
        $this->view('admin/activity', [
            'title' => 'Activity log',
            'logs'  => $m->audit($table, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'table' => $table,
            'tables'=> self::AUDIT_TABLES,
            'page'  => $page,
            'pages' => max(1, (int) ceil($m->auditCount($table) / self::PER_PAGE)),
        ]);
    }
}
