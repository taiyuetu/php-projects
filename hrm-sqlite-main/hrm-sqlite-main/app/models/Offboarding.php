<?php
class Offboarding extends Model
{
    protected string $table = 'offboarding';

    public static array $defaultTasks = [
        '通知与离职协议核对'          => '确认最后工作日，核对通知期与离职协议条款。',
        '工作与文档交接'            => '将所有进行中的项目、文件和职责交接给指定同事。',
        '公司资产退还'             => '收回笔记本电脑、显示器、门禁卡、钥匙及公司硬件设备。',
        'IT与系统权限撤销'         => '停用邮箱、Slack、VPN、云端控制台及内部软件账号。',
        '离职面谈与反馈'            => '进行离职面谈，收集员工关于工作体验的反馈建议。',
        '最终薪酬与结算清算'         => '计算最终核资、未结年假折算，并出具财务结清证明。',
    ];

    public function allWithDetails(bool $activeOnly = true): array
    {
        $sql = "SELECT o.*, e.employee_code, e.first_name, e.last_name, e.email, e.designation, d.name as department_name,
                       (SELECT COUNT(*) FROM offboarding_tasks WHERE offboarding_id = o.id) as total_tasks,
                       (SELECT COUNT(*) FROM offboarding_tasks WHERE offboarding_id = o.id AND is_completed = 1) as completed_tasks
                FROM offboarding o
                INNER JOIN employees e ON o.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id";

        if ($activeOnly) {
            $sql .= " WHERE o.status != 'Completed'";
        }

        $sql .= " ORDER BY CASE o.status WHEN 'In Progress' THEN 1 WHEN 'Pending' THEN 2 ELSE 3 END, o.id DESC";
        return $this->query($sql)->fetchAll();
    }

    public function findWithTasks(int $id): ?array
    {
        $sql = "SELECT o.*, e.employee_code, e.first_name, e.last_name, e.email, e.phone, e.hire_date, e.designation, e.salary, d.name as department_name,
                       (SELECT COUNT(*) FROM offboarding_tasks WHERE offboarding_id = o.id) as total_tasks,
                       (SELECT COUNT(*) FROM offboarding_tasks WHERE offboarding_id = o.id AND is_completed = 1) as completed_tasks
                FROM offboarding o
                INNER JOIN employees e ON o.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE o.id = :id LIMIT 1";
        $row = $this->query($sql, ['id' => $id])->fetch();
        if (!$row) {
            return null;
        }

        $taskStmt = $this->db->prepare("SELECT * FROM offboarding_tasks WHERE offboarding_id = :oid ORDER BY id ASC");
        $taskStmt->execute(['oid' => $id]);
        $row['tasks'] = $taskStmt->fetchAll();

        return $row;
    }

