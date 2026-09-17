<?php
$percent = $record['total_tasks'] > 0 ? round(($record['completed_tasks'] / $record['total_tasks']) * 100) : 0;
$csrf = htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)));

$taskCnMap = [
    'Verify Identity & Collect Documents' => '身份核验与证件收集',
    'Sign Employment Contract'            => '签订劳动合同',
    'Setup Workstation & IT Accounts'     => '工作台与 IT 账号配置',
    'Team Introduction & Orientation'     => '团队介绍与入职引导',
    'Payroll & Direct Deposit Setup'      => '薪资与银行账户绑定',
    'Complete Workplace Safety Briefing'  => '完成安全及规章制度培训',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">入职清单</h4>
        <div class="text-muted">
            正在跟踪入职流程：<strong><?php echo htmlspecialchars($record['last_name'] . $record['first_name']); ?></strong> (<?php echo htmlspecialchars($record['employee_code']); ?>)
        </div>
    </div>
    <a href="<?php echo BASE_URL; ?>/onboarding" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> 返回入职列表
    </a>
</div>

<div class="row g-4">
    <!-- Employee Overview Card -->
    <div class="col-md-4">
        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-person-badge me-2"></i> 员工详细信息</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 110px;">姓名：</td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($record['last_name'] . $record['first_name']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">工号：</td>
                    <td><?php echo htmlspecialchars($record['employee_code']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">所属部门：</td>
                    <td><?php echo htmlspecialchars($record['department_name'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">职位：</td>
                    <td><?php echo htmlspecialchars($record['designation'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">电子邮箱：</td>
                    <td><?php echo htmlspecialchars($record['email']); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">联系电话：</td>
                    <td><?php echo htmlspecialchars($record['phone'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td class="text-muted">入职日期：</td>
                    <td><?php echo htmlspecialchars($record['hire_date'] ?? '—'); ?></td>
                </tr>
            </table>
        </div>

        <div class="card p-3 mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-flag me-2"></i> 流程状态</h6>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small fw-semibold">入职清单完成度</span>
                    <span class="small fw-semibold text-primary"><?php echo $percent; ?>%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%;"></div>
                </div>
                <div class="text-muted small mt-1">已完成 <?php echo $record['completed_tasks']; ?> / <?php echo $record['total_tasks']; ?> 项任务</div>
            </div>

            <div class="mb-2">
                <span class="text-muted small">状态：</span><br>
                <?php if ($record['status'] === 'Completed'): ?>
                    <span class="badge bg-success fs-6"><i class="bi bi-check2"></i> 已完成</span>
                    <?php if (!empty($record['completed_at'])): ?>
                        <div class="text-muted small mt-1">完成于 <?php echo htmlspecialchars($record['completed_at']); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-arrow-repeat"></i> 进行中</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($record['notes'])): ?>
                <div class="mt-3">
                    <span class="text-muted small">备注：</span>
                    <div class="p-2 bg-light rounded small mt-1"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($record['status'] !== 'Completed'): ?>
                <hr>
                <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/complete/<?php echo $record['id']; ?>" onsubmit="return confirm('确定将此入职流程标记为已完成吗？');">
                    <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                    <div class="mb-2">
                        <label class="form-label small text-muted">完成备注（选填）：</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="例如：所有入职手续已全部办理完毕..."><?php echo htmlspecialchars($record['notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check2-circle"></i> 完成入职流程
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tasks Checklist -->
    <div class="col-md-8">
        <div class="card p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i> 入职任务与要项</h6>
                <span class="badge bg-light text-dark border">共 <?php echo count($record['tasks']); ?> 项任务</span>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($record['tasks'] as $task): ?>
                    <div class="list-group-item px-0 py-3 d-flex align-items-start gap-3 <?php echo $task['is_completed'] ? 'bg-light bg-opacity-25' : ''; ?>">
                        <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/toggleTask/<?php echo $task['id']; ?>" class="mt-1">
                            <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="is_completed" value="<?php echo $task['is_completed'] ? '0' : '1'; ?>">
                            <input type="checkbox" class="form-check-input" style="cursor: pointer; width: 1.25rem; height: 1.25rem;" 
                                   <?php echo $task['is_completed'] ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                        </form>

                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-semibold <?php echo $task['is_completed'] ? 'text-decoration-line-through text-muted' : ''; ?>">
                                    <?php echo htmlspecialchars($taskCnMap[$task['task_name']] ?? $task['task_name']); ?>
                                </div>
                                <?php if ($task['is_completed']): ?>
                                    <span class="badge bg-success-subtle text-success small">已完成 <?php echo $task['completed_at'] ? date('Y-m-d', strtotime($task['completed_at'])) : ''; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary small">待完成</span>
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

        <!-- Add Custom Task Form -->
        <div class="card p-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2"></i> 添加自定义入职要项</h6>
            <form method="POST" action="<?php echo BASE_URL; ?>/onboarding/addTask/<?php echo $record['id']; ?>" class="row g-2">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <div class="col-md-5">
                    <input type="text" name="task_name" class="form-control form-control-sm" placeholder="任务标题（如：开通 CRM 系统权限）" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="描述或说明（选填）">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus"></i> 添加项目
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
