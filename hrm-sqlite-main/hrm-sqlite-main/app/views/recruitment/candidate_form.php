<?php
$stageCnLabels = [
    'Applied' => '已投递',
    'Screening' => '初筛中',
    'Interviewing' => '面试中',
    'Offered' => '已发Offer',
    'Hired' => '已录用',
    'Rejected' => '已淘汰',
];
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-person-plus text-primary me-2"></i> 添加候选人到 ATS 流程</h4>
                <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> 返回 ATS 招聘看板
                </a>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/recruitment/addCandidate">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">应聘职位 <span class="text-danger">*</span></label>
                    <select name="job_id" class="form-select" required>
                        <option value="">-- 选择招聘职位 --</option>
                        <?php foreach ($jobs as $j): ?>
                            <option value="<?php echo $j['id']; ?>" <?php echo ($selectedJobId == $j['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($j['title'] . ' (' . ($j['department_name'] ?? '通用') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">名字 <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" placeholder="例如：San" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">姓氏 <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" placeholder="例如：Zhang" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">电子邮箱 <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="例如：zhangsan@example.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">联系电话</label>
                        <input type="tel" name="phone" class="form-control" placeholder="例如：13800138000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">当前任职公司</label>
                        <input type="text" name="current_company" class="form-control" placeholder="例如：科技创新有限公司">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">工作经验年限</label>
                        <input type="number" step="0.5" min="0" name="experience_years" class="form-control" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">简历 / 履历链接</label>
                        <input type="url" name="resume_url" class="form-control" placeholder="https://drive.google.com/resume.pdf">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">LinkedIn 个人主页</label>
                        <input type="url" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/zhangsan">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">初始招聘阶段</label>
                        <select name="stage" class="form-select">
                            <?php foreach ($stages as $stageKey => $sMeta): ?>
                                <option value="<?php echo $stageKey; ?>"><?php echo $stageCnLabels[$stageKey] ?? $sMeta['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">初始评分</label>
                        <select name="rating" class="form-select">
                            <option value="0">未评分 (0 星)</option>
                            <option value="1">★☆☆☆☆ (1 星)</option>
                            <option value="2">★★☆☆☆ (2 星)</option>
                            <option value="3" selected>★★★☆☆ (3 星)</option>
                            <option value="4">★★★★☆ (4 星)</option>
                            <option value="5">★★★★★ (5 星 - 强烈推荐录用)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">初始评估备注 / 推荐评语</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="填写核心技能、推荐来源或初筛意见..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/recruitment/ats" class="btn btn-light">取消</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2"></i> 添加候选人到招聘流程
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
