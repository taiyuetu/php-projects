<?php
$percent = $record['total_tasks'] > 0 ? round(($record['completed_tasks'] / $record['total_tasks']) * 100) : 0;
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Onboarding Checklist</h4>
        <div class="text-muted">
            Tracking onboarding workflow for <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong> (<?php echo htmlspecialchars($record['employee_code']); ?>)
        </div>
    </div>
    <a href="<?php echo BASE_URL; ?>/onboarding" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Onboarding List
    </a>
</div>

<div class="row g-4">
    <!-- Employee Overview Card -->
    <div class="col-md-4">
        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-person-badge me-2"></i> Employee Details</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 110px;">Name:</td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Code:</td>
                    <td><?php echo htmlspecialchars($record['employee_code']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Department:</td>
                    <td><?php echo htmlspecialchars($record['department_name'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Designation:</td>
                    <td><?php echo htmlspecialchars($record['designation'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Email:</td>
                    <td><?php echo htmlspecialchars($record['email']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Phone:</td>
                    <td><?php echo htmlspecialchars($record['phone'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Hire Date:</td>
                    <td><?php echo htmlspecialchars($record['hire_date'] ?? '—'); ?></td>
                </tr>
            </table>
        </div>

        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-flag me-2"></i> Workflow Status</h6>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small fw-semibold">Checklist Progress</span>
                    <span class="small fw-semibold text-primary"><?php echo $percent; ?>%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <div class="text-muted small mt-1"><?php echo $record['completed_tasks']; ?> of <?php echo $record['total_tasks']; ?> tasks completed</div>
            </div>

            <div class="mb-2">
                <span class="text-muted small">Status:</span><br>
                <?php if ($record['status'] === 'Completed'): ?>
                    <span class="badge bg-success fs-6"><i class="bi bi-check2"></i> Completed</span>
                    <?php if (!empty($record['completed_at'])): ?>
                        <div class="text-muted small mt-1">Finished on <?php echo htmlspecialchars($record['completed_at']); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-arrow-repeat"></i> In Progress</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($record['notes'])): ?>
                <div class="mt-3">
                    <span class="text-muted small">Notes:</span>
                    <div class="p-2 bg-light rounded small mt-1"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($record['status'] !== 'Completed'): ?>
                <hr>
                <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/complete/<?php echo $record['id']; ?>" onsubmit="return confirm('Mark this onboarding as completed?');">
                    <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                    <div class="mb-2">
                        <label class="form-label small text-muted">Completion Notes (optional):</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. All requirements satisfied..."><?php echo htmlspecialchars($record['notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check2-circle"></i> Complete Onboarding
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tasks Checklist -->
    <div class="col-md-8">
        <div class="card p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i> Tasks & Requirements</h6>
                <span class="badge bg-light text-dark border"><?php echo count($record['tasks']); ?> Total Items</span>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($record['tasks'] as $task): ?>
                    <div class="list-group-item px-0 py-3 d-flex align-items-start gap-3 <?php echo $task['is_completed'] ? 'bg-light bg-opacity-25' : ''; ?>">
                        <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/toggleTask/<?php echo $task['id']; ?>" class="mt-1">
                            <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="is_completed" value="<?php echo $task['is_completed'] ? '0' : '1'; ?>">
                            <input type="checkbox" class="form-check-input" style="cursor: pointer; width: 1.25rem; height: 1.25rem;" 
                                   <?php echo $task['is_completed'] ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                        </form>

                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-semibold <?php echo $task['is_completed'] ? 'text-decoration-line-through text-muted' : ''; ?>">
                                    <?php echo htmlspecialchars($task['task_name']); ?>
                                </div>
                                <?php if ($task['is_completed']): ?>
                                    <span class="badge bg-success-subtle text-success small">Done <?php echo $task['completed_at'] ? date('M j, Y', strtotime($task['completed_at'])) : ''; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary small">Pending</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <div class="text-muted small mt-1"><?php echo htmlspecialchars($task['description']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Add Custom Task Form -->
        <div class="card p-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2"></i> Add Custom Onboarding Item</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/addTask/<?php echo $record['id']; ?>" class="row g-2">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <div class="col-md-5">
                    <input type="text" name="task_name" class="form-control form-control-sm" placeholder="Task title (e.g. Access to CRM)" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Description or instructions (optional)">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus"></i> Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