    public function findByEmployeeId(int $employeeId)
    {
        $stmt = $this->db->prepare("SELECT * FROM offboarding WHERE employee_id = :eid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['eid' => $employeeId]);
        return $stmt->fetch();
    }

    public function initiate(int $employeeId, string $exitDate, string $reason, string $notes = ''): int
    {
        $offboardingId = (int) $this->insert([
            'employee_id'  => $employeeId,
            'exit_date'    => $exitDate,
            'reason'       => $reason,
            'status'       => 'In Progress',
            'notes'        => trim($notes),
        ]);

        $taskStmt = $this->db->prepare("INSERT INTO offboarding_tasks (offboarding_id, task_name, description, is_completed) VALUES (:oid, :name, :desc, 0)");
        foreach (self::$defaultTasks as $name => $desc) {
            $taskStmt->execute([
                'oid'  => $offboardingId,
                'name' => $name,
                'desc' => $desc,
            ]);
        }

        return $offboardingId;
    }

    public function addTask(int $offboardingId, string $name, string $desc = ''): int
    {
        $stmt = $this->db->prepare("INSERT INTO offboarding_tasks (offboarding_id, task_name, description, is_completed) VALUES (:oid, :name, :desc, 0)");
        $stmt->execute([
            'oid'  => $offboardingId,
            'name' => trim($name),
            'desc' => trim($desc),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function toggleTask(int $taskId, int $isCompleted): bool
    {
        $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare("UPDATE offboarding_tasks SET is_completed = :c, completed_at = :cat WHERE id = :id");
        return $stmt->execute([
            'c'   => $isCompleted ? 1 : 0,
            'cat' => $completedAt,
            'id'  => $taskId,
        ]);
    }

    public function getTask(int $taskId)
    {
        $stmt = $this->db->prepare("SELECT * FROM offboarding_tasks WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $taskId]);
        return $stmt->fetch();
    }

    /**
     * Completes offboarding:
     * 1. Marks offboarding record as Completed
     * 2. Archives employee data into offboarding_employees table
     * 3. Updates employee status to 'Terminated'
     * 4. Deactivates employee's user account if exists
     */
    public function finishOffboarding(int $offboardingId, ?int $userId = null, ?string $finalNotes = null): int
    {
        $offboarding = $this->findWithTasks($offboardingId);
        if (!$offboarding) {
            throw new RuntimeException('Offboarding record not found.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->beginTransaction();

        try {
            // 1. Update offboarding status
            $updateData = [
                'status'       => 'Completed',
                'completed_at' => $now,
            ];
            if ($finalNotes !== null) {
                $updateData['notes'] = $finalNotes;
            }
            $this->update($offboardingId, $updateData);

            // 2. Build completed tasks summary
            $taskSummaryList = [];
            foreach ($offboarding['tasks'] as $t) {
                $taskSummaryList[] = ($t['is_completed'] ? '[Done] ' : '[Pending] ') . $t['task_name'];
            }
            $summaryText = implode("\n", $taskSummaryList);

            // 3. Insert into offboarding_employees table
            $archiveStmt = $this->db->prepare("
                INSERT INTO offboarding_employees (
                    employee_id, employee_code, first_name, last_name, email, phone,
                    department_name, designation, hire_date, exit_date, reason,
                    final_settlement_status, notes, completed_tasks_summary, offboarded_at, offboarded_by
                ) VALUES (
                    :employee_id, :employee_code, :first_name, :last_name, :email, :phone,
                    :department_name, :designation, :hire_date, :exit_date, :reason,
                    :final_settlement_status, :notes, :completed_tasks_summary, :offboarded_at, :offboarded_by
                )
            ");
            $archiveStmt->execute([
                'employee_id'            => $offboarding['employee_id'],
                'employee_code'          => $offboarding['employee_code'],
                'first_name'             => $offboarding['first_name'],
                'last_name'              => $offboarding['last_name'],
                'email'                  => $offboarding['email'],
                'phone'                  => $offboarding['phone'],
                'department_name'        => $offboarding['department_name'],
                'designation'            => $offboarding['designation'],
                'hire_date'              => $offboarding['hire_date'],
                'exit_date'              => $offboarding['exit_date'],
                'reason'                 => $offboarding['reason'],
                'final_settlement_status'=> 'Completed',
                'notes'                  => $finalNotes !== null ? $finalNotes : $offboarding['notes'],
                'completed_tasks_summary'=> $summaryText,
                'offboarded_at'          => $now,
                'offboarded_by'          => $userId,
            ]);
            $archiveId = (int) $this->db->lastInsertId();

            // 4. Update employee status to 'Terminated'
            $empStmt = $this->db->prepare("UPDATE employees SET status = 'Terminated', updated_at = :now WHERE id = :id");
            $empStmt->execute([
                'now' => $now,
                'id'  => $offboarding['employee_id'],
            ]);

            // 5. Deactivate user account if employee had one
            $userStmt = $this->db->prepare("UPDATE users SET status = 'Inactive' WHERE employee_id = :eid");
            $userStmt->execute(['eid' => $offboarding['employee_id']]);

            $this->db->commit();
            return $archiveId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
