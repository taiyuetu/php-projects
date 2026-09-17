<?php
$percent = $record['total_tasks'] > 0 ? round(($record['completed_tasks'] / $record['total_tasks']) * 100) : 0;
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));
$reasonMap = [
    'Resignation' => '主动辞职',
    'Termination' => '解雇 / 辞退',
    'End of Contract' => '合同到期',
    'Layoff' => '裁员',
    'Retirement' => '退休',
    'Other' => '其他'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-danger"><i class="bi bi-box-arrow-right me-2"></i> 离职交接清单</h4>
        <div class="text-muted">
            办理员工离职交接手续：<strong><?php echo htmlspecialchars($record['last_name'] . $record['first_name']); ?></strong> (<?php echo htmlspecialchars($record['employee_code']); ?>)
        </div>
    </div>
    <a href="<?php echo BASE_URL; ?>/offboarding" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> 返回离职管理列表
    </a>
</div>

<div class="row g-4">
    <!-- Left Column: Departure Details & Completion Trigger -->
    <div class="col-md-4">
        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-person-badge me-2"></i> 员工基本信息</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 120px;">姓名：</td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($record['last_name'] . $record['first_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">工号：</td>
                    <td><?php echo htmlspecialchars($record['employee_code']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">部门：</td>
                    <td><?php echo htmlspecialchars($record['department_name'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">职位：</td>
                    <td><?php echo htmlspecialchars($record['designation'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">入职日期：</td>
                    <td><?php echo htmlspecialchars($record['hire_date'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">离职日期：</td>
                    <td><span class="text-danger fw-bold"><?php echo htmlspecialchars($record['exit_date']); ?></span></td>
                </tr>
                <tr>
                    <td class="text-muted">离职原因：</td>
                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($reasonMap[$record['reason']] ?? $record['reason']); ?></span></td>
                </tr>
            </table>
        </div>

        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-flag me-2"></i> 交接办理进度</h6>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small fw-semibold">已完成事项</span>
                    <span class="small fw-semibold text-primary"><?php echo $percent; ?>%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-warning" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <div class="text-muted small mt-1">已完成 <?php echo $record['completed_tasks']; ?> / <?php echo $record['total_tasks']; ?> 项交接</div>
            </div>

            <div class="mb-2">
                <span class="text-muted small">当前流程状态：</span><br>
                <?php if ($record['status'] === 'Completed'): ?>
                    <span class="badge bg-success fs-6"><i class="bi bi-check2"></i> 已完成并归档</span>
                    <?php if (!empty($record['completed_at'])): ?>
                        <div class="text-muted small mt-1">归档时间：<?php echo htmlspecialchars($record['completed_at']); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-hourglass-split"></i> 办理中</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($record['notes'])): ?>
                <div class="mt-3">
                    <span class="text-muted small">初始交接备注：</span>
                    <div class="p-2 bg-light rounded small mt-1"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($record['status'] !== 'Completed'): ?>
                <hr>
                <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/finish/<?php echo $record['id']; ?>" onsubmit="return confirm('确定要完成该员工的离职办理吗？其数据将移至已离职员工表，且员工状态将更新为已解雇/已离职。');">
                    <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">最终结算 / 离职总结备注：</label>
                        <textarea name="final_notes" class="form-control form-control-sm" rows="3" placeholder="例如：所有资产已归还，最终薪酬已结算，交接单已签字核准。"><?php echo htmlspecialchars($record['notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
                        <i class="bi bi-archive me-1"></i> 完成离职办理并归档
                    </button>
                    <div class="form-text text-center mt-2 small">
                        此操作将数据移至<strong>已离职员工表</strong>并停用登录账号。
                    </div>
                </form>
            <?php else: ?>
                <hr>
                <div class="alert alert-success small mb-0">
                    <i class="bi bi-check-circle me-1"></i> 该员工已完成离职交接手续，其数据已移至已离职员工表。
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Clearance Tasks -->
    <div class="col-md-8">
        <div class="card p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i> 离职交接清单明细</h6>
                <span class="badge bg-light text-dark border"><?php echo count($record['tasks']); ?> 项</span>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($record['tasks'] as $task): ?>
                    <div class="list-group-item px-0 py-3 d-flex align-items-start gap-3 <?php echo $task['is_completed'] ? 'bg-light bg-opacity-25' : ''; ?>">
                        <?php if ($record['status'] !== 'Completed'): ?>
                            <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/toggleTask/<?php echo $task['id']; ?>" class="mt-1">
                                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="is_completed" value="<?php echo $task['is_completed'] ? '0' : '1'; ?>">
                                <input type="checkbox" class="form-check-input" style="cursor: pointer; width: 1.25rem; height: 1.25rem;" 
                                       <?php echo $task['is_completed'] ? 'checked' : ''; ?> 
                                       onchange="this.form.submit()">
                            </form>
                        <?php else: ?>
                            <input type="checkbox" class="form-check-input mt-1" style="width: 1.25rem; height: 1.25rem;" 
                                   <?php echo $task['is_completed'] ? 'checked' : ''; ?> disabled>
                        <?php endif; ?>

                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-semibold <?php echo $task['is_completed'] ? 'text-decoration-line-through text-muted' : ''; ?>">
                                    <?php echo htmlspecialchars($task['task_name']); ?>
                                </div>
                                <?php if ($task['is_completed']): ?>
                                    <span class="badge bg-success-subtle text-success small">已完成 <?php echo $task['completed_at'] ? date('Y-m-d', strtotime($task['completed_at'])) : ''; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning small">待处理</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <div class="text-muted small mt-1"><?php echo htmlspecialchars($task['description']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($record['status'] !== 'Completed'): ?>
            <!-- Add Custom Task Form -->
            <div class="card p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2"></i> 添加自定义交接事项</h6>
                <form method="POST" action="<?php echo BASE_URL; ?>/offboarding/addTask/<?php echo $record['id']; ?>" class="row g-2">
                    <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                    <div class="col-md-5">
                        <input type="text" name="task_name" class="form-control form-control-sm" placeholder="交接事项名称（如：归还公司车辆）" required>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="description" class="form-control form-control-sm" placeholder="描述或说明（可选）">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning btn-sm w-100">
                            <i class="bi bi-plus"></i> 添加事项
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
