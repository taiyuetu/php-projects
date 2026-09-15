<?php
$percent = $record['total_tasks'] > 0 ? round(($record['completed_tasks'] / $record['total_tasks']) * 100) : 0;
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-danger"><i class="bi bi-box-arrow-right me-2"></i> Offboarding Clearance Checklist</h4>
        <div class="text-muted">
            Managing departure clearance for <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong> (<?php echo htmlspecialchars($record['employee_code']); ?>)
        </div>
    </div>
    <a href="<?php echo BASE_URL; ?>/offboarding" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Offboarding List
    </a>
</div>

<div class="row g-4">
    <!-- Left Column: Departure Details & Completion Trigger -->
    <div class="col-md-4">
        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-person-badge me-2"></i> Employee Details</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 120px;">Name:</td>
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
                    <td class="text-muted">Hire Date:</td>
                    <td><?php echo htmlspecialchars($record['hire_date'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Exit Date:</td>
                    <td><span class="text-danger fw-bold"><?php echo htmlspecialchars($record['exit_date']); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">Reason:</td>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($record['reason']); ?></span></td>
                </tr>
            </table>
        </div>

        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-flag me-2"></i> Clearance Progress</h6>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small fw-semibold">Tasks Completed</span>
                    <span class="small fw-semibold text-primary"><?php echo $percent; ?>%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-warning" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <div class="text-muted small mt-1"><?php echo $record['completed_tasks']; ?> of <?php echo $record['total_tasks']; ?> items cleared</div>
            </div>

            <div class="mb-2">
                <span class="text-muted small">Current Workflow Status:</span><br>
                <?php if ($record['status'] === 'Completed'): ?>
                    <span class="badge bg-success fs-6"><i class="bi bi-check2"></i> Finished & Archived</span>
                    <?php if (!empty($record['completed_at'])): ?>
                        <div class="text-muted small mt-1">Archived on <?php echo htmlspecialchars($record['completed_at']); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-hourglass-split"></i> In Progress</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($record['notes'])): ?>
                <div class="mt-3">
                    <span class="text-muted small">Initial Handover Notes:</span>
                    <div class="p-2 bg-light rounded small mt-1"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($record['status'] !== 'Completed'): ?>
                <hr>
                <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/finish/<?php echo $record['id']; ?>" onsubmit="return confirm('Finish offboarding for this employee? Their data will move to the Offboarded Employees table and status will be updated to Terminated.');">
                    <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Final Settlement / Exit Notes:</label>
                        <textarea name="final_notes" class="form-control form-control-sm" rows="3" placeholder="e.g. All assets recovered, final payroll released, clearance signed off."><?php echo htmlspecialchars($record['notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
                        <i class="bi bi-archive me-1"></i> Finish Offboarding & Archive
                    </button>
                    <div class="form-text text-center mt-2 small">
                        This will transfer the data to the <strong>Offboarded Employees Table</strong> and deactivate login.
                    </div>
                </form>
            <?php else: ?>
                <hr>
                <div class="alert alert-success small mb-0">
                    <i class="bi bi-check-circle me-1"></i> This employee has completed offboarding and their data has been moved to the Offboarded Employees table.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Clearance Tasks -->
    <div class="col-md-8">
        <div class="card p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i> Departure Clearance Checklist</h6>
                <span class="badge bg-light text-dark border"><?php echo count($record['tasks']); ?> Items</span>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($record['tasks'] as $task): ?>
                    <div class="list-group-item px-0 py-3 d-flex align-items-start gap-3 <?php echo $task['is_completed'] ? 'bg-light bg-opacity-25' : ''; ?>">
                        <?php if ($record['status'] !== 'Completed'): ?>
                            <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/toggleTask/<?php echo $task['id']; ?>" class="mt-1">
                                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="is_completed" value="<?php echo $task['is_completed'] ? '0' : '1'; ?>">
                                <input type="checkbox" class="form-check-input" style="cursor: pointer; width: 1.25rem; height: 1.25rem;" 
                                       <?php echo $task['is_completed'] ? 'checked' : ''; ?> 
                                       onchange="this.form.submit()">
                            </form>
                        <?php else: ?>
                            <input type="checkbox" class="form-check-input mt-1" style="width: 1.25rem; height: 1.25rem;" 
                                   <?php echo $task['is_completed'] ? 'checked' : ''; ?> disabled>
                        <?php endif; ?>

                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-semibold <?php echo $task['is_completed'] ? 'text-decoration-line-through text-muted' : ''; ?>">
                                    <?php echo htmlspecialchars($task['task_name']); ?>
                                </div>
                                <?php if ($task['is_completed']): ?>
                                    <span class="badge bg-success-subtle text-success small">Cleared <?php echo $task['completed_at'] ? date('M j, Y', strtotime($task['completed_at'])) : ''; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning small">Pending</span>
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

        <?php if ($record['status'] !== 'Completed'): ?>
            <!-- Add Custom Task Form -->
            <div class="card p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2"></i> Add Custom Clearance Item</h6>
                <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/addTask/<?php echo $record['id']; ?>" class="row g-2">
                    <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                    <div class="col-md-5">
                        <input type="text" name="task_name" class="form-control form-control-sm" placeholder="Clearance item title (e.g. Return Company Car)" required>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="description" class="form-control form-control-sm" placeholder="Description or instructions (optional)">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning btn-sm w-100">
                            <i class="bi bi-plus"></i> Add Item
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
