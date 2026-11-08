<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo View::render($template, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        throw new RedirectException(url($path));
    }

    /** Go back to the form with errors + old input. */
    protected function back(string $path, ValidationException $e): never
    {
        Session::flashInput($this->request->allPost(), $e->errors);
        Session::flash('danger', 'Please fix the highlighted problems.');
        $this->redirect($path);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function success(string $message): void
    {
        Session::flash('success', $message);
    }
}
