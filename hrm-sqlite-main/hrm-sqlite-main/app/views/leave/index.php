<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Leave Requests</h4>
    <?php if ($_SESSION['user_role'] === 'employee'): ?>
        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/leave/create">
            <i class="bi bi-plus-lg"></i> Apply for Leave
        </a>
    <?php endif; ?>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'])): ?>
                        <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                    <tr><td colspan="<?php echo in_array($_SESSION['user_role'], ['admin', 'hr']) ? '6' : '5'; ?>" class="text-center text-muted py-4">No leave requests found.</td></tr>
                <?php else: foreach ($leaves as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['leave_type']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['start_date'] . ' to ' . $row['end_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['reason'] ?? '—'); ?></td>
                        <td><span class="badge badge-status-<?php echo $row['status']; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'])): ?>
                            <td class="text-end">
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/leave/approve/<?php echo $row['id']; ?>" class="d-inline">
                                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Approve</button>
                                    </form>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/leave/reject/<?php echo $row['id']; ?>" class="d-inline">
                                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
