<?php
class Employee extends Model
{
    protected string $table = 'employees';

    /** All employees with their department name joined in */
    public function allWithDepartment()
    {
        $sql = "SELECT e.*, d.name as department_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                ORDER BY e.id DESC";
        return $this->query($sql)->fetchAll();
    }

    public function findWithDepartment($id)
    {
        $sql = "SELECT e.*, d.name as department_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.id = :id LIMIT 1";
        return $this->query($sql, ['id' => $id])->fetch();
    }

    public function search(string $keyword)
    {
        $sql = "SELECT e.*, d.name as department_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.first_name LIKE :kw OR e.last_name LIKE :kw
                   OR e.email LIKE :kw OR e.employee_code LIKE :kw
                ORDER BY e.id DESC";
        return $this->query($sql, ['kw' => "%{$keyword}%"])->fetchAll();
    }

    public function generateEmployeeCode(): string
    {
        $stmt = $this->db->query("SELECT COUNT(*) as cnt FROM employees");
        $next = (int) $stmt->fetch()['cnt'] + 1;
        return 'EMP-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function countActive(): int
    {
        return $this->count(['status' => 'Active']);
    }
}
