<?php
class OnboardingController extends Controller
{
    private Onboarding $onboardingModel;
    private Employee $employeeModel;
    private Department $departmentModel;

    public function __construct()
    {
        $this->requireRole(['admin', 'hr']);
        $this->onboardingModel = $this->model('Onboarding');
        $this->employeeModel = $this->model('Employee');
        $this->departmentModel = $this->model('Department');
    }

    public function index()
    {
        $records = $this->onboardingModel->allWithDetails();
        $inProgressCount = 0;
        $completedCount = 0;
        foreach ($records as $r) {
            if ($r['status'] === 'Completed') {
                $completedCount++;
            } else {
                $inProgressCount++;
            }
        }

        $paginated = $this->paginate($records, 10);

        $this->render('onboarding/index', [
            'pageTitle'       => '入职管理',
            'records'         => $paginated['items'],
            'pagination'      => $paginated,
            'totalCount'      => count($records),
            'inProgressCount' => $inProgressCount,
            'completedCount'  => $completedCount,
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $firstName = trim((string) $this->input('first_name'));
            $lastName = trim((string) $this->input('last_name'));
            $email = trim((string) $this->input('email'));
            $phone = trim((string) $this->input('phone'));
            $gender = $this->input('gender') ?: null;
            $dob = $this->input('dob') ?: null;
            $address = trim((string) $this->input('address'));
            $departmentId = $this->input('department_id') ?: null;
            $designation = trim((string) $this->input('designation'));
            $hireDate = $this->input('hire_date') ?: date('Y-m-d');
            $salary = (float) $this->input('salary', 0);
            $targetDate = $this->input('target_completion_date') ?: date('Y-m-d', strtotime('+14 days'));
            $notes = trim((string) $this->input('notes', ''));

            if ($firstName === '' || $lastName === '' || $email === '') {
                $this->setFlash('error', '名字、姓氏和邮箱必填。');
                $this->redirect('onboarding/create');
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->setFlash('error', '请输入有效的邮箱地址。');
                $this->redirect('onboarding/create');
                return;
            }

            // Check if email is already in use
            if ($this->employeeModel->findOneBy(['email' => $email])) {
                $this->setFlash('error', '使用该邮箱的员工已存在。');
                $this->redirect('onboarding/create');
                return;
            }

            // 1. Create the new Employee record
            $code = $this->employeeModel->generateEmployeeCode();
            $employeeId = (int) $this->employeeModel->insert([
                'employee_code' => $code,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'email'         => $email,
                'phone'         => $phone ?: null,
                'gender'        => $gender,
                'dob'           => $dob,
                'address'       => $address ?: null,
                'department_id' => $departmentId,
                'designation'   => $designation ?: null,
                'hire_date'     => $hireDate,
                'salary'        => $salary,
                'status'        => 'Active',
            ]);

            // 2. Create the Onboarding workflow record and default tasks
            $onboardingId = $this->onboardingModel->createForEmployee(
                $employeeId,
                $hireDate,
                $targetDate,
                $notes
            );

            // 3. If converted from ATS candidate, mark candidate as Hired and record note
            $candidateId = (int) $this->input('candidate_id');
            if ($candidateId) {
                $candidateModel = $this->model('Candidate');
                $candidateModel->markHired($candidateId, $employeeId);
                $noteModel = $this->model('CandidateNote');
                $noteModel->addNote(
                    $candidateId,
                    $_SESSION['user_id'] ?? null,
                    'Hired',
                    "Candidate officially hired as {$designation} (Employee Code: {$code}). Successfully transitioned into Onboarding."
                );
            }

            $this->setFlash('success', "新员工 {$firstName} {$lastName} ({$code}) 创建成功，已启动入职流程！");
            $this->redirect('onboarding/tasks/' . $onboardingId);
            return;
        }

        $candidate = null;
        $candidateId = (int) $this->input('candidate_id');
        if ($candidateId) {
            $candidate = $this->model('Candidate')->findWithJob($candidateId);
        }

        $this->render('onboarding/create', [
            'pageTitle'     => '办理新员工入职',
            'departments'   => $this->departmentModel->all('name ASC'),
            'suggestedCode' => $this->employeeModel->generateEmployeeCode(),
            'candidate'     => $candidate,
        ]);
    }

    public function tasks($id)
    {
        $record = $this->onboardingModel->findWithTasks((int) $id);
        if (!$record) {
            $this->setFlash('error', '未找到该入职记录。');
            $this->redirect('onboarding');
        }

        $this->render('onboarding/tasks', [
            'pageTitle' => '入职清单 - ' . $record['first_name'] . ' ' . $record['last_name'],
            'record'    => $record,
        ]);
    }

    public function toggleTask($taskId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('onboarding');
        }
        $this->verifyCsrf();

        $task = $this->onboardingModel->getTask((int) $taskId);
        if (!$task) {
            $this->setFlash('error', '未找到该任务。');
            $this->redirect('onboarding');
        }

        $isCompleted = (int) $this->input('is_completed', 0);
        $this->onboardingModel->toggleTask((int) $taskId, $isCompleted);

        $this->setFlash('success', '清单任务状态已更新。');
        $this->redirect('onboarding/tasks/' . $task['onboarding_id']);
    }

    public function addTask($onboardingId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('onboarding');
        }
        $this->verifyCsrf();

        $name = trim((string) $this->input('task_name'));
        $desc = trim((string) $this->input('description'));

        if (!empty($name)) {
            $this->onboardingModel->addTask((int) $onboardingId, $name, $desc);
            $this->setFlash('success', '自定义清单任务已添加。');
        } else {
            $this->setFlash('error', '任务名称不能为空。');
        }

        $this->redirect('onboarding/tasks/' . (int) $onboardingId);
    }

    public function complete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('onboarding');
        }
        $this->verifyCsrf();

        $notes = $this->input('notes');
        $this->onboardingModel->completeOnboarding((int) $id, $notes ? trim($notes) : null);

        $this->setFlash('success', '入职流程已标记为已完成！');
        $this->redirect('onboarding');
    }
}
