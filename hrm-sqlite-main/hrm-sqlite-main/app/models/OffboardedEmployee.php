<?php
class OffboardedEmployee extends Model
{
    protected string $table = 'offboarding_employees';

    public function allWithDetails(): array
    {
        $sql = "SELECT oe.*, u.username as offboarded_by_username
                FROM offboarding_employees oe
                LEFT JOIN users u ON oe.offboarded_by = u.id
                ORDER BY oe.id DESC";
        return $this->query($sql)->fetchAll();
    }

    public function findWithDetails(int $id): ?array
    {
        $sql = "SELECT oe.*, u.username as offboarded_by_username
                FROM offboarding_employees oe
                LEFT JOIN users u ON oe.offboarded_by = u.id
                WHERE oe.id = :id LIMIT 1";
        $row = $this->query($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public function search(string $keyword): array
    {
        $sql = "SELECT oe.*, u.username as offboarded_by_username
                FROM offboarding_employees oe
                LEFT JOIN users u ON oe.offboarded_by = u.id
                WHERE oe.first_name LIKE :kw OR oe.last_name LIKE :kw
                   OR oe.email LIKE :kw OR oe.employee_code LIKE :kw
                   OR oe.department_name LIKE :kw OR oe.designation LIKE :kw
                   OR oe.reason LIKE :kw
                ORDER BY oe.id DESC";
        return $this->query($sql, ['kw' => "%{$keyword}%"])->fetchAll();
    }
}
