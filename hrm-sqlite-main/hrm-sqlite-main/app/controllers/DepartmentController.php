<?php
class DepartmentController extends Controller
{
    private Department $departments;

    public function __construct()
    {
        $this->requireRole(['admin', 'hr']);
        $this->departments = new Department();
    }

    public function index()
    {
        $this->render('departments/index', [
            'pageTitle' => 'Departments',
            'departments' => $this->departments->allWithEmployeeCount(),
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $name = trim((string) $this->input('name'));
            if ($name === '') { $this->setFlash('error', 'Department name is required.'); $this->redirect('department/create'); }
            $this->departments->insert(['name' => $name, 'description' => trim((string) $this->input('description')) ?: null]);
            $this->setFlash('success', 'Department created successfully.');
            $this->redirect('department');
        }
        $this->render('departments/form', ['pageTitle' => 'Add Department', 'department' => null]);
    }
}
