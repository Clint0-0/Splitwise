<?php
declare(strict_types=1);

namespace App\Core;

/** Renders app/views/<name>.php inside a layout. Variables are extracted. */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $data += Session::takeOld();
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content, 'flash' => Session::takeFlash()]);
    }

    private static function capture(string $template, array $data): string
    {
        $file = BASE_PATH . '/app/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
