<?php
class Payroll extends Model
{
    protected string $table = 'payroll';

    public function allWithEmployee(int $month = null, int $year = null)
    {
        $sql = "SELECT p.*, e.first_name, e.last_name, e.employee_code
                FROM payroll p
                JOIN employees e ON p.employee_id = e.id";
        $params = [];
        $clauses = [];
        if ($month) { $clauses[] = 'p.month = :month'; $params['month'] = $month; }
        if ($year)  { $clauses[] = 'p.year = :year';   $params['year'] = $year; }
        if ($clauses) $sql .= ' WHERE ' . implode(' AND ', $clauses);
        $sql .= ' ORDER BY p.year DESC, p.month DESC, e.first_name ASC';
        return $this->query($sql, $params)->fetchAll();
    }

    public function existsFor(int $employeeId, int $month, int $year)
    {
        return $this->findOneBy(['employee_id' => $employeeId, 'month' => $month, 'year' => $year]);
    }

    public function generateForEmployee(array $employee, int $month, int $year, float $allowances = 0, float $deductions = 0)
    {
        $basic = (float) $employee['salary'];
        $net = $basic + $allowances - $deductions;

        $existing = $this->existsFor($employee['id'], $month, $year);
        $data = [
            'employee_id' => $employee['id'],
            'month'       => $month,
            'year'        => $year,
            'basic_salary'=> $basic,
            'allowances'  => $allowances,
            'deductions'  => $deductions,
            'net_salary'  => $net,
        ];
        if ($existing) {
            return $this->update($existing['id'], $data);
        }
        return $this->insert($data);
    }

    public function markPaid(int $id)
    {
        return $this->update($id, ['status' => 'Paid']);
    }
}
