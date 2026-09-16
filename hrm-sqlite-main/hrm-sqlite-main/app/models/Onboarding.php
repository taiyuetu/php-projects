<?php
class Onboarding extends Model
{
    protected string $table = 'onboarding';

    public static array $defaultTasks = [
        '身份与材料核验'            => '核对身份证件、工作许可及学历证书。',
        '合同与制度签署'            => '签署劳动合同、保密协议（NDA）及员工手册确认书。',
        'IT与设备配置'             => '发配电脑、配置企业邮箱并开通相关工具权限。',
        '团队介绍与入职引导'         => '介绍给团队负责人及同事，带其熟悉办公环境。',
        '薪资与福利设置'            => '收集银行卡信息，配置税务信息与医疗福利。',
        '合规与安全培训'            => '完成必须的职场安全与信息安全培训。',
    ];

    public function allWithDetails(): array
    {
        $sql = "SELECT o.*, e.employee_code, e.first_name, e.last_name, e.email, e.designation, d.name as department_name,
                       (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id) as total_tasks,
                       (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id AND is_completed = 1) as completed_tasks
                FROM onboarding o
                INNER JOIN employees e ON o.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                ORDER BY CASE o.status WHEN 'In Progress' THEN 1 WHEN 'Pending' THEN 2 ELSE 3 END, o.id DESC";
        return $this->query($sql)->fetchAll();
    }

    public function findWithTasks(int $id): ?array
    {
        $sql = "SELECT o.*, e.employee_code, e.first_name, e.last_name, e.email, e.phone, e.hire_date, e.designation, d.name as department_name,
                       (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id) as total_tasks,
                       (SELECT COUNT(*) FROM onboarding_tasks WHERE onboarding_id = o.id AND is_completed = 1) as completed_tasks
                FROM onboarding o
                INNER JOIN employees e ON o.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE o.id = :id LIMIT 1";
        $row = $this->query($sql, ['id' => $id])->fetch();
        if (!$row) {
            return null;
        }

        $taskStmt = $this->db->prepare("SELECT * FROM onboarding_tasks WHERE onboarding_id = :oid ORDER BY id ASC");
        $taskStmt->execute(['oid' => $id]);
        $row['tasks'] = $taskStmt->fetchAll();

        return $row;
    }

    public function findByEmployeeId(int $employeeId)
    {
        $stmt = $this->db->prepare("SELECT * FROM onboarding WHERE employee_id = :eid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['eid' => $employeeId]);
        return $stmt->fetch();
    }

    public function createForEmployee(int $employeeId, string $startDate, ?string $targetDate = null, string $notes = ''): int
    {
        $onboardingId = (int) $this->insert([
            'employee_id'            => $employeeId,
            'start_date'             => $startDate,
            'target_completion_date' => $targetDate ?: null,
            'status'                 => 'In Progress',
            'notes'                  => trim($notes),
        ]);

        $taskStmt = $this->db->prepare("INSERT INTO onboarding_tasks (onboarding_id, task_name, description, is_completed) VALUES (:oid, :name, :desc, 0)");
        foreach (self::$defaultTasks as $name => $desc) {
            $taskStmt->execute([
                'oid'  => $onboardingId,
                'name' => $name,
                'desc' => $desc,
            ]);
        }

        return $onboardingId;
    }

    public function addTask(int $onboardingId, string $name, string $desc = ''): int
    {
        $stmt = $this->db->prepare("INSERT INTO onboarding_tasks (onboarding_id, task_name, description, is_completed) VALUES (:oid, :name, :desc, 0)");
        $stmt->execute([
            'oid'  => $onboardingId,
            'name' => trim($name),
            'desc' => trim($desc),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function toggleTask(int $taskId, int $isCompleted): bool
    {
        $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare("UPDATE onboarding_tasks SET is_completed = :c, completed_at = :cat WHERE id = :id");
        return $stmt->execute([
            'c'   => $isCompleted ? 1 : 0,
            'cat' => $completedAt,
            'id'  => $taskId,
        ]);
    }

    public function getTask(int $taskId)
    {
        $stmt = $this->db->prepare("SELECT * FROM onboarding_tasks WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $taskId]);
        return $stmt->fetch();
    }

    public function completeOnboarding(int $id, ?string $notes = null): bool
    {
        $data = [
            'status'       => 'Completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if ($notes !== null) {
            $data['notes'] = $notes;
        }
        return (bool) $this->update($id, $data);
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }
}
