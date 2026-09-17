<?php
/**
 * ApplyController — PUBLIC controller (no login required).
 *
 * New hires scan the onboarding QR code on their phone, open the form at
 * /apply/form/{token} and submit their onboarding information. Submissions
 * land in the admin "入职申请" (onboarding applications) list for review.
 */
class ApplyController extends Controller
{
    private OnboardingInvite $inviteModel;
    private OnboardingApplication $applicationModel;
    private Department $departmentModel;
    private Employee $employeeModel;

    public function __construct()
    {
        // Intentionally NO requireRole()/requireLogin() here:
        // the applicant does not have an account yet.
        $this->inviteModel = $this->model('OnboardingInvite');
        $this->applicationModel = $this->model('OnboardingApplication');
        $this->departmentModel = $this->model('Department');
        $this->employeeModel = $this->model('Employee');
    }

    public function form($token = '')
    {
        $invite = $this->inviteModel->findActiveByToken((string) $token);
        if (!$invite) {
            $this->render('onboarding/apply_invalid', ['pageTitle' => '链接无效'], 'layouts/public');
            return;
        }

        $old = [
            'last_name'          => '',
            'first_name'         => '',
            'email'              => '',
            'phone'              => '',
            'gender'             => '',
            'dob'                => '',
            'id_number'          => '',
            'ethnicity'          => '',
            'household_address'  => '',
            'address'            => '',
            'emergency_contact'  => '',
            'emergency_relation' => '',
            'emergency_phone'    => '',
            'education'          => '',
            'department_id'      => '',
            'designation'        => '',
            'expected_salary'    => '',
            'notes'              => '',
        ];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            foreach ($old as $key => $default) {
                $old[$key] = trim((string) $this->input($key, $default));
            }
            $expectedSalary = (float) $this->input('expected_salary', 0);

            if ($old['last_name'] === '') {
                $errors[] = '请填写姓氏。';
            }
            if ($old['first_name'] === '') {
                $errors[] = '请填写名字。';
            }
            if ($old['email'] === '') {
                $errors[] = '请填写电子邮箱。';
            } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = '请输入有效的电子邮箱地址。';
            }
            if ($old['phone'] === '') {
                $errors[] = '请填写联系电话。';
            }

            if (!$errors) {
                if ($this->employeeModel->findOneBy(['email' => $old['email']])) {
                    $errors[] = '该邮箱已注册为在职员工，如需帮助请联系 HR。';
                } elseif ($this->applicationModel->findPendingByEmail($old['email'])) {
                    $errors[] = '您已提交过入职申请，请耐心等待 HR 审核，无需重复提交。';
                }
            }

            if (!$errors) {
                $this->applicationModel->submit([
                    'invite_id'          => (int) $invite['id'],
                    'first_name'         => $old['first_name'],
                    'last_name'          => $old['last_name'],
                    'email'              => $old['email'],
                    'phone'              => $old['phone'],
                    'gender'             => $old['gender'],
                    'dob'                => $old['dob'],
                    'id_number'          => $old['id_number'],
                    'ethnicity'          => $old['ethnicity'],
                    'household_address'  => $old['household_address'],
                    'emergency_contact'  => $old['emergency_contact'],
                    'emergency_relation' => $old['emergency_relation'],
                    'emergency_phone'    => $old['emergency_phone'],
                    'education'          => $old['education'],
                    'address'            => $old['address'],
                    'department_id'      => $old['department_id'],
                    'designation'        => $old['designation'],
                    'expected_salary'    => $expectedSalary,
                    'notes'              => $old['notes'],
                ]);

                $this->redirect('apply/success/' . $invite['token']);
                return;
            }
        }

        $this->render('onboarding/apply_form', [
            'pageTitle'   => '新员工入职登记',
            'invite'      => $invite,
            'departments' => $this->departmentModel->all('name ASC'),
            'old'         => $old,
            'errors'      => $errors,
            'csrfToken'   => $this->csrfToken(),
        ], 'layouts/public');
    }

    public function success($token = '')
    {
        $this->render('onboarding/apply_success', ['pageTitle' => '提交成功'], 'layouts/public');
    }
}
