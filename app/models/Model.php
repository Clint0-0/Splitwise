<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Base repository. All SQL uses prepared statements with bound parameters —
 * user input is never concatenated into SQL.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    protected function one(string $sql, array $params = []): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    protected function all(string $sql, array $params = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    protected function value(string $sql, array $params = []): mixed
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    protected function run(string $sql, array $params = []): int
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    protected function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->db->lastInsertId();
    }
}
