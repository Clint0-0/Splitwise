<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;

/**
 * UPI "deep link" for paying a friend (Phase 6).
 *
 * upi://pay?pa=<UPI ID>&pn=<name>&am=<amount>&cu=INR&tn=<note> opens the payer's UPI
 * app (GPay, PhonePe, Paytm, BHIM…) with the amount filled in. The money moves
 * between the two bank accounts only; SplitWise+ never holds, receives or sees it,
 * and it cannot know whether the payment went through. So a UPI payment is recorded
 * like any other: the payer records it, the receiver confirms it
 * (sp_record_settlement / sp_respond_settlement).
 */
final class UpiLink
{
    /** Same rule as the users.upi_id CHECK constraint (stored lower-case). */
    public const ID_PATTERN = '/^[a-z0-9._-]{2,64}@[a-z][a-z0-9]{1,63}$/';

    public static function valid(?string $upiId): bool
    {
        return $upiId !== null && preg_match(self::ID_PATTERN, $upiId) === 1;
    }

    /**
     * @param int $amountPaise > 0
     * @return string|null null when the UPI ID is missing/invalid or the amount is not positive
     */
    public static function build(?string $upiId, string $payeeName, int $amountPaise, string $note): ?string
    {
        if (!self::valid($upiId) || $amountPaise <= 0) {
            return null;
        }
        // keep the note short and plain: some UPI apps reject long or unusual remarks
        $note = mb_substr(trim((string) preg_replace(['/[^\p{L}\p{N} .,\-]+/u', '/\s+/u'], ' ', $note)), 0, 50);
        $q = [
            'pa' => $upiId,
            'pn' => mb_substr(trim($payeeName), 0, 50),
            'am' => Money::fromPaise($amountPaise),
            'cu' => 'INR',
            'tn' => $note,
        ];
        $parts = [];
        foreach ($q as $k => $v) {
            if ($v !== '') {
                // '@' stays readable in pa (every UPI app accepts it); everything else is encoded
                $parts[] = $k . '=' . str_replace('%40', '@', rawurlencode($v));
            }
        }
        return 'upi://pay?' . implode('&', $parts);
    }
}
