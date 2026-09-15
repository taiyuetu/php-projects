<?php
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
?>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-warning"><i class="bi bi-person-dash"></i></div>
            <div>
                <div class="text-muted small">Active Offboardings in Progress</div>
                <div class="fs-4 fw-bold"><?php echo $totalActiveCount ?? count($activeOffboardings); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-danger"><i class="bi bi-archive"></i></div>
            <div>
                <div class="text-muted small">Total Offboarded Employees</div>
                <div class="fs-4 fw-bold"><?php echo $totalOffboardedCount ?? count($offboardedEmployees); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Offboarding Management</h4>
    <a href="<?php echo BASE_URL; ?>/employee" class="btn btn-outline-primary">
        <i class="bi bi-people"></i> Select Employee to Offboard
    </a>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-3" id="offboardingTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'offboarded' || empty($activeOffboardings)) ? 'active' : ''; ?>" id="offboarded-tab" data-bs-toggle="tab" data-bs-target="#offboarded-pane" type="button" role="tab">
            <i class="bi bi-archive me-1"></i> Offboarded Employees Table
            <span class="badge bg-secondary ms-1"><?php echo $totalOffboardedCount ?? count($offboardedEmployees); ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'active' && !empty($activeOffboardings)) ? 'active' : ''; ?>" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab">
            <i class="bi bi-hourglass-split me-1"></i> Active Offboarding (In Progress)
            <span class="badge bg-warning text-dark ms-1"><?php echo $totalActiveCount ?? count($activeOffboardings); ?></span>
        </button>
    </li>
</ul>

<div class="tab-content" id="offboardingTabContent">
    <!-- Offboarded Employees Table Pane -->
    <div class="tab-pane fade <?php echo ($activeTab === 'offboarded' || empty($activeOffboardings)) ? 'show active' : ''; ?>" id="offboarded-pane" role="tabpanel">
        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Exit Date</th>
                            <th>Reason</th>
                            <th>Offboarded Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offboardedEmployees)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No offboarded employees yet. When an employee completes the offboarding clearance, their record will appear here.</td></tr>
                        <?php else: foreach ($offboardedEmployees as $oe): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($oe['employee_code']); ?></span></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/offboarding/view/<?php echo $oe['id']; ?>" class="text-decoration-none fw-semibold">
                                        <?php echo htmlspecialchars($oe['first_name'] . ' ' . $oe['last_name']); ?>
                                    </a>
                                    <div class="text-muted small"><?php echo htmlspecialchars($oe['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($oe['department_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($oe['designation'] ?? '—'); ?></td>
                                <td><span class="fw-semibold text-danger"><?php echo htmlspecialchars($oe['exit_date']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($oe['reason']); ?></span></td>
                                <td class="text-muted small"><?php echo date('Y-m-d H:i', strtotime($oe['offboarded_at'])); ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?php echo BASE_URL; ?>/offboarding/view/<?php echo $oe['id']; ?>" class="btn btn-sm btn-outline-secondary" title="View Record">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/deleteOffboarded/<?php echo $oe['id']; ?>" class="d-inline" onsubmit="return confirm('Permanently delete this offboarded employee record? This cannot be undone.');">
                                        <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Permanently Delete">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $pagination = $offboardedPagination; require __DIR__ . '/../partials/pagination.php'; ?>
        </div>
    </div>

    <!-- Active Offboardings Pane -->
    <div class="tab-pane fade <?php echo ($activeTab === 'active' && !empty($activeOffboardings)) ? 'show active' : ''; ?>" id="active-pane" role="tabpanel">
        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Planned Exit Date</th>
                            <th>Reason</th>
                            <th style="width: 220px;">Checklist Progress</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeOffboardings)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No employees currently in offboarding. You can start offboarding an employee from the Employees page.</td></tr>
                        <?php else: foreach ($activeOffboardings as $ao): 
                            $percent = $ao['total_tasks'] > 0 ? round(($ao['completed_tasks'] / $ao['total_tasks']) * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($ao['first_name'] . ' ' . $ao['last_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($ao['employee_code']); ?> · <?php echo htmlspecialchars($ao['email']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($ao['department_name'] ?? '—'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($ao['designation'] ?? '—'); ?></div>
                                </td>
                                <td><span class="text-danger fw-semibold"><?php echo htmlspecialchars($ao['exit_date']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ao['reason']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percent; ?>%;"></div>
                                        </div>
                                        <span class="small text-muted text-nowrap"><?php echo $ao['completed_tasks'] . '/' . $ao['total_tasks']; ?> (<?php echo $percent; ?>%)</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($ao['status'] === 'Completed'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check2"></i> Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo BASE_URL; ?>/offboarding/tasks/<?php echo $ao['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-list-check"></i> Manage Clearance
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $pagination = $activePagination; require __DIR__ . '/../partials/pagination.php'; ?>
        </div>
    </div>
</div>
