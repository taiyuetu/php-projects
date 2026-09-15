<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Attendance</h4>
    <form class="d-flex gap-2" method="GET" action="<?php echo BASE_URL; ?>/attendance">
        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
        <button class="btn btn-outline-secondary"><i class="bi bi-filter"></i> Filter</button>
    </form>
</div>

<?php if ($_SESSION['user_role'] === 'employee'): ?>
    <div class="mb-3">
        <form method="POST" action="<?php echo BASE_URL; ?>/attendance/check-in" class="d-inline">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
            <button class="btn btn-success"><i class="bi bi-box-arrow-in-right"></i> Check in</button>
        </form>
        <form method="POST" action="<?php echo BASE_URL; ?>/attendance/check-out" class="d-inline">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
            <button class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Check out</button>
        </form>
    </div>
<?php endif; ?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No attendance recorded for this date.</td></tr>
                <?php else: foreach ($attendance as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['check_in'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['check_out'] ?? '—'); ?></td>
                        <td><span class="badge badge-status-<?php echo str_replace(' ', '.', $row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
