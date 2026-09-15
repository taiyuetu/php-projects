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

        $this->render('employees/index', [
            'pageTitle' => 'Employees',
            'employees' => $employees,
            'keyword'   => $keyword,
        ]);
    }

    public function profile($id)
    {
        $employee = $this->employeeModel->findWithDepartment($id);
        if (!$employee) {
            $this->setFlash('error', 'Employee not found.');
            $this->redirect('employee');
        }

        $attendanceModel = $this->model('Attendance');
        $leaveModel = $this->model('LeaveRequest');

        $this->render('employees/view', [
            'pageTitle' => 'Employee Profile',
            'employee'  => $employee,
            'attendance'=> $attendanceModel->historyForEmployee($id),
            'leaves'    => $leaveModel->forEmployee($id),
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save();
            return;
        }

        $this->render('employees/form', [
            'pageTitle'   => 'Add Employee',
            'departments' => $this->departmentModel->all('name ASC'),
            'employee'    => null,
            'code'        => $this->employeeModel->generateEmployeeCode(),
        ]);
    }

    public function edit($id)
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            $this->setFlash('error', 'Employee not found.');
            $this->redirect('employee');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save($id);
            return;
        }

        $this->render('employees/form', [
            'pageTitle'   => 'Edit Employee',
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
            $this->setFlash('error', 'First name, last name and email are required.');
            $this->redirect($id ? "employee/edit/{$id}" : 'employee/create');
        }

        if ($id) {
            $this->employeeModel->update($id, $data);
            $this->setFlash('success', 'Employee updated successfully.');
        } else {
            $data['employee_code'] = $this->employeeModel->generateEmployeeCode();
            $id = $this->employeeModel->insert($data);
            $this->setFlash('success', 'Employee added successfully.');
        }

        $this->redirect('employee');
    }

    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('employee');
        }
        $this->verifyCsrf();
        $this->employeeModel->delete($id);
        $this->setFlash('success', 'Employee removed.');
        $this->redirect('employee');
    }
}
