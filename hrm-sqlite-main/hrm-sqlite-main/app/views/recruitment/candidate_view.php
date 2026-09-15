<?php
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
$stageMeta = $stages[$candidate['stage']] ?? ['badge' => 'secondary', 'icon' => 'bi-circle'];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h4 class="mb-0"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></h4>
            <span class="badge bg-<?php echo $stageMeta['badge']; ?> fs-6 d-inline-flex align-items-center gap-1">
                <i class="bi <?php echo $stageMeta['icon']; ?>"></i> <?php echo $candidate['stage']; ?>
            </span>
        </div>
        <div class="text-muted small mt-1">
            Applicant for <strong><?php echo htmlspecialchars($candidate['job_title']); ?></strong>
            (<?php echo htmlspecialchars($candidate['department_name'] ?? 'General'); ?>)
            · Applied on <?php echo date('M j, Y', strtotime($candidate['applied_date'])); ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to ATS
        </a>
        <?php if (!empty($candidate['hired_employee_code'])): ?>
            <a href="<?php echo BASE_URL; ?>/employees/view/<?php echo (int) $candidate['hired_employee_id']; ?>" class="btn btn-sm btn-outline-success" title="View Employee Profile">
                <i class="bi bi-person-check-fill"></i> Onboarded as <?php echo htmlspecialchars($candidate['hired_employee_code']); ?>
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/onboarding/create?candidate_id=<?php echo $candidate['id']; ?>" class="btn btn-success btn-sm">
                <i class="bi bi-person-check"></i> Hire & Start Onboarding
            </a>
        <?php endif; ?>
        <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/deleteCandidate/<?php echo $candidate['id']; ?>" class="d-inline" onsubmit="return confirm('Remove this candidate from ATS?');">
            <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Candidate">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Details & Stage Controls -->
    <div class="col-md-5">
        <!-- Contact & Profile Card -->
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-person-vcard me-2"></i> Candidate Information</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 140px;">Email:</td>
                    <td>
                        <a href="mailto:<?php echo htmlspecialchars($candidate['email']); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($candidate['email']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Phone:</td>
                    <td><?php echo htmlspecialchars($candidate['phone'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Current Company:</td>
                    <td><?php echo htmlspecialchars($candidate['current_company'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Experience:</td>
                    <td>
                        <?php echo $candidate['experience_years'] > 0 ? $candidate['experience_years'] . ' years' : 'Entry Level'; ?>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Resume / CV:</td>
                    <td>
                        <?php if (!empty($candidate['resume_url'])): ?>
                            <a href="<?php echo htmlspecialchars($candidate['resume_url']); ?>" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 small">
                                <i class="bi bi-file-earmark-text"></i> Open Resume
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">Not provided</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">LinkedIn:</td>
                    <td>
                        <?php if (!empty($candidate['linkedin_url'])): ?>
                            <a href="<?php echo htmlspecialchars($candidate['linkedin_url']); ?>" target="_blank" class="text-decoration-none small">
                                <i class="bi bi-linkedin"></i> View Profile
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">Not provided</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Hiring Stage Advancement Card -->
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-arrow-right-circle me-2"></i> Update Hiring Stage</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/updateStage/<?php echo $candidate['id']; ?>">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <div class="mb-3">
                    <label class="form-label small text-muted">Select New Stage:</label>
                    <select name="stage" class="form-select form-select-sm">
                        <?php foreach ($stages as $stageKey => $sMeta): ?>
                            <option value="<?php echo $stageKey; ?>" <?php echo $candidate['stage'] === $stageKey ? 'selected' : ''; ?>>
                                <?php echo $sMeta['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-check-circle"></i> Save Stage Transition
                </button>
            </form>
        </div>

        <!-- Rating Card -->
        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-star me-2"></i> Candidate Rating</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/updateRating/<?php echo $candidate['id']; ?>" class="d-flex align-items-center gap-2">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <select name="rating" class="form-select form-select-sm" style="width: 150px;">
                    <option value="0" <?php echo $candidate['rating'] == 0 ? 'selected' : ''; ?>>0 Stars</option>
                    <option value="1" <?php echo $candidate['rating'] == 1 ? 'selected' : ''; ?>>★ 1 Star</option>
                    <option value="2" <?php echo $candidate['rating'] == 2 ? 'selected' : ''; ?>>★★ 2 Stars</option>
                    <option value="3" <?php echo $candidate['rating'] == 3 ? 'selected' : ''; ?>>★★★ 3 Stars</option>
                    <option value="4" <?php echo $candidate['rating'] == 4 ? 'selected' : ''; ?>>★★★★ 4 Stars</option>
                    <option value="5" <?php echo $candidate['rating'] == 5 ? 'selected' : ''; ?>>★★★★★ 5 Stars</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-warning flex-grow-1">Update Rating</button>
            </form>
        </div>
    </div>

    <!-- Right Column: Initial Notes & Interview Evaluations -->
    <div class="col-md-7">
        <?php if (!empty($candidate['notes'])): ?>
            <div class="card p-3 mb-4">
                <h6 class="fw-bold mb-2 border-bottom pb-2"><i class="bi bi-card-text me-2"></i> Initial Application Remarks</h6>
                <div class="p-3 bg-light rounded small"><?php echo nl2br(htmlspecialchars($candidate['notes'])); ?></div>
            </div>
        <?php endif; ?>

        <!-- Add Evaluation Note Card -->
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-chat-square-text me-2"></i> Add Interview Feedback / Evaluation</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/addNote/<?php echo $candidate['id']; ?>">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <div class="mb-2">
                    <textarea name="note" class="form-control" rows="3" placeholder="Enter technical evaluation, culture fit, interview feedback or hiring recommendation..." required></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-send"></i> Post Evaluation Note
                    </button>
                </div>
            </form>
        </div>

        <!-- Evaluation Notes History Thread -->
        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2">
                <i class="bi bi-clock-history me-2"></i> Evaluation History & Timeline
                <span class="badge bg-light text-dark border ms-1"><?php echo count($notes); ?></span>
            </h6>

            <?php if (empty($notes)): ?>
                <p class="text-muted small mb-0 py-2">No evaluation notes yet. Use the form above to record interview feedback.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notes as $n): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <strong class="text-primary"><?php echo htmlspecialchars($n['author_username'] ?? 'HR System'); ?></strong>
                                    <?php if (!empty($n['author_role'])): ?>
                                        <span class="badge bg-light text-dark border small ms-1"><?php echo htmlspecialchars($n['author_role']); ?></span>
                                    <?php endif; ?>
                                    <span class="badge bg-secondary-subtle text-secondary small ms-1">Stage: <?php echo htmlspecialchars($n['stage']); ?></span>
                                </div>
                                <span class="text-muted small"><?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?></span>
                            </div>
                            <div class="small text-secondary mt-1">
                                <?php echo nl2br(htmlspecialchars($n['note'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
