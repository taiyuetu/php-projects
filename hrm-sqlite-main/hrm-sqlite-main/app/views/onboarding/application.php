<?php
$csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$isPending = $application['status'] === 'Pending';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        入职申请详情 -
        <?php echo htmlspecialchars($application['last_name'] . $application['first_name']); ?>
        <?php if ($application['status'] === 'Pending'): ?>
            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-hourglass-split"></i> 待审核</span>
        <?php elseif ($application['status'] === 'Approved'): ?>
            <span class="badge bg-success ms-2"><i class="bi bi-check2"></i> 已批准</span>
        <?php else: ?>
            <span class="badge bg-danger ms-2"><i class="bi bi-x"></i> 已拒绝</span>
        <?php endif; ?>
    </h4>
    <a href="<?php echo BASE_URL; ?>/onboarding/applications" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> 返回申请列表
    </a>
</div>

<div class="row g-3">
    <!-- Left: submitted information -->
    <div class="col-lg-7">
        <div class="card p-4 mb-3">
            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">1. 基本信息</h6>
            <div class="row mb-3">
                <?php
                $fields = [
                    '姓名'          => $application['last_name'] . $application['first_name'],
                    '电子邮箱'      => $application['email'],
                    '联系电话'      => $application['phone'],
                    '性别'          => $application['gender'] === 'Male' ? '男' : ($application['gender'] === 'Female' ? '女' : ($application['gender'] === 'Other' ? '其他' : null)),
                    '出生日期'      => $application['dob'],
                    '身份证号'      => $application['id_number'],
                    '民族'          => $application['ethnicity'],
                    '学历'          => $application['education'],
                ];
                foreach ($fields as $label => $value): ?>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small"><?php echo $label; ?></div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($value ?? '—'); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">2. 地址信息</h6>
            <div class="row mb-3">
                <div class="col-md-6 mb-2">
                    <div class="text-muted small">户籍地址</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($application['household_address'] ?? '—'); ?></div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="text-muted small">现居住址</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($application['address'] ?? '—'); ?></div>
                </div>
            </div>

            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">3. 紧急联系人</h6>
            <div class="row mb-3">
                <div class="col-md-4 mb-2">
                    <div class="text-muted small">姓名</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($application['emergency_contact'] ?? '—'); ?></div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="text-muted small">与本人关系</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($application['emergency_relation'] ?? '—'); ?></div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="text-muted small">联系电话</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($application['emergency_phone'] ?? '—'); ?></div>
                </div>
            </div>

            <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">4. 职位意向</h6>
            <div class="row mb-3">
                <div class="col-md-4 mb-2">
                    <div class="text-muted small">意向部门</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($application['department_name'] ?? '—'); ?></div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="text-muted small">意向职位</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($application['designation'] ?? '—'); ?></div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="text-muted small">期望薪资（月薪）</div>
                    <div class="fw-semibold"><?php echo $application['expected_salary'] > 0 ? number_format((float) $application['expected_salary'], 2) : '—'; ?></div>
                </div>
            </div>

            <?php if (!empty($application['notes'])): ?>
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">5. 备注</h6>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($application['notes'])); ?></p>
            <?php endif; ?>

            <div class="text-muted small border-top pt-2 mt-3">
                提交时间：<?php echo htmlspecialchars($application['submitted_at']); ?>
            </div>
        </div>

        <?php if (!$isPending): ?>
            <div class="card p-4">
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">审核结果</h6>
                <div class="mb-2">
                    <span class="text-muted small">审核时间：</span><?php echo htmlspecialchars($application['reviewed_at'] ?? '—'); ?>
                </div>
                <?php if (!empty($application['review_comment'])): ?>
                    <div class="mb-2"><span class="text-muted small">审核备注：</span><?php echo htmlspecialchars($application['review_comment']); ?></div>
                <?php endif; ?>
                <?php if ($application['status'] === 'Approved' && $onboardingRecord): ?>
                    <a href="<?php echo BASE_URL; ?>/onboarding/tasks/<?php echo (int) $onboardingRecord['id']; ?>" class="btn btn-outline-primary btn-sm mt-2">
                        <i class="bi bi-list-check"></i> 查看入职清单
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: review actions -->
    <?php if ($isPending): ?>
    <div class="col-lg-5">
        <div class="card p-4 mb-3 border-success">
            <h6 class="fw-bold text-success mb-3"><i class="bi bi-person-check me-1"></i>批准并启动入职流程</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/approve/<?php echo (int) $application['id']; ?>">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">入职日期</label>
                        <input type="date" name="hire_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">目标完成日期</label>
                        <input type="date" name="target_completion_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">入职部门</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- 选择部门 --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int) $d['id']; ?>" <?php echo (int) $application['department_id'] === (int) $d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">职位</label>
                        <input type="text" name="designation" class="form-control" value="<?php echo htmlspecialchars($application['designation'] ?? ''); ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">月薪</label>
                        <input type="number" step="0.01" min="0" name="salary" class="form-control" value="<?php echo htmlspecialchars((string) $application['expected_salary']); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">审核备注（可选）</label>
                        <textarea name="review_comment" class="form-control" rows="2" placeholder="审批意见"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-check2-circle"></i> 批准入职
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card p-4 border-danger">
            <h6 class="fw-bold text-danger mb-3"><i class="bi bi-person-x me-1"></i>拒绝该申请</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/reject/<?php echo (int) $application['id']; ?>"
                  onsubmit="return confirm('确定拒绝该入职申请？此操作不可撤销。');">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold">拒绝原因（可选）</label>
                    <textarea name="review_comment" class="form-control" rows="2" placeholder="拒绝原因"></textarea>
                </div>
                <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="bi bi-x-circle"></i> 拒绝申请
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
