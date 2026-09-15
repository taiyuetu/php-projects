<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0 text-danger"><i class="bi bi-box-arrow-right me-2"></i> Initiate Offboarding</h4>
                <a href="<?php echo BASE_URL; ?>/employee" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Employees
                </a>
            </div>

            <!-- Employee Summary Box -->
            <div class="alert alert-light border d-flex gap-3 align-items-center mb-4">
                <div class="fs-2 text-secondary"><i class="bi bi-person-circle"></i></div>
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h5>
                    <div class="text-muted small">
                        <strong>Code:</strong> <?php echo htmlspecialchars($employee['employee_code']); ?> |
                        <strong>Department:</strong> <?php echo htmlspecialchars($employee['department_name'] ?? 'Unassigned'); ?> |
                        <strong>Designation:</strong> <?php echo htmlspecialchars($employee['designation'] ?? '—'); ?> |
                        <strong>Hire Date:</strong> <?php echo htmlspecialchars($employee['hire_date'] ?? '—'); ?>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning small d-flex gap-2 align-items-center mb-4">
                <i class="bi bi-info-circle fs-5"></i>
                <div>
                    Starting this process generates departure clearance checklists (asset collection, IT access revocation, exit interview). 
                    When clearance is finished, the employee's data will automatically move to the <strong>Offboarded Employees Table</strong>.
                </div>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/initiate/<?php echo $employee['id']; ?>">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Exit Date / Last Working Day <span class="text-danger">*</span></label>
                        <input type="date" name="exit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Reason for Departure <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            <option value="">-- Select Reason --</option>
                            <option value="Resignation">Voluntary Resignation</option>
                            <option value="Termination">Involuntary Termination</option>
                            <option value="End of Contract">Contract Expiration</option>
                            <option value="Layoff">Redundancy / Layoff</option>
                            <option value="Retirement">Retirement</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Handover Notes & Remarks</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Provide any details regarding handover, asset tracking, or departure circumstances..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/employee" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-box-arrow-right"></i> Start Offboarding Checklist
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
