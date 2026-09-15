<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-primary"><i class="bi bi-people"></i></div>
            <div>
                <div class="text-muted small">Total Employees</div>
                <div class="fs-4 fw-bold"><?php echo $totalEmployees; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-success"><i class="bi bi-person-check"></i></div>
            <div>
                <div class="text-muted small">Active Employees</div>
                <div class="fs-4 fw-bold"><?php echo $activeEmployees; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-info"><i class="bi bi-diagram-3"></i></div>
            <div>
                <div class="text-muted small">Departments</div>
                <div class="fs-4 fw-bold"><?php echo $totalDepartments; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="text-muted small">Pending Leaves</div>
                <div class="fs-4 fw-bold"><?php echo $pendingLeaves; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card p-3">
            <h6 class="mb-3">Today's Attendance</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Employee</th><th>Check In</th><th>Check Out</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todayAttendance)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No attendance recorded today.</td></tr>
                        <?php else: foreach ($todayAttendance as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
                                <td><?php echo $a['check_in'] ?? '—'; ?></td>
                                <td><?php echo $a['check_out'] ?? '—'; ?></td>
                                <td><span class="badge badge-status-<?php echo str_replace(' ', '.', $a['status']); ?>"><?php echo $a['status']; ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card p-3">
            <h6 class="mb-3">Recently Added Employees</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentEmployees as $e): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($e['designation'] ?? ''); ?> · <?php echo htmlspecialchars($e['department_name'] ?? 'Unassigned'); ?></div>
                        </div>
                        <span class="badge badge-status-<?php echo $e['status']; ?>"><?php echo $e['status']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
