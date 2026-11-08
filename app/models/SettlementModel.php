<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Settlements are READ here; all writes go through stored procedures
 * (the app's DB account has no INSERT/UPDATE on this table).
 */
final class SettlementModel extends Model
{
    public function find(int $id): ?array
    {
        return $this->one('SELECT d.*, s.created_by, s.responded_at, s.created_at
                             FROM v_settlement_details d JOIN settlements s USING (settlement_id)
                            WHERE d.settlement_id = ?', [$id]);
    }

    public function forGroup(int $groupId, int $limit = 50): array
    {
        return $this->all('SELECT d.*, s.created_by FROM v_settlement_details d JOIN settlements s USING (settlement_id)
                            WHERE d.group_id = ? ORDER BY d.settlement_date DESC, d.settlement_id DESC LIMIT ?',
            [$groupId, $limit]);
    }

    public function forUser(int $userId, ?string $status, int $limit, int $offset): array
    {
        $sql = 'SELECT d.*, s.created_by FROM v_settlement_details d JOIN settlements s USING (settlement_id)
                 WHERE (d.payer_id = ? OR d.payee_id = ?)';
        $p = [$userId, $userId];
        if ($status !== null) {
            $sql .= ' AND d.status = ?';
            $p[] = $status;
        }
        $sql .= ' ORDER BY d.settlement_date DESC, d.settlement_id DESC LIMIT ? OFFSET ?';
        array_push($p, $limit, $offset);
        return $this->all($sql, $p);
    }

    public function countForUser(int $userId, ?string $status): int
    {
        $sql = 'SELECT COUNT(*) FROM settlements WHERE (payer_id = ? OR payee_id = ?)';
        $p = [$userId, $userId];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $p[] = $status;
        }
        return (int) $this->value($sql, $p);
    }

    /** Pending settlements this user must confirm (they are the payee). */
    public function awaitingConfirmation(int $userId): array
    {
        return $this->all("SELECT * FROM v_settlement_details WHERE payee_id = ? AND status = 'pending'
                            ORDER BY settlement_date", [$userId]);
    }

    /** CALL sp_record_settlement; returns the settlement id. */
    public function record(int $groupId, int $payer, int $payee, string $amount, string $date,
                           string $method, ?string $note, int $createdBy, string $token): int
    {
        $st = $this->db->prepare('CALL sp_record_settlement(?, ?, ?, ?, ?, ?, ?, ?, ?, @sid)');
        $st->execute([$groupId, $payer, $payee, $amount, $date, $method, $note, $createdBy, $token]);
        $st->closeCursor();
        return (int) $this->value('SELECT @sid');
    }

    public function respond(int $id, int $userId, string $action): void
    {
        $st = $this->db->prepare('CALL sp_respond_settlement(?, ?, ?)');
        $st->execute([$id, $userId, $action]);
        $st->closeCursor();
    }
}
