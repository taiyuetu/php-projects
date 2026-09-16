<?php
class RecruitmentController extends Controller
{
    private JobOpening $jobModel;
    private Candidate $candidateModel;
    private CandidateNote $noteModel;
    private Department $departmentModel;

    public function __construct()
    {
        $this->requireRole(['admin', 'hr']);
        $this->jobModel = $this->model('JobOpening');
        $this->candidateModel = $this->model('Candidate');
        $this->noteModel = $this->model('CandidateNote');
        $this->departmentModel = $this->model('Department');
    }

    public function index()
    {
        $statusFilter = $this->input('status');
        $jobs = $this->jobModel->allWithDepartmentAndCount($statusFilter ?: null);
        $paginated = $this->paginate($jobs, 10);

        $this->render('recruitment/index', [
            'pageTitle'     => '招聘管理 - 职位列表',
            'jobs'          => $paginated['items'],
            'pagination'    => $paginated,
            'statusFilter'  => $statusFilter,
            'totalOpen'     => $this->jobModel->countOpen(),
            'activeCandidates' => $this->candidateModel->countActive(),
        ]);
    }

    public function createJob()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $title = trim((string) $this->input('title'));
            if ($title === '') {
                $this->setFlash('error', '职位名称必填。');
                $this->redirect('recruitment/createJob');
                return;
            }

            $jobId = $this->jobModel->insert([
                'title'           => $title,
                'department_id'   => $this->input('department_id') ?: null,
                'employment_type' => $this->input('employment_type', 'Full-Time'),
                'openings_count'  => max(1, (int) $this->input('openings_count', 1)),
                'location'        => trim((string) $this->input('location', 'Singapore')),
                'salary_range'    => trim((string) $this->input('salary_range', '')),
                'description'     => trim((string) $this->input('description', '')),
                'requirements'    => trim((string) $this->input('requirements', '')),
                'status'          => $this->input('status', 'Open'),
            ]);

