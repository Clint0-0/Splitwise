<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Helpers\Validator;
use App\Models\SettlementModel;

/**
 * Settlements are written ONLY through sp_record_settlement / sp_respond_settlement,
 * which validate outstanding amounts under a row lock and enforce idempotency.
 * This service validates the form and turns SIGNAL messages into form errors.
 */
final class SettlementService
{
    public const METHODS = ['cash', 'upi', 'bank_transfer', 'other'];

    public function __construct(private SettlementModel $settlements = new SettlementModel())
    {
    }

    public function record(int $actor, array $in): int
    {
        $groupId = (int) ($in['group_id'] ?? 0);
        // Archived groups are read-only for expenses, but debts can still be settled.
        (new GroupService())->requireMember($groupId, $actor);

        $payer  = (int) ($in['payer_id'] ?? 0);
        $payee  = (int) ($in['payee_id'] ?? 0);
        $date   = trim((string) ($in['settlement_date'] ?? ''));
        $method = (string) ($in['method'] ?? 'cash');
        $note   = trim((string) ($in['note'] ?? ''));
        $token  = (string) ($in['request_token'] ?? '');

        $v = (new Validator())
            ->required('settlement_date', $date, 'Date')->date('settlement_date', $date, 'Date', true)
            ->in('method', $method, self::METHODS, 'payment method')
            ->maxLen('note', $note, 255, 'Note')
            ->regex('request_token', $token, '/^[0-9a-f\-]{36}$/', 'Invalid form token. Reload the page.');
        if ($token === '') {
            $v->add('request_token', 'Invalid form token. Reload the page.');
        }
        if ($payer === $payee) {
            $v->add('payee_id', 'Payer and receiver must be different people.');
        }
        if (!in_array($actor, [$payer, $payee], true)) {
            $v->add('payer_id', 'You can only record payments you made or received.');
        }
        $amount = $v->try(fn() => Money::parse((string) ($in['amount'] ?? ''), 'amount'));
        $v->throw();

        try {
            return $this->settlements->record($groupId, $payer, $payee, Money::fromPaise($amount), $date,
                $method, $note !== '' ? $note : null, $actor, $token);
        } catch (\PDOException $e) {
            $msg = Database::signalMessage($e);
            if ($msg !== null) {
                throw ValidationException::single('amount', $msg);
            }
            throw $e;
        }
    }

    public function respond(int $actor, int $settlementId, string $action): array
    {
        $s = $this->settlements->find($settlementId) ?? throw new HttpException(404);
        if (!in_array($actor, [(int) $s['payer_id'], (int) $s['payee_id']], true)) {
            throw new HttpException(403);
        }
        try {
            $this->settlements->respond($settlementId, $actor, $action);
        } catch (\PDOException $e) {
            $msg = Database::signalMessage($e);
            if ($msg !== null) {
                throw ValidationException::single('settlement', $msg);
            }
            throw $e;
        }
        return $s;
    }
}
