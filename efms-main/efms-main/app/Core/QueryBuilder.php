<?php

declare(strict_types=1);

namespace App\Core;

/**
 * QueryBuilder
 *
 * Fluent, parameter-bound query builder shared by every Model.
 * Deliberately covers the 90% case (where/orWhere/orderBy/limit/joins)
 * rather than trying to be a full DBAL — for anything exotic, a Model
 * can still drop to Database::getInstance()->query($sql, $params).
 */
final class QueryBuilder
{
    private array $wheres = [];
    private array $bindings = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private string $columns = '*';

    public function __construct(
        private readonly Database $db,
        private readonly string $table,
        private readonly string $modelClass
    ) {
    }

    public function select(string $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column $operator ?"];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = ['type' => 'OR', 'sql' => "$column $operator ?"];
        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            $this->wheres[] = ['type' => 'AND', 'sql' => '1 = 0'];
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column IN ($placeholders)"];
        array_push($this->bindings, ...array_values($values));
        return $this;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): self
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column BETWEEN ? AND ?"];
        $this->bindings[] = $from;
        $this->bindings[] = $to;
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['type' => 'AND', 'sql' => "$column IS NULL"];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = "JOIN $table ON $first $operator $second";
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function paginate(int $page, int $perPage = 25): array
    {
        $page = max(1, $page);
        $total = (clone $this)->count();

        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $items = $this->get();

        return [
            'data'         => $items,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) max(1, ceil($total / $perPage)),
        ];
    }

    private function buildWhereSql(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = '';
        foreach ($this->wheres as $i => $where) {
            $sql .= $i === 0 ? $where['sql'] : " {$where['type']} {$where['sql']}";
        }

        return ' WHERE ' . $sql;
    }

    public function toSql(): string
    {
        $sql = "SELECT {$this->columns} FROM {$this->table}";
        $sql .= $this->joins ? ' ' . implode(' ', $this->joins) : '';
        $sql .= $this->buildWhereSql();
        $sql .= $this->orderBy ? ' ORDER BY ' . implode(', ', $this->orderBy) : '';
        $sql .= $this->limit !== null ? ' LIMIT ' . $this->limit : '';
        $sql .= $this->offset !== null ? ' OFFSET ' . $this->offset : '';

        return $sql;
    }

    public function get(): array
    {
        $stmt = $this->db->query($this->toSql(), $this->bindings);
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as aggregate FROM {$this->table}";
        $sql .= $this->joins ? ' ' . implode(' ', $this->joins) : '';
        $sql .= $this->buildWhereSql();

        $stmt = $this->db->query($sql, $this->bindings);
        return (int) ($stmt->fetch()['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $sql = "SELECT COALESCE(SUM($column), 0) as aggregate FROM {$this->table}";
        $sql .= $this->buildWhereSql();

        $stmt = $this->db->query($sql, $this->bindings);
        return (float) ($stmt->fetch()['aggregate'] ?? 0);
    }
}
