<?php
class LeaveRequest extends Model
{
    protected string $table = 'leave_requests';

    public function allWithEmployee()
    {
        $sql = "SELECT l.*, e.first_name, e.last_name, e.employee_code
                FROM leave_requests l
                JOIN employees e ON l.employee_id = e.id
                ORDER BY l.applied_at DESC";
        return $this->query($sql)->fetchAll();
    }

    public function forEmployee(int $employeeId)
    {
        return $this->where(['employee_id' => $employeeId], 'applied_at DESC');
    }

    public function setStatus(int $id, string $status, int $approvedBy = null)
    {
        return $this->update($id, [
            'status'      => $status,
            'approved_by' => $approvedBy,
        ]);
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }
}
