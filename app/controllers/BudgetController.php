<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\BudgetModel;
use App\Models\CategoryModel;
use App\Models\GroupModel;
use App\Services\BudgetService;

final class BudgetController extends Controller
{
    public function index(): void
    {
        $uid = Auth::requireId();
        $this->view('budgets/index', ['title' => 'Budgets', 'budgets' => (new BudgetModel())->visibleFor($uid), 'uid' => $uid]);
    }

    public function create(): void
    {
        $this->form('New budget', null);
    }

    public function store(): void
    {
        try {
            (new BudgetService())->create(Auth::requireId(), $this->request->allPost());
        } catch (ValidationException $e) {
            $this->back('/budgets/create', $e);
        }
        $this->success('Budget created.');
        $this->redirect('/budgets');
    }

    public function edit(int $id): void
    {
        $b = (new BudgetService())->requireManage(Auth::requireId(), $id);
        $this->form('Edit budget', $b);
    }

    public function update(int $id): void
    {
        try {
            (new BudgetService())->update(Auth::requireId(), $id, $this->request->allPost());
        } catch (ValidationException $e) {
            $this->back("/budgets/$id/edit", $e);
        }
        $this->success('Budget updated.');
        $this->redirect('/budgets');
    }

    public function delete(int $id): void
    {
        (new BudgetService())->delete(Auth::requireId(), $id);
        $this->success('Budget deleted.');
        $this->redirect('/budgets');
    }

    private function form(string $title, ?array $b): void
    {
        $uid = Auth::requireId();
        $this->view('budgets/form', [
            'title'      => $title,
            'b'          => $b,
            'categories' => (new CategoryModel())->allActive(),
            'adminGroups'=> array_filter((new GroupModel())->forUser($uid), fn($g) => $g['role'] === 'admin'),
            'periods'    => BudgetModel::PERIODS,
            'scripts'    => ['js/budget-form.js'],
        ]);
    }
}
