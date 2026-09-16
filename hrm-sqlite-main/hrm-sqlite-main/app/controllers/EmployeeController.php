<?php
class EmployeeController extends Controller
{
    private Employee $employeeModel;
    private Department $departmentModel;

    public function __construct()
    {
        $this->requireRole(['admin', 'hr']);
        $this->employeeModel = $this->model('Employee');
        $this->departmentModel = $this->model('Department');
    }

    public function index()
    {
        $keyword = $this->input('q');
        $employees = $keyword ? $this->employeeModel->search($keyword) : $this->employeeModel->allWithDepartment();
        $paginated = $this->paginate($employees, 10);

        $this->render('employees/index', [
            'pageTitle'  => '员工管理',
            'employees'  => $paginated['items'],
            'pagination' => $paginated,
            'keyword'    => $keyword,
        ]);
    }

    public function profile($id)
    {
        $employee = $this->employeeModel->findWithDepartment($id);
        if (!$employee) {
            $this->setFlash('error', '未找到该员工。');
            $this->redirect('employee');
        }

        $attendanceModel = $this->model('Attendance');
        $leaveModel = $this->model('LeaveRequest');

        $this->render('employees/view', [
            'pageTitle' => '员工详情',
            'employee'  => $employee,
            'attendance'=> $attendanceModel->historyForEmployee($id),
            'leaves'    => $leaveModel->forEmployee($id),
        ]);
    }

    public function create()
    {
        // New employees are registered and onboarded via the Onboarding workflow
        $this->setFlash('info', '新员工需通过入职流程完成注册。');
        $this->redirect('onboarding/create');
    }

    public function edit($id)
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            $this->setFlash('error', '未找到该员工。');
            $this->redirect('employee');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($id);
            return;
        }

        $this->render('employees/form', [
            'pageTitle'   => '编辑员工',
            'departments' => $this->departmentModel->all('name ASC'),
            'employee'    => $employee,
            'code'        => $employee['employee_code'],
        ]);
    }

    private function save($id = null)
    {
        $this->verifyCsrf();
        $data = [
            'first_name'    => trim($this->input('first_name')),
            'last_name'     => trim($this->input('last_name')),
            'email'         => trim($this->input('email')),
            'phone'         => trim($this->input('phone')),
            'gender'        => $this->input('gender') ?: null,
            'dob'           => $this->input('dob') ?: null,
            'address'       => trim($this->input('address')),
            'department_id' => $this->input('department_id') ?: null,
            'designation'   => trim($this->input('designation')),
            'hire_date'     => $this->input('hire_date') ?: null,
            'salary'        => (float) $this->input('salary', 0),
            'status'        => $this->input('status', 'Active'),
        ];

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            $this->setFlash('error', '名字、姓氏和邮箱必填。');
            $this->redirect($id ? "employee/edit/{$id}" : 'employee/create');
        }

        if ($id) {
            $this->employeeModel->update($id, $data);
            $this->setFlash('success', '员工信息更新成功。');
        } else {
            $data['employee_code'] = $this->employeeModel->generateEmployeeCode();
            $id = (int) $this->employeeModel->insert($data);
            
            // Automatically initialize onboarding checklist for the new hire
            $onboardingModel = $this->model('Onboarding');
            $startDate = $data['hire_date'] ?: date('Y-m-d');
            $onboardingModel->createForEmployee($id, $startDate, null, 'New employee onboarding');

            $this->setFlash('success', '员工添加成功并已创建入职清单。');
        }

        $this->redirect('employee');
    }

    public function delete($id)
    {
        // Deletion is replaced by offboarding
        $this->setFlash('info', '禁止直接删除员工，请通过离职流程办理。');
        $this->redirect('offboarding/initiate/' . (int) $id);
    }
}
