<?php
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->requireLogin();
    }

    public function index()
    {
        $employeeModel = $this->model('Employee');
        $departmentModel = $this->model('Department');
        $leaveModel = $this->model('LeaveRequest');
        $attendanceModel = $this->model('Attendance');

        $jobModel = $this->model('JobOpening');
        $candidateModel = $this->model('Candidate');

        $data = [
            'pageTitle'       => 'Dashboard',
            'totalEmployees'  => $employeeModel->count(),
            'activeEmployees' => $employeeModel->countActive(),
            'totalDepartments'=> $departmentModel->count(),
            'pendingLeaves'   => $leaveModel->countByStatus('Pending'),
            'openJobs'        => $jobModel->countOpen(),
            'activeCandidates'=> $candidateModel->countActive(),
            'todayAttendance' => $attendanceModel->allWithEmployee(date('Y-m-d')),
            'recentEmployees' => array_slice($employeeModel->allWithDepartment(), 0, 5),
        ];

        $this->render('dashboard/index', $data);
    }
}
