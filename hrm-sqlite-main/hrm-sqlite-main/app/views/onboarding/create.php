<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-person-plus text-primary me-2"></i> 办理新员工入职</h4>
                    <div class="text-muted small">登记新员工信息并立即初始化其入职清单。</div>
                </div>
                <a href="<?php echo BASE_URL; ?>/onboarding" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> 返回入职列表
                </a>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/create">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">
                <?php if (!empty($candidate)): ?>
                    <input type="hidden" name="candidate_id" value="<?php echo (int) $candidate['id']; ?>">
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-person-check fs-4"></i>
                        <div>
                            <strong>正在从 ATS 转换候选人：</strong> <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                            <span class="text-muted">(申请职位：<?php echo htmlspecialchars($candidate['job_title']); ?>)</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 1. Personal Information -->
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">1. 个人基本信息</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">名字 <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($candidate['first_name'] ?? ''); ?>" placeholder="例如：San" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">姓氏 <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($candidate['last_name'] ?? ''); ?>" placeholder="例如：Zhang" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">电子邮箱 <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($candidate['email'] ?? ''); ?>" placeholder="例如：zhangsan@example.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">联系电话</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($candidate['phone'] ?? ''); ?>" placeholder="例如：13800138000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">性别</label>
                        <select name="gender" class="form-select">
                            <option value="">-- 选择性别 --</option>
                            <option value="Male">男</option>
                            <option value="Female">女</option>
                            <option value="Other">其他</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">出生日期</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">居住地址</label>
                        <input type="text" name="address" class="form-control" placeholder="街道地址、门牌号、邮政编码">
                    </div>
                </div>

                <!-- 2. Employment & Job Details -->
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">2. 雇佣与职位信息</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">建议工号</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($suggestedCode ?? ''); ?>" readonly>
                        <div class="form-text">系统自动分配</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">所属部门</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- 未分配 --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo (isset($candidate['department_id']) && $candidate['department_id'] == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">职位 / 职称</label>
                        <input type="text" name="designation" class="form-control" value="<?php echo htmlspecialchars($candidate['job_title'] ?? ''); ?>" placeholder="例如：UI/UX 设计师">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">月薪</label>
                        <div class="input-group">
                            <span class="input-group-text">￥</span>
                            <input type="number" step="0.01" min="0" name="salary" class="form-control" placeholder="0.00" value="0">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">入职日期 / 起始日期 <span class="text-danger">*</span></label>
                        <input type="date" name="hire_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <!-- 3. Onboarding Checklist Setup -->
                <h6 class="fw-bold text-uppercase text-secondary small border-bottom pb-2 mb-3">3. 入职流程配置</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">目标完成日期</label>
                        <input type="date" name="target_completion_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                        <div class="form-text">建议的入职清单完成截止日期。</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">入职备注与指定导师</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="输入指定的入职导师、IT设备申请要求或培训备注..."></textarea>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted mb-4 d-flex gap-2 align-items-center">
                    <i class="bi bi-info-circle text-primary fs-5"></i>
                    <div>
                        提交此表单将在数据库中注册员工，并自动生成6项标准入职清单任务（身份及证件核验、签订劳动合同、IT软硬件配置、团队成员介绍、薪资账户绑定、安全及制度培训）。
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/onboarding" class="btn btn-light">取消</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle"></i> 创建员工档案并启动入职流程
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
