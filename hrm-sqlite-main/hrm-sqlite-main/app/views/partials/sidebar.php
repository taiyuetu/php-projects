<?php
$currentUrl = trim($_GET['url'] ?? 'dashboard', '/');
$currentController = strtolower(explode('/', $currentUrl)[0] ?: 'dashboard');
$role = $_SESSION['user_role'] ?? 'employee';

if (!function_exists('navActive')) {
    function navActive($section, $currentController) {
        return $section === $currentController ? 'active' : '';
    }
}
?>
<nav id="sidebar" class="d-flex flex-column flex-shrink-0 p-3 text-white">
    <a href="<?php echo BASE_URL; ?>/dashboard" class="d-flex align-items-center mb-3 text-white text-decoration-none">
        <i class="bi bi-people-fill fs-4 me-2"></i>
        <span class="fs-5 fw-bold">HRMS</span>
    </a>
    <hr class="text-white-50">
    <ul class="nav nav-pills flex-column mb-auto gap-1">
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/dashboard" class="nav-link text-white <?php echo navActive('dashboard', $currentController); ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>
        <?php if (in_array($role, ['admin', 'hr'])): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/employee" class="nav-link text-white <?php echo navActive('employee', $currentController); ?>">
                <i class="bi bi-person-badge me-2"></i> Employees
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/onboarding" class="nav-link text-white <?php echo navActive('onboarding', $currentController); ?>">
                <i class="bi bi-person-plus me-2"></i> Onboarding
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/offboarding" class="nav-link text-white <?php echo navActive('offboarding', $currentController); ?>">
                <i class="bi bi-box-arrow-right me-2"></i> Offboarding
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/department" class="nav-link text-white <?php echo navActive('department', $currentController); ?>">
                <i class="bi bi-diagram-3 me-2"></i> Departments
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/attendance" class="nav-link text-white <?php echo navActive('attendance', $currentController); ?>">
                <i class="bi bi-calendar-check me-2"></i> Attendance
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/leave" class="nav-link text-white <?php echo navActive('leave', $currentController); ?>">
                <i class="bi bi-airplane me-2"></i> Leave
            </a>
        </li>
        <?php if (in_array($role, ['admin', 'hr'])): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/payroll" class="nav-link text-white <?php echo navActive('payroll', $currentController); ?>">
                <i class="bi bi-cash-coin me-2"></i> Payroll
            </a>
        </li>
        <?php endif; ?>
    </ul>
    <hr class="text-white-50">
    <div class="text-white-50 small">Logged in as<br><strong class="text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong> (<?php echo htmlspecialchars($role); ?>)</div>
</nav>
