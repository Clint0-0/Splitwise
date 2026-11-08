<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\CategoryModel;
use App\Models\ExpenseModel;
use App\Models\GroupModel;
use App\Services\ExpenseService;
use App\Services\GroupService;
use App\Services\SplitCalculator;
use App\Services\UploadService;

final class ExpenseController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        $uid = Auth::requireId();
        $f = [
            'q'        => mb_substr(trim((string) $this->request->query('q', '')), 0, 100),
            'group'    => (string) $this->request->query('group', ''),
            'category' => (string) $this->request->query('category', ''),
            'from'     => $this->dateOrEmpty($this->request->query('from')),
            'to'       => $this->dateOrEmpty($this->request->query('to')),
            'tag'      => mb_substr(trim((string) $this->request->query('tag', '')), 0, 30),
            'deleted'  => $this->request->query('deleted') === '1' ? '1' : '',
        ];
        if ($f['group'] !== '' && $f['group'] !== 'personal' && !ctype_digit($f['group'])) {
            $f['group'] = '';
        }
        $page  = max(1, (int) $this->request->query('page', 1));
        $model = new ExpenseModel();
        $total = $model->countSearch($uid, $f);
        $this->view('expenses/index', [
            'title'      => 'Expenses',
            'filters'    => $f,
            'expenses'   => $model->search($uid, $f, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page'       => $page,
            'pages'      => max(1, (int) ceil($total / self::PER_PAGE)),
            'total'      => $total,
            'groups'     => (new GroupModel())->forUser($uid),
            'categories' => (new CategoryModel())->allActive(),
        ]);
    }

    public function create(): void
    {
        $uid = Auth::requireId();
        $groupId = (int) $this->request->query('group', 0);
        $group = $groupId > 0 ? (new GroupService())->requireMember($groupId, $uid) : null;
        $this->form('Add expense', null, $group, $uid);
    }

    public function store(): void
    {
        $uid = Auth::requireId();
        try {
            $id = (new ExpenseService())->create($uid, $this->request->allPost(), $this->request->file('receipt'));
        } catch (ValidationException $e) {
            $g = $this->request->str('group_id');
            $this->back('/expenses/create' . ($g !== '' ? '?group=' . (int) $g : ''), $e);
        }
        $this->success('Expense added.');
        $this->redirect("/expenses/$id");
    }

    public function show(int $id): void
    {
        $uid = Auth::requireId();
        $service = new ExpenseService();
        $e = $service->requireViewable($id, $uid);
        $model = new ExpenseModel();
        $this->view('expenses/show', [
            'title'    => $e['description'],
            'e'        => $e,
            'splits'   => $model->splits($id),
            'tags'     => $model->tags($id),
            'receipts' => $model->receipts($id),
            'history'  => $model->history($id),
            'canEdit'  => $service->canEdit($e, $uid),
            'lock'     => $service->lockReason($e),
            'isAdmin'  => $e['group_id'] !== null
                && ((new GroupModel())->membership((int) $e['group_id'], $uid)['role'] ?? '') === 'admin',
            'uid'      => $uid,
        ]);
    }

    public function edit(int $id): void
    {
        $uid = Auth::requireId();
        $service = new ExpenseService();
        $e = $service->requireViewable($id, $uid);
        if (!$service->canEdit($e, $uid) && ($reason = $service->lockReason($e)) !== null) {
            \App\Core\Session::flash('warning', $reason);
            $this->redirect("/expenses/$id");
        }
        if ((int) $e['is_deleted'] === 1 || !$service->canEdit($e, $uid)) {
            throw new HttpException(403, 'You cannot edit this expense.');
        }
        $group = $e['group_id'] !== null ? (new GroupModel())->find((int) $e['group_id']) : null;
        $this->form('Edit expense', $e, $group, $uid);
    }

    public function update(int $id): void
    {
        try {
            (new ExpenseService())->update(Auth::requireId(), $id, $this->request->allPost(), $this->request->file('receipt'));
        } catch (ValidationException $e) {
            $this->back("/expenses/$id/edit", $e);
        }
        $this->success('Expense updated.');
        $this->redirect("/expenses/$id");
    }

    public function delete(int $id): void
    {
        try {
            (new ExpenseService())->delete(Auth::requireId(), $id);
        } catch (ValidationException $e) {
            $this->back("/expenses/$id", $e);
        }
        $this->success('Expense deleted. A group admin can restore it.');
        $this->redirect("/expenses/$id");
    }

    public function restore(int $id): void
    {
        try {
            (new ExpenseService())->restore(Auth::requireId(), $id);
        } catch (ValidationException $e) {
            $this->back("/expenses/$id", $e);
        }
        $this->success('Expense restored.');
        $this->redirect("/expenses/$id");
    }

    /** Serve a receipt only to someone allowed to see its expense. */
    public function receipt(int $id): void
    {
        $r = (new ExpenseModel())->receipt($id) ?? throw new HttpException(404);
        (new ExpenseService())->requireViewable((int) $r['expense_id'], Auth::requireId());
        $path = UploadService::dir() . '/' . basename($r['file_path']);
        if (!is_file($path)) {
            throw new HttpException(404, 'Receipt file is missing.');
        }
        header('Content-Type: ' . $r['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . rawurlencode($r['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private function form(string $title, ?array $e, ?array $group, int $uid): void
    {
        $model = new ExpenseModel();
        $this->view('expenses/form', [
            'title'      => $title,
            'e'          => $e,
            'group'      => $group,
            'members'    => $group ? array_values(array_filter((new GroupModel())->members((int) $group['group_id']),
                                fn($m) => $m['status'] === 'active')) : [],
            'splits'     => $e ? array_column($model->splits((int) $e['expense_id']), null, 'user_id') : [],
            'tags'       => $e ? implode(', ', $model->tags((int) $e['expense_id'])) : '',
            'groups'     => array_filter((new GroupModel())->forUser($uid), fn($g) => (int) $g['is_archived'] === 0),
            'categories' => (new CategoryModel())->allActive(),
            'methods'    => SplitCalculator::METHODS,
            'uid'        => $uid,
            'scripts'    => ['js/expense-form.js'],
        ]);
    }

    private function dateOrEmpty(mixed $v): string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }
}
