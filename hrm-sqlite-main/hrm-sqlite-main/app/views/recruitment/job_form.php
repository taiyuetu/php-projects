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
                    <?php echo $isEdit ? '编辑招聘职位' : '发布新招聘职位'; ?>
                </h4>
                <a href="<?php echo BASE_URL; ?>/recruitment" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> 返回职位列表
                </a>
            </div>

            <form method="POST" action="<?php echo $actionUrl; ?>">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">职位名称 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($job['title'] ?? ''); ?>" placeholder="例如：高级前端开发工程师" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">所属部门</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- 未分配 --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo (($job['department_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">工作类型</label>
                        <select name="employment_type" class="form-select">
                            <?php 
                            $empTypes = ['Full-Time' => '全职', 'Part-Time' => '兼职', 'Contract' => '合同工', 'Internship' => '实习', 'Remote' => '远程办公'];
                            foreach ($empTypes as $val => $label): 
                            ?>
                                <option value="<?php echo $val; ?>" <?php echo (($job['employment_type'] ?? 'Full-Time') === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">拟招人数</label>
                        <input type="number" min="1" name="openings_count" class="form-control" value="<?php echo htmlspecialchars($job['openings_count'] ?? '1'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">工作地点</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($job['location'] ?? '新加坡'); ?>" placeholder="例如：新加坡 / 远程">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">预估薪资范围</label>
                        <input type="text" name="salary_range" class="form-control" value="<?php echo htmlspecialchars($job['salary_range'] ?? ''); ?>" placeholder="例如：￥15,000 - ￥25,000 / 月">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">状态</label>
                        <select name="status" class="form-select">
                            <option value="Open" <?php echo (($job['status'] ?? 'Open') === 'Open') ? 'selected' : ''; ?>>招聘中</option>
                            <option value="Paused" <?php echo (($job['status'] ?? '') === 'Paused') ? 'selected' : ''; ?>>暂停招募</option>
                            <option value="Closed" <?php echo (($job['status'] ?? '') === 'Closed') ? 'selected' : ''; ?>>已关闭</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">职位描述与工作职责</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="描述岗位职责、日常工作及团队架构..."><?php echo htmlspecialchars($job['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">任职要求与资格条件</label>
                    <textarea name="requirements" class="form-control" rows="3" placeholder="专业技能、工作经验年限、学历要求等..."><?php echo htmlspecialchars($job['requirements'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/recruitment" class="btn btn-light">取消</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg"></i> <?php echo $isEdit ? '更新招聘职位' : '发布招聘职位'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
