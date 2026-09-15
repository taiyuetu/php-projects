<?php
class JobOpening extends Model
{
    protected string $table = 'job_openings';

    public function allWithDepartmentAndCount(?string $status = null): array
    {
        $sql = "SELECT j.*, d.name as department_name,
                       (SELECT COUNT(*) FROM candidates WHERE job_id = j.id) as total_candidates,
                       (SELECT COUNT(*) FROM candidates WHERE job_id = j.id AND stage != 'Rejected') as active_candidates,
                       (SELECT COUNT(*) FROM candidates WHERE job_id = j.id AND stage = 'Hired') as hired_count
                FROM job_openings j
                LEFT JOIN departments d ON j.department_id = d.id";
        
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= " WHERE j.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY CASE j.status WHEN 'Open' THEN 1 WHEN 'Paused' THEN 2 ELSE 3 END, j.id DESC";
        return $this->query($sql, $params)->fetchAll();
    }

    public function findWithDetails(int $id): ?array
    {
        $sql = "SELECT j.*, d.name as department_name,
                       (SELECT COUNT(*) FROM candidates WHERE job_id = j.id) as total_candidates,
                       (SELECT COUNT(*) FROM candidates WHERE job_id = j.id AND stage = 'Hired') as hired_count
                FROM job_openings j
                LEFT JOIN departments d ON j.department_id = d.id
                WHERE j.id = :id LIMIT 1";
        $row = $this->query($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public function openJobs(): array
    {
        $sql = "SELECT j.*, d.name as department_name
                FROM job_openings j
                LEFT JOIN departments d ON j.department_id = d.id
                WHERE j.status = 'Open'
                ORDER BY j.title ASC";
        return $this->query($sql)->fetchAll();
    }

    public function countOpen(): int
    {
        return $this->count(['status' => 'Open']);
    }
}
