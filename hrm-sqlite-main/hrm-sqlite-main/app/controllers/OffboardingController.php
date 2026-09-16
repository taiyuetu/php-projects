<?php
class OffboardingController extends Controller
{
    private Offboarding $offboardingModel;
    private OffboardedEmployee $offboardedModel;
    private Employee $employeeModel;

    public function __construct()
    {
        $this->requireRole(['admin', 'hr']);
        $this->offboardingModel = $this->model('Offboarding');
        $this->offboardedModel = $this->model('OffboardedEmployee');
        $this->employeeModel = $this->model('Employee');
    }

    public function index()
    {
        $activeOffboardings = $this->offboardingModel->allWithDetails();
        $offboardedEmployees = $this->offboardedModel->allWithDetails();
        $activeTab = $this->input('tab', !empty($activeOffboardings) ? 'active' : 'offboarded');

        $paginatedActive = $this->paginate($activeOffboardings, 10, 'page_active');
        $paginatedOffboarded = $this->paginate($offboardedEmployees, 10, 'page_offboarded');

        $this->render('offboarding/index', [
            'pageTitle'            => '离职管理',
            'activeOffboardings'   => $paginatedActive['items'],
            'activePagination'     => $paginatedActive,
            'offboardedEmployees'  => $paginatedOffboarded['items'],
            'offboardedPagination' => $paginatedOffboarded,
            'totalActiveCount'     => count($activeOffboardings),
            'totalOffboardedCount' => count($offboardedEmployees),
            'activeTab'            => $activeTab,
        ]);
    }

    public function initiate($employeeId = null)
    {
        $employeeId = (int) ($employeeId ?: $this->input('employee_id'));
        if (!$employeeId) {
            $this->setFlash('error', '请选择需要办理离职的员工。');
            $this->redirect('employee');
        }

        $employee = $this->employeeModel->findWithDepartment($employeeId);
        if (!$employee) {
            $this->setFlash('error', '未找到该员工。');
            $this->redirect('employee');
        }

        // Check if there is already an active offboarding in progress
        $existing = $this->offboardingModel->findByEmployeeId($employeeId);
        if ($existing && $existing['status'] === 'In Progress') {
            $this->setFlash('info', '该员工已有正在进行的离职流程。');
            $this->redirect('offboarding/tasks/' . $existing['id']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $exitDate = $this->input('exit_date') ?: date('Y-m-d');
            $reason = trim((string) $this->input('reason'));
            $notes = (string) $this->input('notes', '');

            if (empty($reason)) {
                $this->setFlash('error', '请说明离职原因。');
                $this->redirect('offboarding/initiate/' . $employeeId);
                return;
            }

            $offboardingId = $this->offboardingModel->initiate($employeeId, $exitDate, $reason, $notes);
            $this->setFlash('success', '已为 ' . $employee['first_name'] . ' ' . $employee['last_name'] . ' 发起离职流程，请完成离职交接清单。');
            $this->redirect('offboarding/tasks/' . $offboardingId);
            return;
        }

        $this->render('offboarding/initiate', [
            'pageTitle' => '办理离职 - ' . $employee['first_name'] . ' ' . $employee['last_name'],
            'employee'  => $employee,
        ]);
    }

    public function tasks($id)
    {
        $record = $this->offboardingModel->findWithTasks((int) $id);
        if (!$record) {
            $this->setFlash('error', '未找到该离职记录。');
            $this->redirect('offboarding');
        }

        $this->render('offboarding/tasks', [
            'pageTitle' => '离职清单 - ' . $record['first_name'] . ' ' . $record['last_name'],
            'record'    => $record,
        ]);
    }

    public function toggleTask($taskId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offboarding');
        }
        $this->verifyCsrf();

        $task = $this->offboardingModel->getTask((int) $taskId);
        if (!$task) {
            $this->setFlash('error', '未找到该任务。');
            $this->redirect('offboarding');
        }

        $isCompleted = (int) $this->input('is_completed', 0);
        $this->offboardingModel->toggleTask((int) $taskId, $isCompleted);

        $this->setFlash('success', '清单任务状态已更新。');
        $this->redirect('offboarding/tasks/' . $task['offboarding_id']);
    }

    public function addTask($offboardingId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offboarding');
        }
        $this->verifyCsrf();

        $name = trim((string) $this->input('task_name'));
        $desc = trim((string) $this->input('description'));

        if (!empty($name)) {
            $this->offboardingModel->addTask((int) $offboardingId, $name, $desc);
            $this->setFlash('success', '自定义离职交接任务已添加。');
        } else {
            $this->setFlash('error', '任务名称不能为空。');
        }

        $this->redirect('offboarding/tasks/' . (int) $offboardingId);
    }

    public function finish($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offboarding');
        }
        $this->verifyCsrf();

        $finalNotes = $this->input('final_notes');
        $userId = $_SESSION['user_id'] ?? null;

        try {
            $archiveId = $this->offboardingModel->finishOffboarding((int) $id, $userId, $finalNotes ? trim($finalNotes) : null);
            $this->setFlash('success', '离职流程顺利完成！员工数据已移至已离职员工归档表。');
            $this->redirect('offboarding?tab=offboarded');
        } catch (Exception $e) {
            $this->setFlash('error', '完成离职失败：' . $e->getMessage());
            $this->redirect('offboarding/tasks/' . (int) $id);
        }
    }

    public function view($id)
    {
        $employee = $this->offboardedModel->findWithDetails((int) $id);
        if (!$employee) {
            $this->setFlash('error', '未找到该已离职员工记录。');
            $this->redirect('offboarding?tab=offboarded');
        }

        $this->render('offboarding/view', [
            'pageTitle' => '已离职员工档案 - ' . $employee['first_name'] . ' ' . $employee['last_name'],
            'employee'  => $employee,
        ]);
    }

    public function deleteOffboarded($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offboarding?tab=offboarded');
        }
        $this->verifyCsrf();

        $record = $this->offboardedModel->find((int) $id);
        if (!$record) {
            $this->setFlash('error', '未找到该已离职员工记录。');
            $this->redirect('offboarding?tab=offboarded');
        }

        // Permanently delete the archive record
        $this->offboardedModel->delete((int) $id);

        // Permanently clean up associated employee and user data if still present
        if (!empty($record['employee_id'])) {
            $userModel = $this->model('User');
            $user = $userModel->findOneBy(['employee_id' => $record['employee_id']]);
            if ($user) {
                $userModel->delete($user['id']);
            }
            $this->employeeModel->delete($record['employee_id']);
        }

        $this->setFlash('success', '已离职员工记录已永久删除。');
        $this->redirect('offboarding?tab=offboarded');
    }
}
