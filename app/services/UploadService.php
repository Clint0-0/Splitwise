<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\ValidationException;

/**
 * Receipt uploads. The MIME type is detected from the file CONTENT (finfo),
 * never trusted from the browser. Files get random names and live in
 * storage/receipts/ (outside public/), served only through an authorised route.
 */
final class UploadService
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    /** @return array{path:string, original:string, mime:string, size:int} */
    public function storeReceipt(array $file): array
    {
        $max = self::maxBytes();
        $limit = self::human($max);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $msg = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? "Receipt is too large (maximum $limit)." : 'Receipt upload failed.';
            throw ValidationException::single('receipt', $msg);
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > $max) {
            throw ValidationException::single('receipt', "Receipt must be between 1 byte and $limit.");
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw ValidationException::single('receipt', 'Receipt upload failed.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw ValidationException::single('receipt', 'Receipt must be a JPG, PNG or PDF file.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $dest = self::dir() . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw ValidationException::single('receipt', 'Could not save the receipt.');
        }
        $original = mb_substr(basename(str_replace('\\', '/', (string) $file['name'])), 0, 255);
        return ['path' => $name, 'original' => $original, 'mime' => $mime, 'size' => $size];
    }

    /** Effective limit: the app's 5 MB rule, but never more than PHP itself accepts. */
    public static function maxBytes(): int
    {
        return min((int) Env::get('UPLOAD_MAX_BYTES', '5242880'),
            ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')));
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB'
            : (int) ceil($bytes / 1024) . ' KB';
    }

    public static function dir(): string
    {
        return BASE_PATH . '/storage/receipts';
    }

    public function discard(string $name): void
    {
        $path = self::dir() . '/' . basename($name);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
