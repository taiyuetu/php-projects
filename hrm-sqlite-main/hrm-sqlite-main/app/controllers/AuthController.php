<?php
class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = $this->model('User');
    }

    public function login()
    {
        if ($this->isLoggedIn()) {
            $this->redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $username = trim($this->input('username', ''));
            $password = $this->input('password', '');

            $user = $this->userModel->findByUsername($username);

            if ($user && $user['status'] === 'Active' && $this->userModel->verifyPassword($password, $user['password'])) {
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['user_role']    = $user['role'];
                $_SESSION['employee_id']  = $user['employee_id'];
                $this->redirect('dashboard');
            } else {
                $this->setFlash('error', 'Invalid username or password.');
            }
        }

        $this->render('auth/login', ['pageTitle' => 'Login'], 'layouts/auth');
    }

    public function logout()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect('auth/login');
    }
}
