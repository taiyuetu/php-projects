<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-person-plus text-primary me-2"></i> Add Candidate to ATS</h4>
                <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to ATS
                </a>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/addCandidate">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Target Job Vacancy <span class="text-danger">*</span></label>
                    <select name="job_id" class="form-select" required>
                        <option value="">-- Select Job Opening --</option>
                        <?php foreach ($jobs as $j): ?>
                            <option value="<?php echo $j['id']; ?>" <?php echo ($selectedJobId == $j['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($j['title'] . ' (' . ($j['department_name'] ?? 'General') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" placeholder="e.g. Alex" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" placeholder="e.g. Chen" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. alex.chen@example.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" placeholder="e.g. +65 9123 4567">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Current Company</label>
                        <input type="text" name="current_company" class="form-control" placeholder="e.g. TechCorp Solutions">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Years of Experience</label>
                        <input type="number" step="0.5" min="0" name="experience_years" class="form-control" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Resume / CV Link</label>
                        <input type="url" name="resume_url" class="form-control" placeholder="https://drive.google.com/resume.pdf">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">LinkedIn Profile</label>
                        <input type="url" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/alexchen">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Initial Hiring Stage</label>
                        <select name="stage" class="form-select">
                            <?php foreach ($stages as $stageKey => $sMeta): ?>
                                <option value="<?php echo $stageKey; ?>"><?php echo $sMeta['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Initial Rating</label>
                        <select name="rating" class="form-select">
                            <option value="0">Unrated (0 stars)</option>
                            <option value="1">★☆☆☆☆ (1 star)</option>
                            <option value="2">★★☆☆☆ (2 stars)</option>
                            <option value="3" selected>★★★☆☆ (3 stars)</option>
                            <option value="4">★★★★☆ (4 stars)</option>
                            <option value="5">★★★★★ (5 stars - Strong Hire)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Initial Candidate Notes / Evaluation</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Enter key skills, referral details, or recruiter screening remarks..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Add Candidate to Pipeline
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
