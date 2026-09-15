<?php
class Candidate extends Model
{
    protected string $table = 'candidates';

    public static array $stages = [
        'Applied'   => ['label' => 'Applied',   'badge' => 'secondary', 'icon' => 'bi-inbox'],
        'Screening' => ['label' => 'Screening', 'badge' => 'info',      'icon' => 'bi-search'],
        'Interview' => ['label' => 'Interview', 'badge' => 'primary',   'icon' => 'bi-camera-video'],
        'Offered'   => ['label' => 'Offered',   'badge' => 'warning',   'icon' => 'bi-gift'],
        'Hired'     => ['label' => 'Hired',     'badge' => 'success',   'icon' => 'bi-check2-circle'],
        'Rejected'  => ['label' => 'Rejected',  'badge' => 'danger',    'icon' => 'bi-x-circle'],
    ];

    public function allWithDetails(array $filters = []): array
    {
        $sql = "SELECT c.*, j.title as job_title, j.location as job_location, d.name as department_name,
                       e.employee_code as hired_employee_code
                FROM candidates c
                INNER JOIN job_openings j ON c.job_id = j.id
                LEFT JOIN departments d ON j.department_id = d.id
                LEFT JOIN employees e ON c.hired_employee_id = e.id
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['job_id'])) {
            $sql .= " AND c.job_id = :job_id";
            $params['job_id'] = (int) $filters['job_id'];
        }

        if (!empty($filters['stage'])) {
            $sql .= " AND c.stage = :stage";
            $params['stage'] = $filters['stage'];
        }

        if (!empty($filters['keyword'])) {
            $sql .= " AND (c.first_name LIKE :kw OR c.last_name LIKE :kw OR c.email LIKE :kw OR c.phone LIKE :kw OR c.current_company LIKE :kw)";
            $params['kw'] = "%{$filters['keyword']}%";
        }

        $sql .= " ORDER BY c.id DESC";
        return $this->query($sql, $params)->fetchAll();
    }

    public function findWithJob(int $id): ?array
    {
        $sql = "SELECT c.*, j.title as job_title, j.employment_type, j.location as job_location, j.salary_range,
                       d.name as department_name, d.id as department_id,
                       e.employee_code as hired_employee_code
                FROM candidates c
                INNER JOIN job_openings j ON c.job_id = j.id
                LEFT JOIN departments d ON j.department_id = d.id
                LEFT JOIN employees e ON c.hired_employee_id = e.id
                WHERE c.id = :id LIMIT 1";
        $row = $this->query($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public function updateStage(int $id, string $stage): bool
    {
        if (!array_key_exists($stage, self::$stages)) {
            return false;
        }
        return (bool) $this->update($id, [
            'stage'      => $stage,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateRating(int $id, int $rating): bool
    {
        $rating = max(0, min(5, $rating));
        return (bool) $this->update($id, [
            'rating'     => $rating,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markHired(int $id, int $employeeId): bool
    {
        return (bool) $this->update($id, [
            'stage'             => 'Hired',
            'hired_employee_id' => $employeeId,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function countByStage(?int $jobId = null): array
    {
        $counts = array_fill_keys(array_keys(self::$stages), 0);
        $sql = "SELECT stage, COUNT(*) as cnt FROM candidates";
        $params = [];
        if ($jobId) {
            $sql .= " WHERE job_id = :jid";
            $params['jid'] = $jobId;
        }
        $sql .= " GROUP BY stage";

        $rows = $this->query($sql, $params)->fetchAll();
        foreach ($rows as $r) {
            if (isset($counts[$r['stage']])) {
                $counts[$r['stage']] = (int) $r['cnt'];
            }
        }
        return $counts;
    }

    public function countActive(): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM candidates WHERE stage IN ('Applied', 'Screening', 'Interview', 'Offered')";
        return (int) $this->query($sql)->fetch()['cnt'];
    }
}
