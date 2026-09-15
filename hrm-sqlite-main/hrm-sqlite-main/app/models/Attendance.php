<?php
class Attendance extends Model
{
    protected string $table = 'attendance';

    public function allWithEmployee(string $date = null)
    {
        $sql = "SELECT a.*, e.first_name, e.last_name, e.employee_code
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id";
        $params = [];
        if ($date) {
            $sql .= " WHERE a.attendance_date = :date";
            $params['date'] = $date;
        }
        $sql .= " ORDER BY a.attendance_date DESC, e.first_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }

    public function findForEmployeeAndDate(int $employeeId, string $date)
    {
        return $this->findOneBy(['employee_id' => $employeeId, 'attendance_date' => $date]);
    }

    public function checkIn(int $employeeId, string $date, string $time)
    {
        $existing = $this->findForEmployeeAndDate($employeeId, $date);
        if ($existing) {
            return $this->update($existing['id'], ['check_in' => $time, 'status' => 'Present']);
        }
        return $this->insert([
            'employee_id'     => $employeeId,
            'attendance_date' => $date,
            'check_in'        => $time,
            'status'          => 'Present',
        ]);
    }

    public function checkOut(int $employeeId, string $date, string $time)
    {
        $existing = $this->findForEmployeeAndDate($employeeId, $date);
        if ($existing) {
            return $this->update($existing['id'], ['check_out' => $time]);
        }
        return false;
    }

    public function historyForEmployee(int $employeeId)
    {
        return $this->where(['employee_id' => $employeeId], 'attendance_date DESC');
    }
}
