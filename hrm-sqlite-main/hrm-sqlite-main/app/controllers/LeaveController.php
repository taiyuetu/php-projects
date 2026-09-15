<?php
class LeaveController extends Controller
{
    public function __construct() { $this->requireLogin(); }

    public function index()
    {
        $model = new LeaveRequest();
        $rows = $_SESSION['user_role'] === 'employee' && $_SESSION['employee_id']
            ? $model->forEmployee((int) $_SESSION['employee_id']) : $model->allWithEmployee();
        $paginated = $this->paginate($rows, 10);
        $this->render('leave/index', [
            'pageTitle'  => 'Leave Requests',
            'leaves'     => $paginated['items'],
            'pagination' => $paginated,
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            if (!$_SESSION['employee_id']) { $this->setFlash('error', 'Link an employee profile before applying for leave.'); $this->redirect('leave'); }
            $start = $this->input('start_date'); $end = $this->input('end_date');
            if (!$start || !$end || $end < $start) { $this->setFlash('error', 'Please provide a valid date range.'); $this->redirect('leave/create'); }
            (new LeaveRequest())->insert(['employee_id' => $_SESSION['employee_id'], 'leave_type' => $this->input('leave_type', 'Casual'), 'start_date' => $start, 'end_date' => $end, 'reason' => trim((string) $this->input('reason')) ?: null]);
            $this->setFlash('success', 'Leave request submitted.'); $this->redirect('leave');
        }
        $this->render('leave/form', ['pageTitle' => 'Apply for Leave']);
    }

    public function approve($id) { $this->requireRole(['admin', 'hr']); $this->updateStatus($id, 'Approved'); }
    public function reject($id) { $this->requireRole(['admin', 'hr']); $this->updateStatus($id, 'Rejected'); }
    private function updateStatus($id, $status) { $this->verifyCsrf(); (new LeaveRequest())->setStatus((int) $id, $status, (int) $_SESSION['user_id']); $this->setFlash('success', "Leave request {$status}. "); $this->redirect('leave'); }
}
