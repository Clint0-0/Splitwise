<?php
/**
 * All routes. {id}/{userId} match digits only.
 * Every POST is CSRF-checked by the Router. 'auth' = logged in, 'guest' = logged out, 'admin' = system admin.
 * Group membership / roles are checked in the services (server-side authorisation).
 *
 * @var App\Core\Router $router
 */
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AnalyticsController;
use App\Controllers\AuthController;
use App\Controllers\BalanceController;
use App\Controllers\BudgetController;
use App\Controllers\DashboardController;
use App\Controllers\ExpenseController;
use App\Controllers\GroupController;
use App\Controllers\InvitationController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\SettlementController;

$auth  = ['auth'];
$guest = ['guest'];
$admin = ['auth', 'admin'];

// Auth
$router->get('/',          [AuthController::class, 'home']);
$router->get('/login',     [AuthController::class, 'showLogin'], $guest);
$router->post('/login',    [AuthController::class, 'login'], $guest);
$router->get('/register',  [AuthController::class, 'showRegister'], $guest);
$router->post('/register', [AuthController::class, 'register'], $guest);
$router->post('/logout',   [AuthController::class, 'logout'], $auth);

// Dashboard & profile
$router->get('/dashboard',         [DashboardController::class, 'index'], $auth);
$router->get('/profile',           [ProfileController::class, 'show'], $auth);
$router->post('/profile',          [ProfileController::class, 'update'], $auth);
$router->post('/profile/password', [ProfileController::class, 'password'], $auth);

// Groups, members, invitations
$router->get('/groups',                              [GroupController::class, 'index'], $auth);
$router->get('/groups/create',                       [GroupController::class, 'create'], $auth);
$router->post('/groups',                             [GroupController::class, 'store'], $auth);
$router->get('/groups/{id}',                         [GroupController::class, 'show'], $auth);
$router->get('/groups/{id}/edit',                    [GroupController::class, 'edit'], $auth);
$router->post('/groups/{id}',                        [GroupController::class, 'update'], $auth);
$router->post('/groups/{id}/archive',                [GroupController::class, 'archive'], $auth);
$router->post('/groups/{id}/invite',                 [GroupController::class, 'invite'], $auth);
$router->post('/groups/{id}/leave',                  [GroupController::class, 'leave'], $auth);
$router->post('/groups/{id}/members/{userId}/remove',[GroupController::class, 'removeMember'], $auth);
$router->post('/groups/{id}/members/{userId}/role',  [GroupController::class, 'setRole'], $auth);
$router->post('/invitations/{id}/accept',            [InvitationController::class, 'accept'], $auth);
$router->post('/invitations/{id}/decline',           [InvitationController::class, 'decline'], $auth);
$router->post('/invitations/{id}/cancel',            [InvitationController::class, 'cancel'], $auth);

// Expenses
$router->get('/expenses',              [ExpenseController::class, 'index'], $auth);
$router->get('/expenses/create',       [ExpenseController::class, 'create'], $auth);
$router->post('/expenses',             [ExpenseController::class, 'store'], $auth);
$router->get('/expenses/{id}',         [ExpenseController::class, 'show'], $auth);
$router->get('/expenses/{id}/edit',    [ExpenseController::class, 'edit'], $auth);
$router->post('/expenses/{id}',        [ExpenseController::class, 'update'], $auth);
$router->post('/expenses/{id}/delete', [ExpenseController::class, 'delete'], $auth);
$router->post('/expenses/{id}/restore',[ExpenseController::class, 'restore'], $auth);
$router->get('/receipts/{id}',         [ExpenseController::class, 'receipt'], $auth);

// Balances & settlements
$router->get('/balances',                  [BalanceController::class, 'index'], $auth);
$router->get('/settlements',               [SettlementController::class, 'index'], $auth);
$router->get('/groups/{id}/settle',        [SettlementController::class, 'create'], $auth);
$router->post('/settlements',              [SettlementController::class, 'store'], $auth);
$router->post('/settlements/{id}/confirm', [SettlementController::class, 'confirm'], $auth);
$router->post('/settlements/{id}/reject',  [SettlementController::class, 'reject'], $auth);
$router->post('/settlements/{id}/cancel',  [SettlementController::class, 'cancel'], $auth);

// Budgets
$router->get('/budgets',              [BudgetController::class, 'index'], $auth);
$router->get('/budgets/create',       [BudgetController::class, 'create'], $auth);
$router->post('/budgets',             [BudgetController::class, 'store'], $auth);
$router->get('/budgets/{id}/edit',    [BudgetController::class, 'edit'], $auth);
$router->post('/budgets/{id}',        [BudgetController::class, 'update'], $auth);
$router->post('/budgets/{id}/delete', [BudgetController::class, 'delete'], $auth);

// Notifications
$router->get('/notifications',             [NotificationController::class, 'index'], $auth);
$router->post('/notifications/{id}/open',  [NotificationController::class, 'open'], $auth);
$router->post('/notifications/read-all',   [NotificationController::class, 'readAll'], $auth);

// Analytics (+ JSON for Chart.js)
$router->get('/analytics',                  [AnalyticsController::class, 'index'], $auth);
$router->get('/api/analytics/categories',   [AnalyticsController::class, 'categories'], $auth);
$router->get('/api/analytics/monthly',      [AnalyticsController::class, 'monthly'], $auth);
$router->get('/api/analytics/groups',       [AnalyticsController::class, 'groups'], $auth);
$router->get('/api/analytics/paid-vs-owed', [AnalyticsController::class, 'paidVsOwed'], $auth);
$router->get('/api/analytics/settlements',  [AnalyticsController::class, 'settlements'], $auth);

// Administration
$router->get('/admin',                   [AdminController::class, 'index'], $admin);
$router->get('/admin/users',             [AdminController::class, 'users'], $admin);
$router->post('/admin/users/{id}/toggle',[AdminController::class, 'toggleUser'], $admin);
$router->get('/admin/groups',            [AdminController::class, 'groups'], $admin);
$router->get('/admin/activity',          [AdminController::class, 'activity'], $admin);