            $this->setFlash('success', "招聘职位 '{$title}' 发布成功。");
            $this->redirect('recruitment');
            return;
        }

        $this->render('recruitment/job_form', [
            'pageTitle'   => '发布新职位',
            'job'         => null,
            'departments' => $this->departmentModel->all('name ASC'),
        ]);
    }

    public function editJob($id)
    {
        $job = $this->jobModel->find((int) $id);
        if (!$job) {
            $this->setFlash('error', '未找到该招聘职位。');
            $this->redirect('recruitment');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $title = trim((string) $this->input('title'));
            if ($title === '') {
                $this->setFlash('error', '职位名称必填。');
                $this->redirect('recruitment/editJob/' . (int) $id);
                return;
            }

            $this->jobModel->update((int) $id, [
                'title'           => $title,
                'department_id'   => $this->input('department_id') ?: null,
                'employment_type' => $this->input('employment_type', 'Full-Time'),
                'openings_count'  => max(1, (int) $this->input('openings_count', 1)),
                'location'        => trim((string) $this->input('location', 'Singapore')),
                'salary_range'    => trim((string) $this->input('salary_range', '')),
                'description'     => trim((string) $this->input('description', '')),
                'requirements'    => trim((string) $this->input('requirements', '')),
                'status'          => $this->input('status', 'Open'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->setFlash('success', "招聘职位 '{$title}' 更新成功。");
            $this->redirect('recruitment');
            return;
        }

        $this->render('recruitment/job_form', [
            'pageTitle'   => '编辑招聘职位 - ' . $job['title'],
            'job'         => $job,
            'departments' => $this->departmentModel->all('name ASC'),
        ]);
    }

    public function closeJob($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('recruitment');
        }
        $this->verifyCsrf();
        $this->jobModel->update((int) $id, ['status' => 'Closed', 'updated_at' => date('Y-m-d H:i:s')]);
        $this->setFlash('success', '招聘职位已标记为已关闭。');
        $this->redirect('recruitment');
    }

    public function ats()
    {
        $selectedJobId = (int) $this->input('job_id', 0);
        $selectedStage = $this->input('stage');
        $keyword = $this->input('q');

        $filters = [];
        if ($selectedJobId) {
            $filters['job_id'] = $selectedJobId;
        }
        if ($selectedStage && array_key_exists($selectedStage, Candidate::$stages)) {
            $filters['stage'] = $selectedStage;
        }
        if ($keyword) {
            $filters['keyword'] = $keyword;
        }

        $candidates = $this->candidateModel->allWithDetails($filters);
        $paginated = $this->paginate($candidates, 10);
        $stageCounts = $this->candidateModel->countByStage($selectedJobId ?: null);

        $this->render('recruitment/ats', [
            'pageTitle'     => '招聘看板 (ATS)',
            'candidates'    => $paginated['items'],
            'pagination'    => $paginated,
            'jobs'          => $this->jobModel->all('title ASC'),
            'selectedJobId' => $selectedJobId,
            'selectedStage' => $selectedStage,
            'keyword'       => $keyword,
            'stageCounts'   => $stageCounts,
            'stages'        => Candidate::$stages,
        ]);
    }

    public function addCandidate()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $jobId = (int) $this->input('job_id');
            $firstName = trim((string) $this->input('first_name'));
            $lastName = trim((string) $this->input('last_name'));
            $email = trim((string) $this->input('email'));
            $phone = trim((string) $this->input('phone'));
            $resumeUrl = trim((string) $this->input('resume_url'));
            $linkedinUrl = trim((string) $this->input('linkedin_url'));
            $experience = (float) $this->input('experience_years', 0);
            $currentCompany = trim((string) $this->input('current_company'));
            $stage = $this->input('stage', 'Applied');
            $rating = (int) $this->input('rating', 0);
            $notes = trim((string) $this->input('notes'));

            if (!$jobId) {
                $this->setFlash('error', '请选择招聘职位。');
                $this->redirect('recruitment/addCandidate');
                return;
            }

            if ($firstName === '' || $lastName === '' || $email === '') {
                $this->setFlash('error', '名字、姓氏和邮箱必填。');
                $this->redirect('recruitment/addCandidate?job_id=' . $jobId);
                return;
            }

            $candidateId = (int) $this->candidateModel->insert([
                'job_id'           => $jobId,
                'first_name'       => $firstName,
                'last_name'        => $lastName,
                'email'            => $email,
                'phone'            => $phone ?: null,
                'resume_url'       => $resumeUrl ?: null,
                'linkedin_url'     => $linkedinUrl ?: null,
                'experience_years' => $experience,
                'current_company'  => $currentCompany ?: null,
                'stage'            => array_key_exists($stage, Candidate::$stages) ? $stage : 'Applied',
                'rating'           => max(0, min(5, $rating)),
                'notes'            => $notes ?: null,
            ]);

            // Add initial note if provided
            if ($notes !== '') {
                $this->noteModel->addNote($candidateId, $_SESSION['user_id'] ?? null, $stage, $notes);
            }

            $this->setFlash('success', "候选人 {$firstName} {$lastName} 已成功添加到 ATS 招聘流程。");
            $this->redirect('recruitment/candidate/' . $candidateId);
            return;
        }

        $this->render('recruitment/candidate_form', [
            'pageTitle'       => '添加候选人',
            'jobs'            => $this->jobModel->openJobs(),
            'selectedJobId'   => (int) $this->input('job_id', 0),
            'stages'          => Candidate::$stages,
        ]);
    }

    public function candidate($id)
    {
        $candidate = $this->candidateModel->findWithJob((int) $id);
        if (!$candidate) {
            $this->setFlash('error', '未找到该候选人。');
            $this->redirect('recruitment/ats');
        }

        $notes = $this->noteModel->forCandidate((int) $id);

        $this->render('recruitment/candidate_view', [
            'pageTitle' => '候选人详情 - ' . $candidate['first_name'] . ' ' . $candidate['last_name'],
            'candidate' => $candidate,
            'notes'     => $notes,
            'stages'    => Candidate::$stages,
        ]);
    }

    public function updateStage($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('recruitment/ats');
        }
        $this->verifyCsrf();

        $stage = $this->input('stage');
        if (!array_key_exists($stage, Candidate::$stages)) {
            $this->setFlash('error', '选择的阶段无效。');
            $this->redirect('recruitment/candidate/' . (int) $id);
            return;
        }

        $this->candidateModel->updateStage((int) $id, $stage);

        // Record automated stage transition note
        $username = $_SESSION['username'] ?? 'HR';
        $this->noteModel->addNote((int) $id, $_SESSION['user_id'] ?? null, $stage, "Hiring stage advanced to {$stage} by {$username}.");

        $this->setFlash('success', "候选人招聘阶段已更新。");
        $this->redirect('recruitment/candidate/' . (int) $id);
    }

    public function updateRating($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('recruitment/ats');
        }
        $this->verifyCsrf();

        $rating = max(0, min(5, (int) $this->input('rating', 0)));
        $this->candidateModel->updateRating((int) $id, $rating);

        $this->setFlash('success', "候选人评分已更新为 {$rating} 星。");
        $this->redirect('recruitment/candidate/' . (int) $id);
    }

    public function addNote($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('recruitment/ats');
        }
        $this->verifyCsrf();

        $candidate = $this->candidateModel->find((int) $id);
        if (!$candidate) {
            $this->setFlash('error', '未找到该候选人。');
            $this->redirect('recruitment/ats');
        }

        $note = trim((string) $this->input('note'));
        if ($note !== '') {
            $this->noteModel->addNote((int) $id, $_SESSION['user_id'] ?? null, $candidate['stage'], $note);
            $this->setFlash('success', '评估备注已添加。');
        }

        $this->redirect('recruitment/candidate/' . (int) $id);
    }

    public function deleteCandidate($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('recruitment/ats');
        }
        $this->verifyCsrf();

        $candidate = $this->candidateModel->find((int) $id);
        if ($candidate) {
            $this->candidateModel->delete((int) $id);
            $this->setFlash('success', '候选人已从 ATS 流程中移除。');
        }

        $this->redirect('recruitment/ats');
    }
}
