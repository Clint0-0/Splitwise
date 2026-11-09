<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\ValidationException;

/**
 * Receipt uploads. The MIME type is detected from the file CONTENT (finfo),
 * never trusted from the browser. The bytes are stored in MySQL (receipts.content)
 * — not on the server's disk, which is temporary on Vercel — under a random key,
 * and served only through an authorised route (ExpenseController::receipt).
 */
final class UploadService
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    /** @return array{path:string, original:string, mime:string, size:int, content:string} */
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
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || strlen($content) !== $size) {
            throw ValidationException::single('receipt', 'Could not read the receipt.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $original = mb_substr(basename(str_replace('\\', '/', (string) $file['name'])), 0, 255);
        return ['path' => $name, 'original' => $original, 'mime' => $mime, 'size' => $size, 'content' => $content];
    }

    /** Effective limit: the app's 5 MB rule, but never more than PHP itself accepts. */
    public static function maxBytes(): int
    {
        // 5242880 = the receipts.file_size CHECK in the database: env can lower the limit, never raise it
        return min(max(1, (int) Env::get('UPLOAD_MAX_BYTES', '5242880')), 5242880,
            ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')));
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB'
            : (int) ceil($bytes / 1024) . ' KB';
    }

    /** Nothing to clean up: a failed transaction rolls the receipt row back with the expense. */
    public function discard(string $name): void
    {
    }
}
