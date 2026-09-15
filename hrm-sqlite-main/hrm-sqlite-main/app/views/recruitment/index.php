<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-primary"><i class="bi bi-briefcase"></i></div>
            <div>
                <div class="text-muted small">Open Job Vacancies</div>
                <div class="fs-4 fw-bold"><?php echo $totalOpen; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-info"><i class="bi bi-person-lines-fill"></i></div>
            <div>
                <div class="text-muted small">Active Candidates in Pipeline</div>
                <div class="fs-4 fw-bold"><?php echo $activeCandidates; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card p-3 d-flex flex-row align-items-center gap-3">
            <div class="icon bg-success"><i class="bi bi-kanban"></i></div>
            <div>
                <div class="text-muted small">ATS Pipeline</div>
                <div><a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-sm btn-outline-success mt-1">Open ATS Board &rarr;</a></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <h4 class="mb-0">Job Openings</h4>
        <div class="btn-group btn-group-sm ms-2" role="group">
            <a href="<?php echo BASE_URL; ?>/recruitment" class="btn <?php echo empty($statusFilter) ? 'btn-secondary' : 'btn-outline-secondary'; ?>">All</a>
            <a href="<?php echo BASE_URL; ?>/recruitment?status=Open" class="btn <?php echo $statusFilter === 'Open' ? 'btn-success' : 'btn-outline-secondary'; ?>">Open</a>
            <a href="<?php echo BASE_URL; ?>/recruitment?status=Paused" class="btn <?php echo $statusFilter === 'Paused' ? 'btn-warning' : 'btn-outline-secondary'; ?>">Paused</a>
            <a href="<?php echo BASE_URL; ?>/recruitment?status=Closed" class="btn <?php echo $statusFilter === 'Closed' ? 'btn-dark' : 'btn-outline-secondary'; ?>">Closed</a>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-outline-primary">
            <i class="bi bi-kanban"></i> ATS Pipeline
        </a>
        <a href="<?php echo BASE_URL; ?>/recruitment/createJob" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Post Job Opening
        </a>
    </div>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Job Title</th>
                    <th>Department</th>
                    <th>Type & Location</th>
                    <th>Slots</th>
                    <th>Applicants (ATS)</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No job openings found. Click "Post Job Opening" to create one.</td></tr>
                <?php else: foreach ($jobs as $job): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold fs-6"><?php echo htmlspecialchars($job['title']); ?></div>
                            <?php if (!empty($job['salary_range'])): ?>
                                <div class="text-muted small"><i class="bi bi-cash me-1"></i><?php echo htmlspecialchars($job['salary_range']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($job['department_name'] ?? 'Unassigned'); ?></td>
                        <td>
                            <div><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($job['employment_type']); ?></span></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($job['location'] ?? 'Singapore'); ?></div>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo (int) $job['openings_count']; ?></span></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/recruitment/ats?job_id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                <span class="badge bg-info text-dark me-1"><?php echo (int) $job['active_candidates']; ?> Active</span>
                                <span class="badge bg-light text-dark border"><?php echo (int) $job['total_candidates']; ?> Total</span>
                            </a>
                            <?php if ((int) $job['hired_count'] > 0): ?>
                                <span class="badge bg-success ms-1"><?php echo (int) $job['hired_count']; ?> Hired</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($job['status'] === 'Open'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Open</span>
                            <?php elseif ($job['status'] === 'Paused'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-pause-circle"></i> Paused</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Closed</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?php echo BASE_URL; ?>/recruitment/ats?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-info" title="View ATS Candidates">
                                <i class="bi bi-people"></i> ATS
                            </a>
                            <a href="<?php echo BASE_URL; ?>/recruitment/addCandidate?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-success" title="Add Candidate">
                                <i class="bi bi-person-plus"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/recruitment/editJob/<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Opening">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($job['status'] === 'Open'): ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/closeJob/<?php echo $job['id']; ?>" class="d-inline" onsubmit="return confirm('Close this job opening?');">
                                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Close Opening"><i class="bi bi-slash-circle"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
