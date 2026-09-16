<?php
class PayrollController extends Controller
{
    public function __construct() { $this->requireRole(['admin', 'hr']); }

    public function index()
    {
        $all = (new Payroll())->allWithEmployee();
        $paginated = $this->paginate($all, 10);
        $this->render('payroll/index', [
            'pageTitle'  => '薪酬管理',
            'payroll'    => $paginated['items'],
            'pagination' => $paginated,
        ]);
    }

    public function generate()
    {
        $this->verifyCsrf();
        $month = (int) $this->input('month', date('n')); $year = (int) $this->input('year', date('Y'));
        foreach ((new Employee())->all() as $employee) if ($employee['status'] === 'Active') (new Payroll())->generateForEmployee($employee, $month, $year);
        $this->setFlash('success', '已为在职员工生成薪资表。'); $this->redirect('payroll');
    }

    public function paid($id) { $this->verifyCsrf(); (new Payroll())->markPaid((int) $id); $this->setFlash('success', '薪资状态已标记为已发放。'); $this->redirect('payroll'); }
}
