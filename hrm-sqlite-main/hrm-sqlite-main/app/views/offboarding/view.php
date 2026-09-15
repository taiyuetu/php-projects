<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-danger"><i class="bi bi-archive me-2"></i> Offboarded Employee Record</h4>
        <div class="text-muted">
            Permanent exit archive for <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong> (<?php echo htmlspecialchars($employee['employee_code']); ?>)
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/offboarding?tab=offboarded" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Offboarded Table
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer"></i> Print Record
        </button>
        <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/deleteOffboarded/<?php echo $employee['id']; ?>" class="d-inline" onsubmit="return confirm('Permanently delete this offboarded employee record? This cannot be undone.');">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash"></i> Delete Permanently
            </button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-person me-2"></i> Employee Information</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 140px;">Employee Code:</td>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($employee['employee_code']); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">Full Name:</td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Email:</td>
                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Phone:</td>
                    <td><?php echo htmlspecialchars($employee['phone'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Department:</td>
                    <td><?php echo htmlspecialchars($employee['department_name'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Designation:</td>
                    <td><?php echo htmlspecialchars($employee['designation'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Hire Date:</td>
                    <td><?php echo htmlspecialchars($employee['hire_date'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Exit Date:</td>
                    <td><span class="text-danger fw-bold"><?php echo htmlspecialchars($employee['exit_date']); ?></span></td>
                </tr>
                <?php if (!empty($employee['hire_date']) && !empty($employee['exit_date'])): 
                    $hire = new DateTime($employee['hire_date']);
                    $exit = new DateTime($employee['exit_date']);
                    $diff = $hire->diff($exit);
                ?>
                <tr>
                    <td class="text-muted">Service Tenure:</td>
                    <td><span class="badge bg-secondary"><?php echo $diff->y . ' yr(s), ' . $diff->m . ' mo(s)'; ?></span></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-info-circle me-2"></i> Archive Metadata</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 140px;">Settlement Status:</td>
                    <td><span class="badge bg-success"><?php echo htmlspecialchars($employee['final_settlement_status'] ?? 'Completed'); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">Reason for Exit:</td>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($employee['reason']); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">Archived On:</td>
                    <td><?php echo htmlspecialchars($employee['offboarded_at']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Processed By:</td>
                    <td><?php echo htmlspecialchars($employee['offboarded_by_username'] ?? 'HR System Admin'); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-check2-all me-2"></i> Clearance Checklist Summary</h6>
            <?php if (!empty($employee['completed_tasks_summary'])): ?>
                <div class="p-3 bg-light rounded font-monospace small" style="white-space: pre-wrap; line-height: 1.6;">
<?php echo htmlspecialchars($employee['completed_tasks_summary']); ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0 small">No task summary recorded.</p>
            <?php endif; ?>
        </div>

        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-chat-left-text me-2"></i> Exit & Settlement Notes</h6>
            <?php if (!empty($employee['notes'])): ?>
                <div class="p-3 bg-light rounded small">
                    <?php echo nl2br(htmlspecialchars($employee['notes'])); ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0 small">No additional remarks or notes recorded.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
