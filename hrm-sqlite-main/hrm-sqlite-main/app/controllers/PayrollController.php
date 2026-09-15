<?php
class PayrollController extends Controller
{
    public function __construct() { $this->requireRole(['admin', 'hr']); }

    public function index()
    {
        $all = (new Payroll())->allWithEmployee();
        $paginated = $this->paginate($all, 10);
        $this->render('payroll/index', [
            'pageTitle'  => 'Payroll',
            'payroll'    => $paginated['items'],
            'pagination' => $paginated,
        ]);
    }

    public function generate()
    {
        $this->verifyCsrf();
        $month = (int) $this->input('month', date('n')); $year = (int) $this->input('year', date('Y'));
        foreach ((new Employee())->all() as $employee) if ($employee['status'] === 'Active') (new Payroll())->generateForEmployee($employee, $month, $year);
        $this->setFlash('success', 'Payroll generated for active employees.'); $this->redirect('payroll');
    }

    public function paid($id) { $this->verifyCsrf(); (new Payroll())->markPaid((int) $id); $this->setFlash('success', 'Payroll marked as paid.'); $this->redirect('payroll'); }
}
