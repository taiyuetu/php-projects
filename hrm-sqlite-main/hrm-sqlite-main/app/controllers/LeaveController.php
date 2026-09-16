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
            'pageTitle'  => '请假管理',
            'leaves'     => $paginated['items'],
            'pagination' => $paginated,
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            if (!$_SESSION['employee_id']) { $this->setFlash('error', '申请请假前请先关联员工档案。'); $this->redirect('leave'); }
            $start = $this->input('start_date'); $end = $this->input('end_date');
            if (!$start || !$end || $end < $start) { $this->setFlash('error', '请提供有效的日期范围。'); $this->redirect('leave/create'); }
            (new LeaveRequest())->insert(['employee_id' => $_SESSION['employee_id'], 'leave_type' => $this->input('leave_type', 'Casual'), 'start_date' => $start, 'end_date' => $end, 'reason' => trim((string) $this->input('reason')) ?: null]);
            $this->setFlash('success', '请假申请已提交。'); $this->redirect('leave');
        }
        $this->render('leave/form', ['pageTitle' => '申请请假']);
    }

    public function approve($id) { $this->requireRole(['admin', 'hr']); $this->updateStatus($id, 'Approved'); }
    public function reject($id) { $this->requireRole(['admin', 'hr']); $this->updateStatus($id, 'Rejected'); }
    private function updateStatus($id, $status) { $this->verifyCsrf(); (new LeaveRequest())->setStatus((int) $id, $status, (int) $_SESSION['user_id']); $statusText = $status === 'Approved' ? '已批准' : '已拒绝'; $this->setFlash('success', "请假申请{$statusText}。"); $this->redirect('leave'); }
}
