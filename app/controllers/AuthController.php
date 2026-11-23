<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ValidationException;
use App\Services\AuthService;
use App\Services\PasswordResetService;

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
            (new AuthService())->login($this->request->str('login'), (is_string($p = $this->request->post('password', '')) ? $p : ''),
                client_ip());
        } catch (ValidationException $e) {
            $this->back('/login', $e);
        }
        $this->redirect('/dashboard');
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot', ['title' => 'Forgot password'], 'layouts/guest');
    }

    public function forgot(): void
    {
        try {
            (new PasswordResetService())->request($this->request->str('email'), client_ip());
        } catch (ValidationException $e) {
            $this->back('/forgot-password', $e);
        }
        \App\Core\Session::flash('success', 'If an account uses that e-mail, we have sent it a link to reset the password. The link works for 1 hour.');
        $this->redirect('/login');
    }

    public function showReset(): void
    {
        $token = $this->request->queryStr('token');
        $valid = (new PasswordResetService())->check($token) !== null;
        header('Referrer-Policy: no-referrer');   // keep the token out of Referer headers
        $this->view('auth/reset', ['title' => 'Choose a new password', 'token' => $valid ? $token : '', 'valid' => $valid], 'layouts/guest');
    }

    public function reset(): void
    {
        $token = $this->request->str('token');
        try {
            $pw = $this->request->post('password', '');
            $pc = $this->request->post('password_confirm', '');
            (new PasswordResetService())->reset($token, is_string($pw) ? $pw : '', is_string($pc) ? $pc : '');   // untrimmed, like login
        } catch (ValidationException $e) {
            if (isset($e->errors['token'])) {
                \App\Core\Session::flash('danger', $e->errors['token']);
                $this->redirect('/forgot-password');
            }
            \App\Core\Session::flashInput([], $e->errors);
            $this->redirect('/reset-password?token=' . rawurlencode($token));
        }
        \App\Core\Session::flash('success', 'Password changed. You have been logged out everywhere; log in with the new password.');
        $this->redirect('/login');
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
