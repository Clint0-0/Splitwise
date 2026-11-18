<?php
/** All SQL of the lab app. Every user value goes through a prepared statement. */
declare(strict_types=1);

final class CandidateRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** INSERT candidate + first application in one transaction. Returns the new id. */
    public function create(array $c, array $a): int
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO candidates (full_name, email, phone, date_of_birth, highest_qualification)
                                VALUES (?, ?, ?, ?, ?)')
                ->execute([$c['full_name'], $c['email'], $c['phone'], $c['date_of_birth'], $c['highest_qualification']]);
            $id = (int) $this->db->lastInsertId();
            $this->addApplication($id, $a);
            $this->db->commit();
            return $id;
        } catch (Throwable $t) {
            $this->db->rollBack();
            throw $t;
        }
    }

    /** SELECT list with search (name/email/phone) and filters. */
    public function search(string $q, int $positionId, string $status): array
    {
        $where = [];
        $args = [];
        if ($q !== '') {
            $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, $like);
        }
        if ($positionId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM applications f WHERE f.candidate_id = c.candidate_id AND f.position_id = ?)';
            $args[] = $positionId;
        }
        if ($status !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM applications f WHERE f.candidate_id = c.candidate_id AND f.status = ?)';
            $args[] = $status;
        }
        $sql = 'SELECT c.candidate_id, c.full_name, c.email, c.phone, c.highest_qualification,
                       COUNT(a.application_id) AS applications,
                       (SELECT CONCAT(p.title, \' · \', a2.status) FROM applications a2 JOIN positions p ON p.position_id = a2.position_id
                         WHERE a2.candidate_id = c.candidate_id ORDER BY a2.applied_on DESC, a2.application_id DESC LIMIT 1) AS latest
                  FROM candidates c LEFT JOIN applications a ON a.candidate_id = c.candidate_id'
              . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
              . ' GROUP BY c.candidate_id ORDER BY c.full_name, c.candidate_id';
        $st = $this->db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM candidates WHERE candidate_id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function applications(int $candidateId): array
    {
        $st = $this->db->prepare('SELECT a.application_id, a.position_id, p.title, p.department, a.applied_on, a.status
                                    FROM applications a JOIN positions p ON p.position_id = a.position_id
                                   WHERE a.candidate_id = ? ORDER BY a.applied_on DESC, a.application_id DESC');
        $st->execute([$candidateId]);
        return $st->fetchAll();
    }

    public function update(int $id, array $c): bool
    {
        $st = $this->db->prepare('UPDATE candidates SET full_name = ?, email = ?, phone = ?, date_of_birth = ?, highest_qualification = ?
                                   WHERE candidate_id = ?');
        $st->execute([$c['full_name'], $c['email'], $c['phone'], $c['date_of_birth'], $c['highest_qualification'], $id]);
        return $st->rowCount() > 0 || $this->find($id) !== null;
    }

    /** DELETE candidate; applications removed by ON DELETE CASCADE. */
    public function delete(int $id): bool
    {
        $st = $this->db->prepare('DELETE FROM candidates WHERE candidate_id = ?');
        $st->execute([$id]);
        return $st->rowCount() === 1;
    }

    public function addApplication(int $candidateId, array $a): void
    {
        $this->db->prepare('INSERT INTO applications (candidate_id, position_id, applied_on, status) VALUES (?, ?, ?, ?)')
            ->execute([$candidateId, $a['position_id'], $a['applied_on'], $a['status']]);
    }

    public function setStatus(int $applicationId, int $candidateId, string $status): bool
    {
        $st = $this->db->prepare('UPDATE applications SET status = ? WHERE application_id = ? AND candidate_id = ?');
        $st->execute([$status, $applicationId, $candidateId]);
        return $st->rowCount() > 0;
    }

    public function application(int $applicationId, int $candidateId): ?array
    {
        $st = $this->db->prepare('SELECT a.*, p.title FROM applications a JOIN positions p ON p.position_id = a.position_id
                                   WHERE a.application_id = ? AND a.candidate_id = ?');
        $st->execute([$applicationId, $candidateId]);
        return $st->fetch() ?: null;
    }

    public function deleteApplication(int $applicationId, int $candidateId): bool
    {
        $st = $this->db->prepare('DELETE FROM applications WHERE application_id = ? AND candidate_id = ?');
        $st->execute([$applicationId, $candidateId]);
        return $st->rowCount() === 1;
    }

    public function positions(bool $openOnly = false): array
    {
        return $this->db->query('SELECT position_id, title, department, is_open FROM positions'
            . ($openOnly ? ' WHERE is_open = 1' : '') . ' ORDER BY title')->fetchAll();
    }

    /** Map a MySQL duplicate-key error (1062) to the form field it concerns. */
    public static function duplicateField(PDOException $e): ?array
    {
        if (($e->errorInfo[1] ?? 0) !== 1062) {
            return null;
        }
        $m = $e->getMessage();
        return match (true) {
            str_contains($m, 'uq_candidates_email') => ['email', 'Another candidate already uses this email.'],
            str_contains($m, 'uq_candidates_phone') => ['phone', 'Another candidate already uses this phone number.'],
            str_contains($m, 'uq_applications_candidate_position') => ['position_id', 'This candidate has already applied for that position.'],
            default => ['general', 'This record already exists.'],
        };
    }
}
