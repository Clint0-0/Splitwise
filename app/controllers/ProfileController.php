<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ValidationException;
use App\Services\AuthService;

final class ProfileController extends Controller
{
    public function show(): void
    {
        $this->view('profile/show', ['title' => 'Profile', 'user' => Auth::user()]);
    }

    public function update(): void
    {
        try {
            (new AuthService())->updateProfile(Auth::requireId(), $this->request->allPost());
        } catch (ValidationException $e) {
            $this->back('/profile', $e);
        }
        $this->success('Profile updated.');
        $this->redirect('/profile');
    }

    public function password(): void
    {
        try {
            (new AuthService())->changePassword(Auth::requireId(), $this->request->allPost());
        } catch (ValidationException $e) {
            $this->back('/profile', $e);
        }
        $this->success('Password changed.');
        $this->redirect('/profile');
    }
}
