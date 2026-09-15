<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" action="<?php echo BASE_URL; ?>/employee" class="d-flex gap-2">
        <input type="text" name="q" value="<?php echo htmlspecialchars($keyword ?? ''); ?>" class="form-control" placeholder="Search by name, email or code..." style="width: 280px;">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="<?php echo BASE_URL; ?>/employee/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Employee
    </a>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th><th>Name</th><th>Email</th><th>Department</th>
                    <th>Designation</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No employees found.</td></tr>
                <?php else: foreach ($employees as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['employee_code']); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/employee/profile/<?php echo $e['id']; ?>" class="text-decoration-none fw-semibold">
                                <?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($e['email']); ?></td>
                        <td><?php echo htmlspecialchars($e['department_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($e['designation'] ?? '—'); ?></td>
                        <td><span class="badge badge-status-<?php echo $e['status']; ?>"><?php echo $e['status']; ?></span></td>
                        <td class="text-end">
                            <a href="<?php echo BASE_URL; ?>/employee/edit/<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="<?php echo BASE_URL; ?>/employee/delete/<?php echo $e['id']; ?>" class="d-inline" onsubmit="return confirm('Delete this employee? This cannot be undone.');">
                                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
