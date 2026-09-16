<?php
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
$stageCnLabels = [
    'Applied' => '已投递',
    'Screening' => '初筛中',
    'Interviewing' => '面试中',
    'Offered' => '已发Offer',
    'Hired' => '已录用',
    'Rejected' => '已淘汰',
];
?>

<!-- ATS Pipeline Summary Header -->
<div class="card p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-kanban text-primary me-2"></i> 招聘看板 (ATS)</h4>
            <div class="text-muted small">全流程管理候选人从投递到入职的各个阶段。</div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo BASE_URL; ?>/recruitment" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-briefcase"></i> 招聘职位
            </a>
            <a href="<?php echo BASE_URL; ?>/recruitment/addCandidate<?php echo $selectedJobId ? '?job_id=' . $selectedJobId : ''; ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus"></i> 添加候选人
            </a>
        </div>
    </div>

    <!-- Pipeline Stage Progress Pills -->
    <div class="d-flex flex-wrap gap-2 pt-2 border-top">
        <a href="<?php echo BASE_URL; ?>/recruitment/ats<?php echo $selectedJobId ? '?job_id=' . $selectedJobId : ''; ?>" 
           class="btn btn-sm <?php echo empty($selectedStage) ? 'btn-dark' : 'btn-outline-dark'; ?>">
            全部 (<?php echo array_sum($stageCounts); ?>)
        </a>
        <?php foreach ($stages as $key => $meta): ?>
            <?php 
                $isActive = $selectedStage === $key;
                $btnClass = $isActive ? 'btn-' . $meta['badge'] : 'btn-outline-' . $meta['badge'];
                $cLabel = $stageCnLabels[$key] ?? $meta['label'];
            ?>
            <a href="<?php echo BASE_URL; ?>/recruitment/ats?<?php echo http_build_query(array_filter(['job_id' => $selectedJobId, 'stage' => $key, 'q' => $keyword])); ?>" 
               class="btn btn-sm <?php echo $btnClass; ?> d-flex align-items-center gap-1">
                <i class="bi <?php echo $meta['icon']; ?>"></i>
                <span><?php echo $cLabel; ?></span>
                <span class="badge rounded-pill bg-white text-dark ms-1"><?php echo $stageCounts[$key] ?? 0; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Filters Bar -->
<div class="card p-3 mb-3">
    <form method="GET" action="<?php echo BASE_URL; ?>/recruitment/ats" class="row g-2 align-items-center">
        <?php if (!empty($selectedStage)): ?>
            <input type="hidden" name="stage" value="<?php echo htmlspecialchars($selectedStage); ?>">
        <?php endif; ?>

        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="q" value="<?php echo htmlspecialchars($keyword ?? ''); ?>" class="form-control" placeholder="按姓名、邮箱、电话、公司搜索...">
            </div>
        </div>

        <div class="col-md-5">
            <select name="job_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- 全部招聘职位 --</option>
                <?php foreach ($jobs as $j): ?>
                    <option value="<?php echo $j['id']; ?>" <?php echo $selectedJobId == $j['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($j['title'] . ' (' . ($j['status'] === 'Open' ? '招聘中' : ($j['status'] === 'Paused' ? '暂停' : '已关闭')) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">筛选</button>
            <?php if (!empty($selectedJobId) || !empty($selectedStage) || !empty($keyword)): ?>
                <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-sm btn-outline-secondary" title="重置筛选"><i class="bi bi-arrow-counterclockwise"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Candidates Table -->
<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>候选人</th>
                    <th>申请职位</th>
                    <th>工作经验</th>
                    <th>评分</th>
                    <th>招聘阶段</th>
                    <th>投递日期</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($candidates)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            在该阶段或筛选条件下未找到候选人。
                            <br><a href="<?php echo BASE_URL; ?>/recruitment/addCandidate<?php echo $selectedJobId ? '?job_id=' . $selectedJobId : ''; ?>" class="btn btn-sm btn-outline-primary mt-2">添加候选人</a>
                        </td>
                    </tr>
                <?php else: foreach ($candidates as $c): 
                    $stageMeta = $stages[$c['stage']] ?? ['badge' => 'secondary', 'icon' => 'bi-circle'];
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/recruitment/candidate/<?php echo $c['id']; ?>" class="text-decoration-none fw-semibold">
                                <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                            </a>
                            <div class="text-muted small">
                                <?php echo htmlspecialchars($c['email']); ?>
                                <?php if (!empty($c['phone'])): ?> · <?php echo htmlspecialchars($c['phone']); ?><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold small"><?php echo htmlspecialchars($c['job_title']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($c['department_name'] ?? '未分配'); ?></div>
                        </td>
                        <td>
                            <?php if ($c['experience_years'] > 0): ?>
                                <span class="badge bg-light text-dark border"><?php echo $c['experience_years']; ?> 年</span>
                            <?php else: ?>
                                <span class="text-muted small">应届/无经验</span>
                            <?php endif; ?>
                            <?php if (!empty($c['current_company'])): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($c['current_company']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-warning">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= $c['rating'] ? '-fill' : ''; ?>"></i>
                                <?php endfor; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $stageMeta['badge']; ?> d-inline-flex align-items-center gap-1">
                                <i class="bi <?php echo $stageMeta['icon']; ?>"></i> <?php echo $stageCnLabels[$c['stage']] ?? $c['stage']; ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?php echo date('Y-m-d', strtotime($c['applied_date'])); ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?php echo BASE_URL; ?>/recruitment/candidate/<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary" title="查看候选人详情">
                                <i class="bi bi-eye"></i> 查看详情
                            </a>

                            <!-- Hire & Onboard Button -->
                            <?php if (in_array($c['stage'], ['Offered', 'Hired']) && empty($c['hired_employee_id'])): ?>
                                <a href="<?php echo BASE_URL; ?>/onboarding/create?candidate_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-success" title="录用并办理入职">
                                    <i class="bi bi-person-check"></i> 录用并办理入职
                                </a>
                            <?php elseif (!empty($c['hired_employee_code'])): ?>
                                <span class="badge bg-success-subtle text-success small border border-success ms-1">
                                    <i class="bi bi-check-circle"></i> 已入职 (<?php echo htmlspecialchars($c['hired_employee_code']); ?>)
                                </span>
                            <?php endif; ?>

                            <!-- Quick Stage Dropdown -->
                            <div class="dropdown d-inline ms-1">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="变更阶段">
                                    变更
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li class="dropdown-header small">推进阶段</li>
                                    <?php foreach ($stages as $stageKey => $sMeta): ?>
                                        <?php if ($stageKey !== $c['stage']): ?>
                                            <li>
                                                <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/updateStage/<?php echo $c['id']; ?>">
                                                    <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                                                    <input type="hidden" name="stage" value="<?php echo $stageKey; ?>">
                                                    <button type="submit" class="dropdown-item small d-flex align-items-center gap-2">
                                                        <i class="bi <?php echo $sMeta['icon']; ?> text-<?php echo $sMeta['badge']; ?>"></i>
                                                        移至 <?php echo $stageCnLabels[$stageKey] ?? $sMeta['label']; ?>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
