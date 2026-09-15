<nav class="navbar navbar-light bg-white border-bottom px-4 py-3">
    <span class="fw-semibold fs-5"><?php echo $pageTitle ?? 'Dashboard'; ?></span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small"><?php echo date('l, d F Y'); ?></span>
        <a href="<?php echo BASE_URL; ?>/auth/logout" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>
