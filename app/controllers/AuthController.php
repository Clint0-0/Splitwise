<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ValidationException;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function home(): void
    {
        $this->redirect(Auth::user() ? '/dashboard' : '/login');
    }

    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Log in'], 'layouts/guest');
    }

    public function login(): void
    {
        try {
            (new AuthService())->login($this->request->str('login'), (string) $this->request->post('password', ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        } catch (ValidationException $e) {
            $this->back('/login', $e);
        }
        $this->redirect('/dashboard');
    }

    public function showRegister(): void
    {
        $this->view('auth/register', ['title' => 'Create account'], 'layouts/guest');
    }

    public function register(): void
    {
        try {
            (new AuthService())->register($this->request->allPost());
        } catch (ValidationException $e) {
            $this->back('/register', $e);
        }
        $this->success('Welcome to SplitWise+! Create a group to start sharing expenses.');
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
