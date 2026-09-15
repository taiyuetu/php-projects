<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-person-plus text-primary me-2"></i> Onboard New Employee</h4>
                    <div class="text-muted small">Register a new team member and immediately initialize their onboarding checklist.</div>
                </div>
                <a href="<?php echo BASE_URL; ?>/onboarding" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Onboarding List
                </a>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/create">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                <?php if (!empty($candidate)): ?>
                    <input type="hidden" name="candidate_id" value="<?php echo (int) $candidate['id']; ?>">
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-person-check fs-4"></i>
                        <div>
                            <strong>Converting Candidate from ATS:</strong> <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                            <span class="text-muted">(Applied for: <?php echo htmlspecialchars($candidate['job_title']); ?>)</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 1. Personal Information -->
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">1. Personal Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($candidate['first_name'] ?? ''); ?>" placeholder="e.g. John" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($candidate['last_name'] ?? ''); ?>" placeholder="e.g. Doe" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($candidate['email'] ?? ''); ?>" placeholder="e.g. john.doe@example.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($candidate['phone'] ?? ''); ?>" placeholder="e.g. +65 9123 4567">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">-- Select Gender --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Residential Address</label>
                        <input type="text" name="address" class="form-control" placeholder="Street address, unit number, postal code">
                    </div>
                </div>

                <!-- 2. Employment & Job Details -->
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">2. Employment & Job Details</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Suggested Code</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($suggestedCode ?? ''); ?>" readonly>
                        <div class="form-text">Assigned automatically</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo (isset($candidate['department_id']) && $candidate['department_id'] == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Designation / Title</label>
                        <input type="text" name="designation" class="form-control" value="<?php echo htmlspecialchars($candidate['job_title'] ?? ''); ?>" placeholder="e.g. UX Designer">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Monthly Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" name="salary" class="form-control" placeholder="0.00" value="0">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Hire Date / Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="hire_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <!-- 3. Onboarding Checklist Setup -->
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">3. Onboarding Workflow Settings</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Target Completion Date</label>
                        <input type="date" name="target_completion_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                        <div class="form-text">Recommended deadline for completing all checklist items.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Onboarding Notes & Assigned Mentor</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter assigned buddy/mentor, specific IT requests, or orientation notes..."></textarea>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted mb-4 d-flex gap-2 align-items-center">
                    <i class="bi bi-info-circle text-primary fs-5"></i>
                    <div>
                        Submitting this form will register the employee in the database and automatically generate the 6 standard onboarding checklist items (document verification, contract signing, IT provisioning, team introduction, payroll setup, and safety briefing).
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/onboarding" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle"></i> Create Employee & Launch Onboarding
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
