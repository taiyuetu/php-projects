<?php
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
$stageMeta = $stages[$candidate['stage']] ?? ['badge' => 'secondary', 'icon' => 'bi-circle'];
$stageCnLabels = [
    'Applied' => '已投递',
    'Screening' => '初筛中',
    'Interviewing' => '面试中',
    'Offered' => '已发Offer',
    'Hired' => '已录用',
    'Rejected' => '已淘汰',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h4 class="mb-0"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></h4>
            <span class="badge bg-<?php echo $stageMeta['badge']; ?> fs-6 d-inline-flex align-items-center gap-1">
                <i class="bi <?php echo $stageMeta['icon']; ?>"></i> <?php echo $stageCnLabels[$candidate['stage']] ?? $candidate['stage']; ?>
            </span>
        </div>
        <div class="text-muted small mt-1">
            应聘 <strong><?php echo htmlspecialchars($candidate['job_title']); ?></strong>
            (<?php echo htmlspecialchars($candidate['department_name'] ?? '通用'); ?>)
            · 投递日期 <?php echo date('Y-m-d', strtotime($candidate['applied_date'])); ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回 ATS 招聘看板
        </a>
        <?php if (!empty($candidate['hired_employee_code'])): ?>
            <a href="<?php echo BASE_URL; ?>/employee/profile/<?php echo (int) $candidate['hired_employee_id']; ?>" class="btn btn-sm btn-outline-success" title="查看员工档案">
                <i class="bi bi-person-check-fill"></i> 已办理入职 (工号：<?php echo htmlspecialchars($candidate['hired_employee_code']); ?>)
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/onboarding/create?candidate_id=<?php echo $candidate['id']; ?>" class="btn btn-success btn-sm">
                <i class="bi bi-person-check"></i> 录用并办理入职
            </a>
        <?php endif; ?>
        <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/deleteCandidate/<?php echo $candidate['id']; ?>" class="d-inline" onsubmit="return confirm('确定从 ATS 中移除该候选人吗？');">
            <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm" title="删除候选人">
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
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-person-vcard me-2"></i> 候选人基本信息</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 140px;">电子邮箱：</td>
                    <td>
                        <a href="mailto:<?php echo htmlspecialchars($candidate['email']); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($candidate['email']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">联系电话：</td>
                    <td><?php echo htmlspecialchars($candidate['phone'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">当前任职公司：</td>
                    <td><?php echo htmlspecialchars($candidate['current_company'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">工作经验：</td>
                    <td>
                        <?php echo $candidate['experience_years'] > 0 ? $candidate['experience_years'] . ' 年' : '应届/无经验'; ?>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">简历 / 履历：</td>
                    <td>
                        <?php if (!empty($candidate['resume_url'])): ?>
                            <a href="<?php echo htmlspecialchars($candidate['resume_url']); ?>" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 small">
                                <i class="bi bi-file-earmark-text"></i> 打开简历
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">未提供</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">LinkedIn 主页：</td>
                    <td>
                        <?php if (!empty($candidate['linkedin_url'])): ?>
                            <a href="<?php echo htmlspecialchars($candidate['linkedin_url']); ?>" target="_blank" class="text-decoration-none small">
                                <i class="bi bi-linkedin"></i> 查看主页
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">未提供</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Hiring Stage Advancement Card -->
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-arrow-right-circle me-2"></i> 更新招聘阶段</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/updateStage/<?php echo $candidate['id']; ?>">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <div class="mb-3">
                    <label class="form-label small text-muted">选择新阶段：</label>
                    <select name="stage" class="form-select form-select-sm">
                        <?php foreach ($stages as $stageKey => $sMeta): ?>
                            <option value="<?php echo $stageKey; ?>" <?php echo $candidate['stage'] === $stageKey ? 'selected' : ''; ?>>
                                <?php echo $stageCnLabels[$stageKey] ?? $sMeta['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-check-circle"></i> 保存阶段变更
                </button>
            </form>
        </div>

        <!-- Rating Card -->
        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-star me-2"></i> 候选人评分</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/updateRating/<?php echo $candidate['id']; ?>" class="d-flex align-items-center gap-2">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <select name="rating" class="form-select form-select-sm" style="width: 150px;">
                    <option value="0" <?php echo $candidate['rating'] == 0 ? 'selected' : ''; ?>>0 星</option>
                    <option value="1" <?php echo $candidate['rating'] == 1 ? 'selected' : ''; ?>>★ 1 星</option>
                    <option value="2" <?php echo $candidate['rating'] == 2 ? 'selected' : ''; ?>>★★ 2 星</option>
                    <option value="3" <?php echo $candidate['rating'] == 3 ? 'selected' : ''; ?>>★★★ 3 星</option>
                    <option value="4" <?php echo $candidate['rating'] == 4 ? 'selected' : ''; ?>>★★★★ 4 星</option>
                    <option value="5" <?php echo $candidate['rating'] == 5 ? 'selected' : ''; ?>>★★★★★ 5 星</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-warning flex-grow-1">更新评分</button>
            </form>
        </div>
    </div>

    <!-- Right Column: Initial Notes & Interview Evaluations -->
    <div class="col-md-7">
        <?php if (!empty($candidate['notes'])): ?>
            <div class="card p-3 mb-4">
                <h6 class="fw-bold mb-2 border-bottom pb-2"><i class="bi bi-card-text me-2"></i> 初始投递备注</h6>
                <div class="p-3 bg-light rounded small"><?php echo nl2br(htmlspecialchars($candidate['notes'])); ?></div>
            </div>
        <?php endif; ?>

        <!-- Add Evaluation Note Card -->
        <div class="card p-3 mb-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-chat-square-text me-2"></i> 添加面试反馈与评估备注</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/addNote/<?php echo $candidate['id']; ?>">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <div class="mb-2">
                    <textarea name="note" class="form-control" rows="3" placeholder="输入技术评估、团队契合度、面试反馈或录用建议..." required></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-send"></i> 提交评估备注
                    </button>
                </div>
            </form>
        </div>

        <!-- Evaluation Notes History Thread -->
        <div class="card p-3">
            <h6 class="fw-bold mb-3 border-bottom pb-2">
                <i class="bi bi-clock-history me-2"></i> 评估历史与时间线
                <span class="badge bg-light text-dark border ms-1"><?php echo count($notes); ?></span>
            </h6>

            <?php if (empty($notes)): ?>
                <p class="text-muted small mb-0 py-2">暂无评估备注。使用上方表单记录面试反馈。</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notes as $n): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <strong class="text-primary"><?php echo htmlspecialchars($n['author_username'] ?? 'HR 系统'); ?></strong>
                                    <?php if (!empty($n['author_role'])): ?>
                                        <span class="badge bg-light text-dark border small ms-1"><?php echo htmlspecialchars($n['author_role'] === 'admin' ? '管理员' : ($n['author_role'] === 'hr' ? 'HR' : $n['author_role'])); ?></span>
                                    <?php endif; ?>
                                    <span class="badge bg-secondary-subtle text-secondary small ms-1">阶段：<?php echo htmlspecialchars($stageCnLabels[$n['stage']] ?? $n['stage']); ?></span>
                                </div>
                                <span class="text-muted small"><?php echo date('Y-m-d H:i', strtotime($n['created_at'])); ?></span>
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
