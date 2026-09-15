<?php
class Department extends Model
{
    protected string $table = 'departments';

    /** Departments with a live count of employees in each */
    public function allWithEmployeeCount()
    {
        $sql = "SELECT d.*, COUNT(e.id) as employee_count
                FROM departments d
                LEFT JOIN employees e ON e.department_id = d.id
                GROUP BY d.id
                ORDER BY d.name ASC";
        return $this->query($sql)->fetchAll();
    }
}
