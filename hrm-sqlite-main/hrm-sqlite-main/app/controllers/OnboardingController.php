<?php
class OnboardingController extends Controller
{
    private Onboarding $onboardingModel;
    private Employee $employeeModel;
    private Department $departmentModel;
    private OnboardingApplication $applicationModel;
    private OnboardingInvite $inviteModel;

    public function __construct()
    {
        $this->requireRole(['admin', 'hr']);
        $this->onboardingModel = $this->model('Onboarding');
        $this->employeeModel = $this->model('Employee');
        $this->departmentModel = $this->model('Department');
        $this->applicationModel = $this->model('OnboardingApplication');
        $this->inviteModel = $this->model('OnboardingInvite');
    }

    public function index()
    {
        $records = $this->onboardingModel->allActive();
        $inProgressCount = count($records);

        $paginated = $this->paginate($records, 10);

        $this->render('onboarding/index', [
            'pageTitle'       => '入职管理',
            'records'         => $paginated['items'],
            'pagination'      => $paginated,
            'totalCount'      => count($records),
            'inProgressCount' => $inProgressCount,
            'completedCount'  => 0,
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $firstName = trim((string) $this->input('first_name'));
            $lastName = trim((string) $this->input('last_name'));
            $email = trim((string) $this->input('email'));
            $phone = trim((string) $this->input('phone')) ?: null;
            $gender = $this->input('gender') ?: null;
            $dob = $this->input('dob') ?: null;
            $idNumber = trim((string) $this->input('id_number')) ?: null;
            $address = trim((string) $this->input('address')) ?: null;
            $departmentId = $this->input('department_id') ?: null;
            $designation = trim((string) $this->input('designation')) ?: null;
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
                'phone'         => $phone,
                'gender'        => $gender,
                'dob'           => $dob,
                'id_number'     => $idNumber,
                'address'       => $address,
                'department_id' => $departmentId,
                'designation'   => $designation,
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
            'pageTitle' => '入职清单 - ' . $record['last_name'] . $record['first_name'],
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

    // ---- QR-code onboarding applications ----

    /**
     * QR code page: show the current active invite link as a scannable QR code.
     */
    public function qr()
    {
        $invite = $this->inviteModel->ensureActive($_SESSION['user_id'] ?? null);

        $this->render('onboarding/qr', [
            'pageTitle' => '入职二维码',
            'invite'    => $invite,
            'applyUrl'  => $this->absoluteUrl('apply/form/' . $invite['token']),
            'pendingCount' => $this->applicationModel->countByStatus('Pending'),
        ]);
    }

    /**
     * Deactivate the current invite and issue a fresh QR token.
     */
    public function regenerateInvite()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('onboarding/qr');
        }
        $this->verifyCsrf();

        $this->inviteModel->issue($_SESSION['user_id'] ?? null);
        $this->setFlash('success', '入职二维码已重新生成，旧二维码已失效。');
        $this->redirect('onboarding/qr');
    }

    /**
     * List onboarding applications submitted through the QR-code form.
     */
    public function applications()
    {
        $status = (string) $this->input('status', '');
        if (!in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
            $status = '';
        }

        $records = $this->applicationModel->allWithDetails($status);
        $paginated = $this->paginate($records, 10);

        $this->render('onboarding/applications', [
            'pageTitle'     => '入职申请',
            'records'       => $paginated['items'],
            'pagination'    => $paginated,
            'status'        => $status,
            'pendingCount'  => $this->applicationModel->countByStatus('Pending'),
            'approvedCount' => $this->applicationModel->countByStatus('Approved'),
            'rejectedCount' => $this->applicationModel->countByStatus('Rejected'),
        ]);
    }

    /**
     * Application detail with approve / reject actions.
     */
    public function application($id)
    {
        $application = $this->applicationModel->findWithDepartment((int) $id);
        if (!$application) {
            $this->setFlash('error', '未找到该入职申请。');
            $this->redirect('onboarding/applications');
            return;
        }

        $onboardingRecord = null;
        if ($application['hired_employee_id']) {
            $onboardingRecord = $this->onboardingModel->findByEmployeeId((int) $application['hired_employee_id']);
        }

        $this->render('onboarding/application', [
            'pageTitle'        => '入职申请详情 - ' . $application['last_name'] . $application['first_name'],
            'application'      => $application,
            'departments'      => $this->departmentModel->all('name ASC'),
            'onboardingRecord' => $onboardingRecord,
        ]);
    }

    /**
     * Approve an application: create the Employee record, start the
     * onboarding workflow with the default checklist, and mark the
     * application as approved.
     */
    public function approve($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('onboarding/applications');
        }
        $this->verifyCsrf();

        $id = (int) $id;
        $application = $this->applicationModel->findWithDepartment($id);
        if (!$application) {
            $this->setFlash('error', '未找到该入职申请。');
            $this->redirect('onboarding/applications');
            return;
        }
        if ($application['status'] !== 'Pending') {
            $this->setFlash('error', '该申请已审核过，不能重复操作。');
            $this->redirect('onboarding/application/' . $id);
            return;
        }

        if ($this->employeeModel->findOneBy(['email' => $application['email']])) {
            $this->setFlash('error', '使用该邮箱的员工已存在，无法重复入职。');
            $this->redirect('onboarding/application/' . $id);
            return;
        }

        $hireDate = $this->input('hire_date') ?: date('Y-m-d');
        $targetDate = $this->input('target_completion_date') ?: date('Y-m-d', strtotime('+14 days'));
        $salary = (float) $this->input('salary', (float) $application['expected_salary']);
        $departmentId = $this->input('department_id') ?: $application['department_id'];
        $designation = trim((string) $this->input('designation')) ?: $application['designation'];
        $comment = trim((string) $this->input('review_comment')) ?: null;

        // 1. Create the new Employee record from the application data
        $code = $this->employeeModel->generateEmployeeCode();
        $employeeId = (int) $this->employeeModel->insert([
            'employee_code'     => $code,
            'first_name'        => $application['first_name'],
            'last_name'         => $application['last_name'],
            'email'             => $application['email'],
            'phone'             => $application['phone'],
            'gender'            => $application['gender'],
            'dob'               => $application['dob'],
            'id_number'         => $application['id_number'],
            'ethnicity'         => $application['ethnicity'],
            'household_address' => $application['household_address'],
            'emergency_contact' => $application['emergency_contact'],
            'emergency_relation' => $application['emergency_relation'],
            'emergency_phone'   => $application['emergency_phone'],
            'education'         => $application['education'],
            'address'           => $application['address'],
            'department_id'     => $departmentId,
            'designation'       => $designation,
            'hire_date'         => $hireDate,
            'salary'            => $salary,
            'status'            => 'Active',
        ]);

        // 2. Start the onboarding workflow with the default checklist
        $onboardingId = (int) $this->onboardingModel->createForEmployee(
            $employeeId,
            $hireDate,
            $targetDate,
            $comment ?? ('来源：扫码入职申请 #' . $id)
        );

        // 3. Mark the application as approved
        $this->applicationModel->review(
            $id,
            'Approved',
            $_SESSION['user_id'] ?? null,
            $comment,
            $employeeId
        );

        $this->setFlash('success', "入职申请已批准！新员工 {$application['last_name']}{$application['first_name']} ({$code}) 已创建并启动入职流程。");
        $this->redirect('onboarding/tasks/' . $onboardingId);
    }

    /**
     * Reject an application with an optional comment.
     */
    public function reject($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('onboarding/applications');
        }
        $this->verifyCsrf();

        $id = (int) $id;
        $application = $this->applicationModel->findWithDepartment($id);
        if (!$application) {
            $this->setFlash('error', '未找到该入职申请。');
            $this->redirect('onboarding/applications');
            return;
        }
        if ($application['status'] !== 'Pending') {
            $this->setFlash('error', '该申请已审核过，不能重复操作。');
            $this->redirect('onboarding/application/' . $id);
            return;
        }

        $comment = trim((string) $this->input('review_comment')) ?: null;
        $this->applicationModel->review($id, 'Rejected', $_SESSION['user_id'] ?? null, $comment);

        $this->setFlash('success', '入职申请已拒绝。');
        $this->redirect('onboarding/applications');
    }

    /**
     * Build an absolute URL (scheme + host + BASE_URL) for QR codes.
     */
    private function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . BASE_URL . '/' . ltrim($path, '/');
    }
}
