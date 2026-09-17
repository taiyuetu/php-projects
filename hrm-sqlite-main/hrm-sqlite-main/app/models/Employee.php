<?php
class Employee extends Model
{
    protected string $table = 'employees';

    /** All employees with their department name joined in (excluding terminated) */
    public function allWithDepartment()
    {
        $sql = "SELECT e.*, d.name as department_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.status != 'Terminated'
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
                WHERE e.status != 'Terminated'
                  AND (e.first_name LIKE :kw OR e.last_name LIKE :kw
                   OR e.email LIKE :kw OR e.employee_code LIKE :kw)
                ORDER BY e.id DESC";
        return $this->query($sql, ['kw' => "%{$keyword}%"])->fetchAll();
    }

    public function generateEmployeeCode(): string
    {
        $stmt = $this->db->query("SELECT MAX(id) as max_id FROM employees");
        $next = (int) ($stmt->fetch()['max_id'] ?? 0) + 1;
        $code = 'EMP-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        while ($this->findOneBy(['employee_code' => $code])) {
            $next++;
            $code = 'EMP-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    public function countActive(): int
    {
        return $this->count(['status' => 'Active']);
    }

    /** Calculate age from date of birth */
    public function calculateAge(?string $dob): ?int
    {
        if (empty($dob)) {
            return null;
        }
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $birthDate->diff($today)->y;
        return $age > 0 ? $age : null;
    }

    /** Find employee with age calculated */
    public function findWithAge($id)
    {
        $employee = $this->findWithDepartment($id);
        if ($employee) {
            $employee['age'] = $this->calculateAge($employee['dob'] ?? null);
        }
        return $employee;
    }
}
