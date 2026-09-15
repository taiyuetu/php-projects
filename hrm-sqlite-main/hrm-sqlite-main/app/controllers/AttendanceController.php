<?php
class AttendanceController extends Controller
{
    public function __construct() { $this->requireLogin(); }

    public function index()
    {
        $date = $this->input('date', date('Y-m-d'));
        $model = new Attendance();
        $rows = $_SESSION['user_role'] === 'employee' && $_SESSION['employee_id']
            ? array_values(array_filter($model->allWithEmployee($date), fn($row) => (int) $row['employee_id'] === (int) $_SESSION['employee_id']))
            : $model->allWithEmployee($date);
        $paginated = $this->paginate($rows, 10);
        $this->render('attendance/index', [
            'pageTitle'  => 'Attendance',
            'date'       => $date,
            'attendance' => $paginated['items'],
            'pagination' => $paginated,
        ]);
    }

    public function checkIn()
    {
        $this->verifyCsrf();
        if (!$_SESSION['employee_id']) { $this->setFlash('error', 'Your account is not linked to an employee.'); $this->redirect('attendance'); }
        (new Attendance())->checkIn((int) $_SESSION['employee_id'], date('Y-m-d'), date('H:i:s'));
        $this->setFlash('success', 'Checked in successfully.'); $this->redirect('attendance');
    }

    public function checkOut()
    {
        $this->verifyCsrf();
        if ($_SESSION['employee_id']) (new Attendance())->checkOut((int) $_SESSION['employee_id'], date('Y-m-d'), date('H:i:s'));
        $this->setFlash('success', 'Checked out successfully.'); $this->redirect('attendance');
    }
}
