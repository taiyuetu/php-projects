<?php
$isEdit = !empty($job);
$actionUrl = $isEdit ? BASE_URL . '/recruitment/editJob/' . $job['id'] : BASE_URL . '/recruitment/createJob';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-briefcase text-primary me-2"></i>
                    <?php echo $isEdit ? 'Edit Job Opening' : 'Post New Job Opening'; ?>
                </h4>
                <a href="<?php echo BASE_URL; ?>/recruitment" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Openings
                </a>
            </div>

            <form method="POST" action="<?php echo $actionUrl; ?>">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Job Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($job['title'] ?? ''); ?>" placeholder="e.g. Senior Frontend Developer" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo (($job['department_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Employment Type</label>
                        <select name="employment_type" class="form-select">
                            <?php foreach (['Full-Time', 'Part-Time', 'Contract', 'Internship', 'Remote'] as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo (($job['employment_type'] ?? 'Full-Time') === $type) ? 'selected' : ''; ?>><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Open Positions</label>
                        <input type="number" min="1" name="openings_count" class="form-control" value="<?php echo htmlspecialchars($job['openings_count'] ?? '1'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($job['location'] ?? 'Singapore'); ?>" placeholder="e.g. Singapore / Remote">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Estimated Salary Range</label>
                        <input type="text" name="salary_range" class="form-control" value="<?php echo htmlspecialchars($job['salary_range'] ?? ''); ?>" placeholder="e.g. $5,500 - $7,000 / month">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Open" <?php echo (($job['status'] ?? 'Open') === 'Open') ? 'selected' : ''; ?>>Open (Actively Hiring)</option>
                            <option value="Paused" <?php echo (($job['status'] ?? '') === 'Paused') ? 'selected' : ''; ?>>Paused (On Hold)</option>
                            <option value="Closed" <?php echo (($job['status'] ?? '') === 'Closed') ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Job Description & Responsibilities</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe the role, day-to-day responsibilities, and team structure..."><?php echo htmlspecialchars($job['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Requirements & Qualifications</label>
                    <textarea name="requirements" class="form-control" rows="3" placeholder="Skills, years of experience, educational requirements..."><?php echo htmlspecialchars($job['requirements'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/recruitment" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg"></i> <?php echo $isEdit ? 'Update Job Opening' : 'Publish Job Opening'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
