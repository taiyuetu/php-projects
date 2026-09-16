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
        $all = $this->departments->allWithEmployeeCount();
        $paginated = $this->paginate($all, 10);

        $this->render('departments/index', [
            'pageTitle'   => '部门管理',
            'departments' => $paginated['items'],
            'pagination'  => $paginated,
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $name = trim((string) $this->input('name'));
            if ($name === '') { $this->setFlash('error', '部门名称必填。'); $this->redirect('department/create'); }
            $this->departments->insert(['name' => $name, 'description' => trim((string) $this->input('description')) ?: null]);
            $this->setFlash('success', '部门添加成功。');
            $this->redirect('department');
        }
        $this->render('departments/form', ['pageTitle' => '添加部门', 'department' => null]);
    }
}
