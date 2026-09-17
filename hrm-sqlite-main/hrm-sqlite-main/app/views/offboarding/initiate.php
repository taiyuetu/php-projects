<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0 text-danger"><i class="bi bi-box-arrow-right me-2"></i> 发起离职办理</h4>
                <a href="<?php echo BASE_URL; ?>/employee" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> 返回员工列表
                </a>
            </div>

            <!-- Employee Summary Box -->
            <div class="alert alert-light border d-flex gap-3 align-items-center mb-4">
                <div class="fs-2 text-secondary"><i class="bi bi-person-circle"></i></div>
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($employee['last_name'] . $employee['first_name']); ?></h5>
                    <div class="text-muted small">
                        <strong>工号：</strong> <?php echo htmlspecialchars($employee['employee_code']); ?> |
                        <strong>部门：</strong> <?php echo htmlspecialchars($employee['department_name'] ?? '未分配'); ?> |
                        <strong>职位：</strong> <?php echo htmlspecialchars($employee['designation'] ?? '—'); ?> |
                        <strong>入职日期：</strong> <?php echo htmlspecialchars($employee['hire_date'] ?? '—'); ?>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning small d-flex gap-2 align-items-center mb-4">
                <i class="bi bi-info-circle fs-5"></i>
                <div>
                    启动此流程将生成离职交接清单（资产收回、IT权限撤销、离职面谈等）。当交接完成后，员工数据将自动移至<strong>已离职员工表</strong>。
                </div>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/initiate/<?php echo $employee['id']; ?>">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))); ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">离职日期 / 最后工作日 <span class="text-danger">*</span></label>
                        <input type="date" name="exit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">离职原因 <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            <option value="">-- 选择离职原因 --</option>
                            <option value="Resignation">主动辞职</option>
                            <option value="Termination">解雇 / 辞退</option>
                            <option value="End of Contract">合同到期</option>
                            <option value="Layoff">裁员</option>
                            <option value="Retirement">退休</option>
                            <option value="Other">其他</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">交接说明与备注</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="提供有关交接、资产追溯或离职情况的具体说明..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/employee" class="btn btn-light">取消</a>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-box-arrow-right"></i> 启动离职交接清单
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
